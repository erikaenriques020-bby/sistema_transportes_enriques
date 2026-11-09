<?php

use App\Models\FleetImport;
use App\Models\FleetPerson;
use App\Models\GpsImportRow;
use App\Models\GpsPosition;
use App\Models\Truck;
use App\Services\FleetExcelImport;
use App\Services\GpsExcelImport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function gpsUpload(array $rows): UploadedFile
{
    return fleetTestUpload($rows, ['Placa', 'Fecha', 'Hora', 'Latitud', 'Longitud']);
}

function prepareGps(array $rows): FleetImport
{
    return app(GpsExcelImport::class)->prepare(gpsUpload($rows), ['plate' => 0, 'date' => 1, 'time' => 2, 'latitude' => 3, 'longitude' => 4], 'Activos', 1);
}

function confirmGps(FleetImport $batch): FleetImport
{
    return app(GpsExcelImport::class)->confirm($batch->id, FleetExcelImport::fingerprint($batch));
}

test('stages all source rows without writing GPS positions and identifies repeated observations', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);

    $batch = prepareGps([[' gps-1 ', '27/10/2026', '09:00', '-16,50', '-68,10'], ['GPS-1', '27/10/2026', '09:00', -16.5, -68.1], ['GPS-1', '27/10/2026', '10:00', -16.5, -68.1]]);

    expect($batch->preview['counts'])->toMatchArray(['new' => 2, 'duplicate_file' => 1]);
    $this->assertDatabaseCount('gps_positions', 0);
    $this->assertDatabaseCount('gps_import_rows', 3);
    Storage::disk('local')->assertExists($batch->source_path);
});

test('stores only unique positions with source traceability without modifying owners or conductors', function () {
    $person = FleetPerson::factory()->create(['is_owner' => true]);
    $truck = Truck::factory()->create(['plate' => 'GPS-1', 'owner_id' => $person->id, 'driver_id' => $person->id]);
    $batch = prepareGps([['GPS-1', '27/10/2026', '10:00', -16.6, -68.2], ['GPS-1', '27/10/2026', '09:00', -16.5, -68.1], ['GPS-1', '27/10/2026', '09:00', -16.5, -68.1]]);

    $result = confirmGps($batch)->result;

    expect($result['positions_created'])->toBe(2);
    $this->assertDatabaseHas('gps_positions', ['truck_id' => $truck->id, 'observed_at' => '2026-10-27 10:00:00', 'fleet_import_id' => $batch->id, 'source_row' => 2]);
    expect($truck->fresh()->owner_id)->toBe($person->id);
    expect($truck->fresh()->driver_id)->toBe($person->id);
    expect($truck->fresh()->latestGpsPosition->observed_at->format('H:i'))->toBe('10:00');
});

test('confirmation and reupload of the exact same file are idempotent', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $file = gpsUpload([['GPS-1', '27/10/2026', '09:00', -16.5, -68.1]]);
    $service = app(GpsExcelImport::class);
    $fields = ['plate' => 0, 'date' => 1, 'time' => 2, 'latitude' => 3, 'longitude' => 4];
    $batch = $service->prepare($file, $fields, 'Activos', 1);

    confirmGps($batch);
    confirmGps($batch);
    $repeat = $service->prepare($file, $fields, 'Activos', 1);

    expect($repeat->status)->toBe('completed');
    $this->assertDatabaseCount('gps_positions', 1);
    $this->assertDatabaseCount('fleet_imports', 1);
});

test('later reports skip database duplicates and never overwrite conflicting coordinates', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1']);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 09:00:00', 'latitude' => -16.5, 'longitude' => -68.1]);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 10:00:00', 'latitude' => -16.6, 'longitude' => -68.2]);
    $batch = prepareGps([['GPS-1', '27/10/2026', '09:00', '-16,5000000', -68.1], ['GPS-1', '27/10/2026', '10:00', -17, -69], ['GPS-1', '27/10/2026', '11:00', -16.7, -68.3]]);

    expect($batch->preview['counts'])->toMatchArray(['existing' => 1, 'conflict_existing' => 1, 'new' => 1]);
    expect(confirmGps($batch)->result['positions_created'])->toBe(1);
    $this->assertDatabaseHas('gps_positions', ['truck_id' => $truck->id, 'observed_at' => '2026-10-27 10:00:00', 'latitude' => -16.6]);
});

test('omits every row of an internally conflicting instant regardless of input order', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $batch = prepareGps([['GPS-1', '27/10/2026', '09:00', -16, -68], ['GPS-1', '27/10/2026', '09:00', -17, -69], ['GPS-1', '27/10/2026', '09:00', -16, -68], ['GPS-1', '27/10/2026', '10:00', -16.5, -68.1]]);

    expect($batch->preview['counts'])->toMatchArray(['conflict_file' => 3, 'new' => 1]);
    expect(confirmGps($batch)->result['positions_created'])->toBe(1);
    $this->assertDatabaseMissing('gps_positions', ['observed_at' => '2026-10-27 09:00:00']);
});

