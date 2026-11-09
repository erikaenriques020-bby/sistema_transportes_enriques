<?php

use App\Filament\Pages\ImportarPosicionesGps;
use App\Filament\Pages\UbicacionActivos;
use App\Models\Truck;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('local');
});

function locationWorkbook(array $rows): UploadedFile
{
    return fleetTestUpload($rows, ['Placa', 'Fecha', 'Hora', 'Latitud', 'Longitud']);
}

test('suggests the GPS columns and requires review confirmation before writing any positions', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1']);
    $component = Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16.5, -68.1]]))
        ->call('analyze')->assertHasNoErrors()->assertSet('mapping.plate', '0')->assertSet('mapping.latitude', '3')
        ->call('prepareImport')->assertHasNoErrors()->assertSet('summary.counts.new', 1)->assertDispatched('open-modal', id: 'gps-review');
    $this->assertDatabaseCount('gps_positions', 0);

    $component->call('confirmImport')->assertHasErrors('reviewConfirmed')->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()
        ->assertSet('result.positions_created', 1)->assertDispatched('open-modal', id: 'gps-success');

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->assertSet('points.0.lat', -16.5);
});

test('imported positions are available as an ordered daily route immediately after confirmation', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1']);
    Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook([['GPS-1', '27/10/2026', '10:00', -16.6, -68.2], ['GPS-1', '27/10/2026', '09:00', -16.5, -68.1]]))
        ->call('analyze')->call('prepareImport')->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors();

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->set('date', '2026-10-27')->call('showRoute')
        ->assertSet('points.0.at', '27/10/2026 09:00:00')->assertSet('points.1.at', '27/10/2026 10:00:00');
});

test('rejects a fleet workbook without coordinates and invalidates review when mapping changes', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    Livewire::test(ImportarPosicionesGps::class)->set('file', fleetTestUpload([['GPS-1', 'Ana']]))->call('analyze')->call('prepareImport')->assertHasErrors('mapping');
    $component = Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]))->call('analyze')->call('prepareImport');

    $component->set('mapping.latitude', '4')->assertSet('summary', null)->assertSet('fingerprint', null)
        ->call('prepareImport')->assertHasErrors('mapping');
    $this->assertDatabaseCount('gps_positions', 0);
});

test('filters and paginates source rows without sending the whole report to the browser', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $rows = [];
    for ($minute = 0; $minute < 18; $minute++) {
        $rows[] = ['GPS-1', '27/10/2026', sprintf('09:%02d', $minute), -16, -68];
    }
    $rows[] = ['GPS-1', '27/10/2026', '09:00', -16, -68];
    $component = Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook($rows))->call('analyze')->call('prepareImport');

    $component->call('nextReviewPage')->assertSet('reviewPage', 2)->set('reviewFilter', 'duplicates')->assertSet('reviewPage', 1)->assertSee('Repetida en Excel');
    $this->assertDatabaseCount('gps_positions', 0);
});

test('shows unknown plates and blocks confirmation until they are registered and reviewed again', function () {
    $component = Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook([['NEW-1', '27/10/2026', '09:00', -16, -68]]))->call('analyze')->call('prepareImport')
        ->assertSet('summary.counts.unknown_plate', 1)->assertSee('Placa no registrada');

    $component->set('reviewConfirmed', true)->call('confirmImport')->assertHasErrors('import');
    Truck::factory()->create(['plate' => 'NEW-1']);
    $component->call('prepareImport')->assertSet('summary.counts.new', 1)->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors();

    $this->assertDatabaseCount('gps_positions', 1);
});

test('reupload of a completed file shows that no further positions will be created', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $file = locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]);
    $component = Livewire::test(ImportarPosicionesGps::class)->set('file', $file)->call('analyze')->call('prepareImport')->set('reviewConfirmed', true)->call('confirmImport');

    $component->set('file', $file)->call('analyze')->call('prepareImport')->assertSet('completed', true)->assertSet('fingerprint', null)->assertSee('Este archivo ya fue importado.');
    $this->assertDatabaseCount('gps_positions', 1);
});

