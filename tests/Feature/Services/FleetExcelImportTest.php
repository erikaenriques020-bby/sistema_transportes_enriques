<?php

use App\Models\Truck;
use App\Services\FleetExcelImport;
use App\Services\XlsxReportReader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function simpleImport(array $rows, ?array $fields = null): array
{
    return app(FleetExcelImport::class)->prepare(fleetTestUpload($rows), $fields ?? ['plate' => 0, 'driver' => 1], 'Activos', 1);
}

test('groups repeated plates and conductor names before review without writing trucks', function () {
    Storage::fake('local');

    $prepared = simpleImport([[' abc-1 ', ' Ana   Prueba '], ['ABC-1', 'ANA PRUEBA'], ['ABC-1', 'ANA PRUEBA'], ['XYZ-2', '']]);

    expect($prepared['preview']['trucks'])->toHaveCount(2);
    expect($prepared['preview']['repeated_observations'])->toBe(2);
    expect($prepared['preview']['trucks'][0]['drivers'])->toHaveCount(1);
    expect($prepared['preview']['trucks'][1]['conductor_name'])->toBeNull();
    Storage::disk('local')->assertExists($prepared['import']->source_path);
    $this->assertDatabaseCount('trucks', 0);
});

test('saves new plates with optional conductor and an independent driver roster without assuming identity links', function () {
    Storage::fake('local');
    $prepared = simpleImport([['ABC-1', 'Ana Prueba'], ['XYZ-2', '']]);
    $import = $prepared['import'];

    $completed = app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana Prueba'], ['conductor_name' => '']]);

    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => 'Ana Prueba', 'owner_id' => null, 'driver_id' => null, 'is_active' => true]);
    $this->assertDatabaseHas('trucks', ['plate' => 'XYZ-2', 'conductor_name' => null]);
    $this->assertDatabaseCount('fleet_people', 1);
    $this->assertDatabaseCount('truck_driver_assignments', 0);
    expect($completed->result['trucks_created'])->toBe(2);
});

test('accepts a file without a conductor column', function () {
    Storage::fake('local');
    $file = fleetTestUpload([['ABC-1']], ['Unidad']);
    $prepared = app(FleetExcelImport::class)->prepare($file, ['plate' => 0], 'Activos', 1);

    $import = $prepared['import'];
    app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), []);

    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => null]);
});

test('shows only the new plates from a later file and preserves existing conductors', function () {
    Storage::fake('local');
    Truck::factory()->create(['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);

    $prepared = simpleImport([['abc-1', 'Otro nombre'], ['NEW-2', 'Ana'], ['NEW-2', 'Ana']]);
    $import = $prepared['import'];
    app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana']]);

    expect(array_column($prepared['preview']['trucks'], 'plate'))->toBe(['NEW-2']);
    expect($prepared['preview']['existing_count'])->toBe(1);
    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);
    $this->assertDatabaseCount('trucks', 2);
});

test('finds no new plates in another file containing only existing plates', function () {
    Storage::fake('local');
    Truck::factory()->create(['plate' => 'ABC-1']);

    $prepared = simpleImport([[' abc-1 ', ''], ['ABC-1', 'Ana']]);

    expect($prepared['preview']['trucks'])->toBe([]);
    expect($prepared['preview']['existing_count'])->toBe(1);
    $this->assertDatabaseCount('trucks', 1);
});

test('does not duplicate records on double confirmation or repeated file upload', function () {
    Storage::fake('local');
    $file = fleetTestUpload([['ABC-1', 'Ana']]);
    $service = app(FleetExcelImport::class);
    $prepared = $service->prepare($file, ['plate' => 0, 'driver' => 1], 'Activos', 1);
    $import = $prepared['import'];
    $service->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana']]);

    $service->confirm($import->id, FleetExcelImport::fingerprint($import), []);
    $repeated = $service->prepare($file, ['plate' => 0, 'driver' => 1], 'Activos', 1);

    expect($repeated['preview']['trucks'])->toBe([]);
    $this->assertDatabaseCount('fleet_imports', 1);
    $this->assertDatabaseCount('trucks', 1);
});

test('requires review for multiple conductor names without guessing the latest', function () {
    Storage::fake('local');

    $prepared = simpleImport([['ABC-1', 'Ana'], ['ABC-1', 'Luis'], ['ABC-1', '']]);

    expect($prepared['preview']['trucks'][0]['drivers'])->toBe(['Ana', 'Luis']);
    expect($prepared['preview']['trucks'][0]['conductor_name'])->toBeNull();
    $import = $prepared['import'];
    app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Luis']]);
    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => 'Luis']);
});

test('allows leaving an ambiguous conductor blank', function () {
    Storage::fake('local');
    $prepared = simpleImport([['ABC-1', 'Ana'], ['ABC-1', 'Luis']]);
    $import = $prepared['import'];

    app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => '']]);

    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => null]);
});

test('keeps row references for missing plates and still accepts a plate with blank conductor', function () {
    Storage::fake('local');

    $prepared = simpleImport([['', 'Ana'], ['ABC-1', ''], [str_repeat('A', 21), '']]);

    expect(array_column($prepared['preview']['invalid'], 'row'))->toBe([2, 4]);
    expect($prepared['preview']['trucks'])->toHaveCount(1);
});

