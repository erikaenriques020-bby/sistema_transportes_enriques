<?php

use App\Models\FleetImport;
use App\Models\FleetPerson;
use App\Models\Truck;
use App\Services\FleetExcelImport;
use App\Services\ReportObservationTime;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function datedFleet(array $rows): array
{
    return app(FleetExcelImport::class)->prepare(fleetTestUpload($rows, ['Placa', 'Conductor', 'Fecha', 'Hora']), ['plate' => 0, 'driver' => 1, 'date' => 2, 'time' => 3], 'Activos', 1);
}

function confirmDated(array $prepared, array $updates = []): FleetImport
{
    return app(FleetExcelImport::class)->confirm($prepared['import']->id, FleetExcelImport::fingerprint($prepared['import']), array_map(fn (array $truck): array => ['conductor_name' => $truck['conductor_name']], $prepared['preview']['trucks']), $updates);
}

test('supports optional relationships and one person as owner and driver on several plates', function () {
    $person = FleetPerson::factory()->create(['is_owner' => true, 'is_driver' => true]);
    $one = Truck::factory()->create(['owner_id' => $person->id, 'driver_id' => $person->id]);
    $two = Truck::factory()->create(['owner_id' => $person->id, 'driver_id' => $person->id]);
    $empty = Truck::factory()->create();
    FleetPerson::factory()->create(['is_owner' => true, 'is_driver' => false]);
    FleetPerson::factory()->create();
    expect($person->trucks()->count())->toBe(2)->and($person->drivenTrucks()->count())->toBe(2);
    expect($empty->owner_id)->toBeNull()->and($empty->driver_id)->toBeNull();
    expect($one->conductor_name)->toBe($person->name)->and($two->conductor_name)->toBe($person->name);
});

test('sorts observations by source date and time and changes only explicitly approved assignments', function () {
    Storage::fake('local');
    $owner = FleetPerson::factory()->create(['is_owner' => true]);
    $truck = Truck::factory()->create(['plate' => 'A-1', 'conductor_name' => 'Anterior', 'owner_id' => $owner->id]);
    Truck::factory()->create(['plate' => 'B-2', 'conductor_name' => 'Conservar']);
    $prepared = datedFleet([['A-1', 'Último', '27/10/2026', '12:00:00'], ['A-1', 'Viejo', '26/10/2026', '23:00:00'], ['B-2', 'Otro', '27/10/2026', '13:00:00']]);
    expect($prepared['preview']['updates'][0]['conductor_name'])->toBe('Último');
    confirmDated($prepared, [['approve' => true], ['approve' => false]]);
    expect($truck->fresh()->conductor_name)->toBe('Último')->and($truck->fresh()->owner_id)->toBe($owner->id);
    expect(Truck::where('plate', 'B-2')->first()->conductor_name)->toBe('Conservar');
    expect($truck->conductorChanges()->latest('id')->first()->old_name)->toBe('Anterior');
    expect($truck->fresh()->driver_observed_at->format('Y-m-d H:i:s'))->toBe('2026-10-27 12:00:00');
});

test('latest blank GPS conductor preserves the current name and reports the issue', function () {
    Storage::fake('local');
    $truck = Truck::factory()->create(['plate' => 'A-1', 'conductor_name' => 'Actual']);
    $prepared = datedFleet([['A-1', 'Viejo', '26/10/2026', '10:00'], ['A-1', '', '27/10/2026', '10:00']]);
    expect($prepared['preview']['updates'])->toBe([])->and($prepared['preview']['review_issues'])->toHaveCount(1);
    confirmDated($prepared);
    expect($truck->fresh()->conductor_name)->toBe('Actual');
});

test('equal latest times with different names and invalid dates require manual review', function () {
    Storage::fake('local');
    Truck::factory()->create(['plate' => 'A-1', 'conductor_name' => 'Actual']);
    $prepared = datedFleet([['A-1', 'Ana', '27/10/2026', '10:00'], ['A-1', 'Luis', '27/10/2026', '10:00'], ['N-2', 'Ana', '31/02/2026', '10:00']]);
    expect($prepared['preview']['updates'])->toBe([])->and($prepared['preview']['review_issues'])->toHaveCount(1);
    expect($prepared['preview']['trucks'][0]['conductor_name'])->toBeNull();
});

test('a repeated name advances the GPS reference and prevents an older file from replacing it', function () {
    Storage::fake('local');
    $initial = datedFleet([['A-1', 'Ana', '25/10/2026', '10:00']]);
    confirmDated($initial);
    $newer = datedFleet([['A-1', 'Ana', '27/10/2026', '10:00']]);
    confirmDated($newer);
    $older = datedFleet([['A-1', 'Luis', '26/10/2026', '10:00']]);
    expect($older['preview']['updates'])->toBe([])->and($older['preview']['review_issues'])->toHaveCount(1);
    expect(Truck::first()->driver_observed_at->format('Y-m-d'))->toBe('2026-10-27');
});

test('rejects approval when the assignment changed after preview and rolls back new trucks', function () {
    Storage::fake('local');
    $truck = Truck::factory()->create(['plate' => 'A-1', 'conductor_name' => 'Actual']);
    $prepared = datedFleet([['A-1', 'Ana', '27/10/2026', '10:00'], ['N-2', '', '27/10/2026', '10:00']]);
    $truck->update(['conductor_name' => 'Cambio manual']);
    expect(fn () => confirmDated($prepared, [['approve' => true]]))->toThrow(ValidationException::class);
    $this->assertDatabaseMissing('trucks', ['plate' => 'N-2']);
    expect($truck->fresh()->conductor_name)->toBe('Cambio manual');
});

test('a GPS name change removes the old identity link but preserves owner and complete history', function () {
    Storage::fake('local');
    $person = FleetPerson::factory()->create(['name' => 'Anterior', 'is_owner' => true]);
    $truck = Truck::factory()->create(['plate' => 'A-1', 'owner_id' => $person->id, 'driver_id' => $person->id]);
    $prepared = datedFleet([['A-1', 'Nuevo', '27/10/2026', '10:00']]);
    confirmDated($prepared, [['approve' => true]]);
    expect($truck->fresh()->driver_id)->toBeNull()->and($truck->fresh()->owner_id)->toBe($person->id);
    expect($truck->conductorChanges()->count())->toBe(2);
});

test('rejects a duplicate person without document and preserves linked role requirements', function () {
    $person = FleetPerson::factory()->create(['name' => 'Ana', 'document_number' => null, 'is_owner' => true]);
    expect(fn () => FleetPerson::factory()->create(['name' => ' ana ', 'document_number' => null]))->toThrow(ValidationException::class);
    Truck::factory()->create(['owner_id' => $person->id]);
    expect(fn () => $person->update(['is_owner' => false]))->toThrow(ValidationException::class);
});

test('parses native Excel date and time cells without confusing their reference dates', function () {
    expect(ReportObservationTime::parse(['2026-10-27 00:00:00', '1899-12-30 14:35:00'], ['date' => 0, 'time' => 1]))->toBe('2026-10-27 14:35:00');
});

test('renaming a verified driver updates linked trucks and retains the link and history', function () {
    $person = FleetPerson::factory()->create(['name' => 'Anterior']);
    $truck = Truck::factory()->create(['driver_id' => $person->id]);
    $person->update(['name' => 'Corregido']);
    expect($truck->fresh()->conductor_name)->toBe('Corregido')->and($truck->fresh()->driver_id)->toBe($person->id);
    expect($truck->conductorChanges()->latest('id')->first()->old_name)->toBe('Anterior');
});