test('reports invalid coordinates and dates instead of silently dropping source rows', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $batch = prepareGps([['GPS-1', '31/02/2026', '09:00', -16, -68], ['GPS-1', '27/10/2026', '09:00', 91, -68], ['GPS-1', '27/10/2026', '09:00', -16, '1,234.56'], ['', '27/10/2026', '09:00', -16, -68], ['GPS-1', '27/10/2026', '09:00', '', -68]]);

    expect($batch->preview['counts']['invalid'])->toBe(5);
    expect(GpsImportRow::orderBy('row_number')->pluck('row_number')->all())->toBe([2, 3, 4, 5, 6]);
    $this->assertDatabaseCount('gps_positions', 0);
});

test('requires unknown plates to be registered and reanalyzed before any positions are saved', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $rows = [['GPS-1', '27/10/2026', '09:00', -16, -68], ['NEW-2', '27/10/2026', '09:00', -17, -69]];
    $batch = prepareGps($rows);

    expect(fn () => confirmGps($batch))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('gps_positions', 0);
    Truck::factory()->create(['plate' => 'NEW-2']);
    $review = prepareGps($rows);
    expect($review->preview['counts']['new'])->toBe(2);
    expect(confirmGps($review)->result['positions_created'])->toBe(2);
});

test('skips an exact duplicate saved after review but rejects a newly conflicting position', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1']);
    $batch = prepareGps([['GPS-1', '27/10/2026', '09:00', -16, -68]]);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 09:00:00', 'latitude' => -16, 'longitude' => -68]);

    expect(confirmGps($batch)->result)->toMatchArray(['positions_created' => 0, 'concurrent_duplicates' => 1]);
    $next = prepareGps([['GPS-1', '27/10/2026', '10:00', -16, -68], ['GPS-1', '27/10/2026', '11:00', -16, -68]]);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 11:00:00', 'latitude' => -17, 'longitude' => -69]);
    expect(fn () => confirmGps($next))->toThrow(ValidationException::class);
    $this->assertDatabaseMissing('gps_positions', ['observed_at' => '2026-10-27 10:00:00']);
});

test('rejects stale reviews and changed originals before saving', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $batch = prepareGps([['GPS-1', '27/10/2026', '09:00', -16, -68]]);
    expect(fn () => app(GpsExcelImport::class)->confirm($batch->id, 'stale'))->toThrow(ValidationException::class);
    Storage::disk('local')->put($batch->source_path, 'changed');
    expect(fn () => confirmGps($batch))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('gps_positions', 0);
});

test('rolls back earlier chunks if a truck changes after review', function () {
    $one = Truck::factory()->create(['plate' => 'GPS-1']);
    $two = Truck::factory()->create(['plate' => 'GPS-2']);
    $rows = [];
    for ($i = 0; $i < 510; $i++) {
        $rows[] = ['GPS-1', '27/10/2026', sprintf('%02d:%02d:00', intdiv($i, 60), $i % 60), -16, -68];
    }
    $rows[] = ['GPS-2', '27/10/2026', '09:00', -16, -68];
    $batch = prepareGps($rows);
    $two->update(['plate' => 'CHANGED']);

    expect(fn () => confirmGps($batch))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('gps_positions', 0);
    expect(GpsImportRow::where('status', 'imported')->count())->toBe(0);
});

test('supports a combined timestamp column and native Excel date/time cells', function () {
    Truck::factory()->create(['plate' => 'GPS-1']);
    $batch = app(GpsExcelImport::class)->prepare(fleetTestUpload([['GPS-1', '2026-10-27 09:00:00', -16, -68]], ['Unidad', 'Instante', 'Norte', 'Este']), ['plate' => 0, 'datetime' => 1, 'latitude' => 2, 'longitude' => 3], 'Activos', 1);
    expect(confirmGps($batch)->result['positions_created'])->toBe(1);
    $nativeFile = gpsUpload([['GPS-1', new DateTimeImmutable('2026-10-27'), new DateTimeImmutable('1899-12-30 10:00:00'), -16, -68]]);
    $native = app(GpsExcelImport::class)->prepare($nativeFile, ['plate' => 0, 'date' => 1, 'time' => 2, 'latitude' => 3, 'longitude' => 4], 'Activos', 1);
    expect(GpsImportRow::where('fleet_import_id', $native->id)->first()->reason)->toBeNull();
    expect(confirmGps($native)->result['positions_created'])->toBe(1);
});