test('rechecks plates saved by another user after the preview and skips them', function () {
    Storage::fake('local');
    $prepared = simpleImport([['ABC-1', 'Ana']]);
    $import = $prepared['import'];
    Truck::factory()->create(['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);

    $completed = app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana']]);

    expect($completed->result['trucks_created'])->toBe(0);
    $this->assertDatabaseCount('trucks', 1);
    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);
});

test('rolls back the whole import if a conductor choice was not present in the source', function () {
    Storage::fake('local');
    $prepared = simpleImport([['ABC-1', 'Ana'], ['XYZ-2', 'Luis']]);
    $import = $prepared['import'];

    expect(fn () => app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana'], ['conductor_name' => 'Inventado']]))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('trucks', 0);
    $this->assertDatabaseHas('fleet_imports', ['id' => $import->id, 'status' => 'pending']);
});

test('rejects a stale preview and a changed original file', function () {
    Storage::fake('local');
    $prepared = simpleImport([['ABC-1', 'Ana']]);
    $import = $prepared['import'];
    $service = app(FleetExcelImport::class);

    expect(fn () => $service->confirm($import->id, 'stale', []))->toThrow(ValidationException::class);
    Storage::disk('local')->put($import->source_path, 'changed');
    expect(fn () => $service->confirm($import->id, FleetExcelImport::fingerprint($import), []))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('trucks', 0);
});

test('reads metadata and shared strings through OpenSpout without requiring Activo', function () {
    $file = fleetTestUpload([['ABC-1', 'Ana'], ['XYZ-2', '']], ['Placa', 'Conductor'], 12, true);

    $inspection = app(XlsxReportReader::class)->inspect($file->getRealPath());

    expect($inspection['header_row'])->toBe(12);
    expect($inspection['data_rows'])->toBe(2);
    expect($inspection['columns'][1]['empty'])->toBe(1);
});

test('accepts arbitrary header names for manual mapping', function () {
    $file = fleetTestUpload([['ABC-1', 'Ana']], ['Unidad', 'Operador']);

    $inspection = app(XlsxReportReader::class)->inspect($file->getRealPath());

    expect(array_column($inspection['columns'], 'name'))->toBe(['Unidad', 'Operador']);
});

test('counts trucks independently from distinct driver names including trucks without conductor', function () {
    Storage::fake('local');

    $prepared = simpleImport([['ABC-1', 'Ana'], ['ABC-1', 'Ana'], ['XYZ-2', 'Ana'], ['SIN-3', ''], ['SIN-4', '']]);

    expect($prepared['preview']['unique_plates'])->toBe(4);
    expect($prepared['preview']['driver_names'])->toBe(1);
    expect($prepared['preview']['trucks_with_driver'])->toBe(2);
    expect($prepared['preview']['trucks_without_driver'])->toBe(2);
    $import = $prepared['import'];
    $completed = app(FleetExcelImport::class)->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Ana'], ['conductor_name' => 'Ana'], [], []]);
    expect($completed->result['trucks_total'])->toBe(4);
    expect($completed->result['driver_names_total'])->toBe(1);
    expect($completed->result['trucks_without_driver'])->toBe(2);
    $this->assertDatabaseCount('trucks', 4);
});

test('registers only Juanito and Ramon when an extended file includes all previous plates', function () {
    Storage::fake('local');
    $service = app(FleetExcelImport::class);
    $base = [['ABC-1', 'Ana'], ['SIN-2', '']];
    $first = simpleImport($base)['import'];
    $service->confirm($first->id, FleetExcelImport::fingerprint($first), [['conductor_name' => 'Ana'], []]);
    $repeat = simpleImport($base);
    expect($repeat['preview']['trucks'])->toBe([]);
    $extended = simpleImport([...$base, ['JUAN-3', 'Juanito'], ['RAM-4', 'Ramón'], ['JUAN-3', 'Juanito'], ['RAM-4', 'Ramón']]);

    expect(array_column($extended['preview']['trucks'], 'plate'))->toBe(['JUAN-3', 'RAM-4']);
    expect($extended['preview']['new_driver_names'])->toBe(2);
    $import = $extended['import'];
    $completed = $service->confirm($import->id, FleetExcelImport::fingerprint($import), [['conductor_name' => 'Juanito'], ['conductor_name' => 'Ramón']]);

    expect($completed->result['trucks_created'])->toBe(2);
    expect($completed->result['driver_names_created'])->toBe(2);
    expect($completed->result['trucks_total'])->toBe(4);
    $this->assertDatabaseHas('trucks', ['plate' => 'SIN-2', 'conductor_name' => null]);
    $this->assertDatabaseCount('trucks', 4);
});

test('warns about both relationship directions even for plates already in the fleet', function () {
    Storage::fake('local');
    Truck::factory()->create(['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);

    $prepared = simpleImport([['ABC-1', 'Ana'], ['ABC-1', 'Luis'], ['XYZ-2', 'Ana']]);

    expect($prepared['preview']['plates_with_multiple_drivers'])->toHaveCount(1);
    expect($prepared['preview']['plates_with_multiple_drivers'][0]['drivers'])->toBe(['Ana', 'Luis']);
    expect($prepared['preview']['names_on_multiple_plates'])->toHaveCount(1);
    expect($prepared['preview']['names_on_multiple_plates'][0])->toBe(['name' => 'Ana', 'plates' => ['ABC-1', 'XYZ-2']]);
    expect(array_column($prepared['preview']['trucks'], 'plate'))->toBe(['XYZ-2']);
    $this->assertDatabaseHas('trucks', ['plate' => 'ABC-1', 'conductor_name' => 'Nombre actual']);
});