test('requires existing panel authentication for uploading GPS reports', function () {
    $this->get('/admin/ubicacion/importar-gps')->assertRedirect('/admin/login');
});

test('explains missing files and permits retrying analysis with a valid upload', function () {
    $component = Livewire::test(ImportarPosicionesGps::class)->call('analyze')
        ->assertHasErrors(['file' => 'required'])->assertSee('Selecciona un archivo Excel antes de analizar.');

    $component->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]))
        ->call('analyze')->assertHasNoErrors()->assertSet('mapping.plate', '0');
});

test('shows actionable errors for unreadable workbooks and recovers after replacing them', function () {
    $component = Livewire::test(ImportarPosicionesGps::class)
        ->set('file', UploadedFile::fake()->createWithContent('dañado.xlsx', 'Esto no es un libro Excel'))
        ->call('analyze')->assertHasErrors('file')->assertSet('inspection', null);

    $component->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]))
        ->call('analyze')->assertHasNoErrors()->assertSet('mapping.latitude', '3');
});

test('identifies absent headers and clears obsolete inspection after failed reanalysis', function () {
    $component = Livewire::test(ImportarPosicionesGps::class)
        ->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]))
        ->call('analyze')->assertHasNoErrors()->set('headerRow', 100)->call('analyze')
        ->assertHasErrors('file')->assertSet('inspection', null)->assertSee('No se encontraron encabezados');

    $component->set('headerRow', 1)->call('analyze')->assertHasNoErrors()->assertSet('mapping.plate', '0');
});

test('selects place columns and opens a place review with confirmation before saving', function () {
    $component = Livewire::test(ImportarPosicionesGps::class)->set('target', 'places')
        ->set('file', fleetTestUpload([['Terminal', -16, -68]], ['Nombre del lugar', 'Latitud', 'Longitud']))
        ->call('analyze')->assertHasNoErrors()->assertSet('mapping.name', '0')->assertSet('mapping.latitude', '1')
        ->call('prepareImport')->assertHasNoErrors()->assertSet('importTarget', 'places')->assertSet('summary.counts.new', 1)->assertSee('Nombre del lugar');
    $this->assertDatabaseCount('places', 0);
    $component->call('confirmImport')->assertHasErrors('reviewConfirmed')->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()->assertSet('result.places_created', 1);
    $this->assertDatabaseCount('places', 1);
    $this->assertDatabaseCount('gps_positions', 0);
});

test('changing destination invalidates pending review and proposes the other column schema', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    Livewire::test(ImportarPosicionesGps::class)->set('file', locationWorkbook([['GPS-1', '27/10/2026', '09:00', -16, -68]]))
        ->call('analyze')->call('prepareImport')->assertSet('summary.counts.new', 1)
        ->set('target', 'places')->assertSet('importId', null)->assertSet('fingerprint', null)->assertSet('mapping.name', '')->assertSet('mapping.latitude', '3');
});

test('long routes include first and last observations without truncating the end of the day', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1']);
    $start = CarbonImmutable::parse('2026-10-27 00:00:00');
    $rows = [];
    for ($i = 0; $i < 5002; $i++) {
        $rows[] = ['truck_id' => $truck->id, 'observed_at' => $start->addSeconds($i)->format('Y-m-d H:i:s'), 'latitude' => -16, 'longitude' => -68, 'details' => json_encode(['event' => 'Ubicación'])];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('gps_positions')->insert($chunk);
    }
    $component = Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->set('date', '2026-10-27')->call('showRoute')->assertHasNoErrors();
    $points = $component->get('points');
    expect(count($points))->toBeLessThanOrEqual(5000)->and($points[0]['at'])->toBe('27/10/2026 00:00:00')->and(end($points)['at'])->toBe('27/10/2026 01:23:21')->and($points[0]['details']['event'])->toBe('Ubicación');
});
