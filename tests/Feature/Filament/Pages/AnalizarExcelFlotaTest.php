<?php

use App\Filament\Pages\AnalizarExcelFlota;
use App\Filament\Resources\Trucks\Pages\CreateTruck;
use App\Filament\Resources\Trucks\Pages\EditTruck;
use App\Models\Truck;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('analyzes a two-column workbook and requires the user to choose Placa', function () {
    Storage::fake('local');

    Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana']]))->call('analyze')
        ->assertHasNoErrors()->assertSet('analysis.data_rows', 1)->call('prepareImport')->assertHasErrors('mapping');

    $this->assertDatabaseCount('trucks', 0);
});

test('prepares new plates and requires confirmation before registering', function () {
    Storage::fake('local');
    $component = Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana'], ['XYZ-2', '']]))->call('analyze')->set('mapping', ['truck.plate', 'driver.name']);

    $component->call('prepareImport')->assertHasNoErrors()->assertSee('3. Revisa las placas nuevas');
    $this->assertDatabaseCount('trucks', 0);
    $component->call('confirmImport')->assertHasErrors('reviewConfirmed');
    $component->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()->assertSet('importResult.trucks_created', 2);
    $this->assertDatabaseHas('trucks', ['plate' => 'XYZ-2', 'conductor_name' => null]);
});

test('imports plates when no conductor column is selected', function () {
    Storage::fake('local');

    Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1']], ['Unidad']))->call('analyze')->set('mapping', ['truck.plate'])
        ->call('prepareImport')->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors();

    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => null]);
});

test('shows no new plates and hides existing rows from the preview', function () {
    Storage::fake('local');
    Truck::factory()->create(['plate' => 'ABC-1', 'conductor_name' => 'Actual']);

    Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Otro']]))->call('analyze')->set('mapping', ['truck.plate', 'driver.name'])->call('prepareImport')
        ->assertSet('preview.trucks', [])->assertSee('No se encontraron placas nuevas.');
});

test('changing mapping or file invalidates the review and cancellation preserves the list', function () {
    Storage::fake('local');
    $component = Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana']]))->call('analyze')->set('mapping', ['truck.plate', 'driver.name'])->call('prepareImport');

    $component->set('mapping.1', 'discard')->assertSet('preview', null)->call('prepareImport')->call('cancelImport')->assertSet('preview', null);
    $component->set('file', fleetTestUpload([['XYZ-2', 'Luis']]))->assertSet('analysis', null)->assertSet('mapping', []);
    $this->assertDatabaseCount('trucks', 0);
});

test('creates a manual truck using only normalized plate and rejects a duplicate', function () {
    Livewire::test(CreateTruck::class)->fillForm(['plate' => ' abc-1 ', 'conductor_name' => null])->call('create')->assertHasNoFormErrors();

    Livewire::test(CreateTruck::class)->fillForm(['plate' => 'abc-1'])->call('create')->assertHasFormErrors(['plate' => 'unique']);

    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'owner_id' => null, 'conductor_name' => null, 'is_active' => true]);
    $this->assertDatabaseCount('trucks', 1);
});

test('renders independent conductors and owners plus the report of GPS names', function () {
    $user = User::factory()->create(['user_type' => 'administrator']);
    Truck::factory()->create(['plate' => 'ABC-1', 'conductor_name' => 'Ana']);
    Truck::factory()->create(['plate' => 'XYZ-2', 'conductor_name' => 'Ana']);

    $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Camiones registrados')->assertDontSee('Dueños y choferes')->assertDontSee('Programaciones');
    $this->get('/admin/conductores')->assertOk()->assertSee('Cargar Excel de conductores');
    $this->get('/admin/owners')->assertOk()->assertSee('Un conductor también es dueño');
    $this->get('/admin/conductores-resumen')->assertOk()->assertSee('Ana')->assertSee('ABC-1, XYZ-2');
    $this->get('/admin/cargas-excel')->assertOk()->assertSee('Todavía no hay cargas de Excel.');
    $this->get('/admin/trucks')->assertOk()->assertSee('Conductor')->assertDontSee('Activo i-monnet')->assertDontSee('Dueño del camión');
});

test('rejects an invalid workbook with an understandable error and clears the previous analysis', function () {
    Storage::fake('local');
    $component = Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana']]))->call('analyze');

    $component->set('file', UploadedFile::fake()->createWithContent('invalido.xlsx', 'not a workbook'))
        ->call('analyze')->assertHasErrors('file')->assertSet('analysis', null)->assertSet('preview', null);

    $this->assertDatabaseCount('trucks', 0);
});

test('does not accept two columns assigned to Placa', function () {
    Storage::fake('local');

    Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana']]))->call('analyze')
        ->set('mapping', ['truck.plate', 'truck.plate'])->call('prepareImport')->assertHasErrors('mapping');

    $this->assertDatabaseCount('trucks', 0);
});

test('opens review windows, paginates without losing decisions and opens a success window', function () {
    Storage::fake('local');
    $rows = [];
    for ($number = 1; $number <= 14; $number++) {
        $rows[] = ['TEST-'.$number, $number === 14 ? '' : 'Chofer '.$number];
    }
    $component = Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload($rows))->call('analyze')
        ->assertDispatched('open-modal', id: 'fleet-review')->assertSet('wizardStep', 2)
        ->set('mapping', ['truck.plate', 'driver.name'])->call('prepareImport')->assertSet('wizardStep', 3);

    $component->call('nextReviewPage')->assertSet('reviewPage', 2)->assertSet('truckChoices.0.conductor_name', 'Chofer 1');
    $component->call('nextReviewPage')->call('nextReviewPage')->assertSet('reviewPage', 3)
        ->assertSee('Sin conductor informado · se registrará igualmente');
    $component->call('previousReviewPage')->assertSet('reviewPage', 2)
        ->set('reviewConfirmed', true)->call('confirmImport')->assertHasNoErrors()
        ->assertDispatched('open-modal', id: 'fleet-success')->assertSet('importResult.trucks_total', 14);

    $this->assertDatabaseHas('trucks', ['plate' => 'TEST-14', 'conductor_name' => null]);
});

test('allows adding a conductor manually to an already registered truck without creating another truck', function () {
    $truck = Truck::factory()->create(['plate' => 'SIN-1', 'conductor_name' => null]);

    Livewire::test(EditTruck::class, ['record' => $truck->getRouteKey()])
        ->fillForm(['plate' => 'SIN-1', 'conductor_name' => 'Juanito'])->call('save')->assertHasNoFormErrors();

    $this->assertDatabaseHas('trucks', ['id' => $truck->id, 'plate' => 'SIN-1', 'conductor_name' => 'Juanito']);
    $this->assertDatabaseCount('trucks', 1);
});

test('opens a separate window for observed relationship warnings without modifying assignments', function () {
    Storage::fake('local');
    $component = Livewire::test(AnalizarExcelFlota::class)->set('file', fleetTestUpload([['ABC-1', 'Ana'], ['ABC-1', 'Luis'], ['XYZ-2', 'Ana']]))
        ->call('analyze')->set('mapping', ['truck.plate', 'driver.name'])->call('prepareImport');

    $component->assertSee('Ver casos detectados')->call('openRelationships')
        ->assertDispatched('open-modal', id: 'fleet-relationships')
        ->call('openReview')->assertDispatched('open-modal', id: 'fleet-review');

    $this->assertDatabaseCount('trucks', 0);
});
