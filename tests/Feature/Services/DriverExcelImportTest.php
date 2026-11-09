<?php

use App\Filament\Pages\AnalizarExcelFlota;
use App\Filament\Resources\FleetPeople\Pages\CreateFleetPerson;
use App\Filament\Resources\Owners\Pages\CreateOwner;
use App\Filament\Resources\Owners\Pages\ListOwners;
use App\Models\FleetPerson;
use App\Models\Truck;
use App\Services\DriverExcelImport;
use App\Services\FleetExcelImport;
use App\Services\XlsxReportReader;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('imports independent drivers with repeated names and documents without creating trucks', function () {
    $file = fleetTestUpload([['Ana', '123', '700'], ['ANA', '123', '700'], ['Luis', '', '']], ['Conductor', 'Documento', 'Teléfono']);
    $service = app(DriverExcelImport::class);
    $prepared = $service->prepare($file, ['driver' => 0, 'document' => 1, 'phone' => 2], 'Activos', 1);
    expect($prepared['preview']['drivers'])->toHaveCount(2);
    $import = $service->confirm($prepared['import']->id, FleetExcelImport::fingerprint($prepared['import']));
    expect($import->result['drivers_created'])->toBe(2);
    $service->confirm($import->id, FleetExcelImport::fingerprint($import));
    $repeat = $service->prepare($file, ['driver' => 0, 'document' => 1, 'phone' => 2], 'Activos', 1);
    expect($repeat['preview']['drivers'])->toBe([]);
    $this->assertDatabaseCount('trucks', 0);
    $this->assertDatabaseCount('fleet_people', 2);
});

test('does not duplicate an existing owner or guess identity from a name without a document', function () {
    FleetPerson::factory()->create(['name' => 'Ana', 'document_number' => null, 'is_owner' => true, 'is_driver' => false]);
    $prepared = app(DriverExcelImport::class)->prepare(fleetTestUpload([['Ana', '123']]), ['driver' => 0, 'document' => 1], 'Activos', 1);
    expect($prepared['preview']['drivers'])->toBe([])->and($prepared['preview']['invalid'])->toHaveCount(1);
    $this->assertDatabaseCount('fleet_people', 1);
});

test('supports separate import purposes for the same file and detects driver headers below metadata', function () {
    $file = fleetTestUpload([['Ana']], ['Conductor'], 12);
    $inspection = app(XlsxReportReader::class)->inspect($file->getRealPath());
    expect($inspection['header_row'])->toBe(12);
    app(DriverExcelImport::class)->prepare($file, ['driver' => 0], 'Activos', 12);
    app(FleetExcelImport::class)->prepare($file, ['plate' => 0], 'Activos', 12);
    $this->assertDatabaseCount('fleet_imports', 2);
});

test('reviews and imports a driver-only report through the shared wizard', function () {
    Livewire::test(AnalizarExcelFlota::class)->set('kind', 'drivers')->set('file', fleetTestUpload([['Ana', '123']], ['Nombre', 'Documento']))
        ->call('analyze')->set('mapping', ['driver.name', 'document'])->call('prepareImport')->assertHasNoErrors()->assertSet('importKind', 'drivers')
        ->assertSee('3. Revisa los conductores')->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()->assertSet('importResult.drivers_created', 1);
    $this->assertDatabaseCount('trucks', 0);
});

test('manual registration supports either role without truck and reuses a driver as owner', function () {
    Livewire::test(CreateFleetPerson::class)->fillForm(['name' => 'Ana', 'is_driver' => true, 'is_owner' => false])->call('create')->assertHasNoFormErrors();
    $person = FleetPerson::first();
    Livewire::test(ListOwners::class)->callAction('reuseDriver', ['person_id' => $person->id])->assertHasNoActionErrors();
    expect($person->fresh()->is_owner)->toBeTrue()->and($person->fresh()->is_driver)->toBeTrue();
    Livewire::test(CreateOwner::class)->fillForm(['name' => 'Luis', 'is_owner' => true, 'is_driver' => false])->call('create')->assertHasNoFormErrors();
    $this->assertDatabaseCount('fleet_people', 2);
    $this->assertDatabaseCount('trucks', 0);
});

test('wizard shows current and proposed driver and requires individual approval', function () {
    $truck = Truck::factory()->create(['plate' => 'A-1', 'conductor_name' => 'Actual']);
    Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['A-1', 'Propuesto', '27/10/2026', '10:00']], ['Placa', 'Conductor', 'Fecha', 'Hora']))
        ->call('analyze')->set('mapping', ['truck.plate', 'driver.name', 'date', 'time'])->call('prepareImport')
        ->assertHasNoErrors()->assertSee('Cambios de conductor: 1')->assertSee('Actual')->assertSee('Propuesto')->assertSet('updateChoices.0.approve', false)
        ->set('updateChoices.0.approve', true)->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()->assertSet('importResult.trucks_updated', 1);
    expect($truck->fresh()->conductor_name)->toBe('Propuesto');
});

test('same document with different names is excluded for manual identity review', function () {
    $prepared = app(DriverExcelImport::class)->prepare(fleetTestUpload([['Ana', '123'], ['Luis', '123']], ['Conductor', 'Documento']), ['driver' => 0, 'document' => 1], 'Activos', 1);
    expect($prepared['preview']['drivers'])->toBe([])->and($prepared['preview']['invalid'])->toHaveCount(1);
});
