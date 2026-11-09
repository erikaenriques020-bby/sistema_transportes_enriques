<?php

use App\Models\GpsPosition;
use App\Models\Place;
use App\Models\Truck;
use App\Services\FleetExcelImport;
use App\Services\GpsExcelImport;
use App\Services\LocationImportSchema;
use App\Services\PlaceExcelImport;
use App\Services\XlsxReportReader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => Storage::fake('local'));

test('reads complete GPS headers after metadata and separates reception from observation', function () {
    $truck = Truck::factory()->create(['plate' => 'GPS-1', 'conductor_name' => 'Actual']);
    $headers = ['Fecha', 'Hora', 'Activo', 'Conductor', 'Direccion', 'Orientacion', 'Velocidad', 'Evento', 'Latitud', 'Longitud', 'Odometro', 'Lugar mas cercano', 'Placa', 'Temperatura', 'Temperatura2', 'Ver mapa', 'Buffer', 'TimeStamp', 'Geocerca', 'Parametro futuro'];
    $file = fleetTestUpload([['26/10/2026', '09:00:03', '123', 'GPS conductor', 'Dirección', 'N', '45', 'Ubicación', '-16,5', '-68,1', '100', 'Centro', 'GPS-1', '', '', '=HYPERLINK("https://example.com","Mapa")', '0', '26/10/2026 10:30:00', 'Zona A', 'Conservado']], $headers, 12);
    $inspection = app(XlsxReportReader::class)->inspect($file->getRealPath());
    $mapping = LocationImportSchema::guess('gps', $inspection['columns']);
    expect($inspection['header_row'])->toBe(12)->and($inspection['sheets'])->toBe(['Activos'])->and($mapping['datetime'])->toBe('')->and($mapping['received_at'])->toBe('17');
    $batch = app(GpsExcelImport::class)->prepare($file, $mapping, 'Activos', 12);
    $this->assertDatabaseCount('gps_positions', 0);
    app(GpsExcelImport::class)->confirm($batch->id, FleetExcelImport::fingerprint($batch));
    $position = GpsPosition::firstOrFail();
    expect($position->observed_at->format('Y-m-d H:i:s'))->toBe('2026-10-26 09:00:03')->and($position->details['received_at'])->toBe('26/10/2026 10:30:00')->and($position->details['map_link'])->toContain('HYPERLINK')->and($position->source_metadata['20:Parametro futuro'])->toBe('Conservado')->and($truck->fresh()->conductor_name)->toBe('Actual');
});

test('respects unused columns and repairs legacy timestamp profiles', function () {
    $mapping = LocationImportSchema::guess('gps', [['index' => 0, 'name' => 'Conductor'], ['index' => 1, 'name' => 'TimeStamp']], ['0:Conductor' => null, '1:TimeStamp' => 'datetime']);
    expect($mapping['observed_driver'])->toBe('')->and($mapping['received_at'])->toBe('1')->and($mapping['datetime'])->toBe('');
});

test('imports places without merging distinct coordinates or overwriting existing data', function () {
    Place::factory()->create(['name' => 'Terminal', 'latitude' => -16.5, 'longitude' => -68.1, 'phone' => '123']);
    $file = fleetTestUpload([['Terminal', -16.5, -68.1, '999'], ['Terminal', -17, -69, '456'], [' terminal ', -17, -69, '777'], ['Otro', -17, -69, ''], ['Inválido', 91, -69, '']], ['Nombre del lugar', 'Latitud', 'Longitud', 'Telefono']);
    $inspection = app(XlsxReportReader::class)->inspect($file->getRealPath());
    expect(LocationImportSchema::suggestedTarget($inspection['columns']))->toBe('places');
    $service = app(PlaceExcelImport::class);
    $batch = $service->prepare($file, LocationImportSchema::guess('places', $inspection['columns']), 'Activos', 1);
    expect($batch->preview['counts'])->toMatchArray(['new' => 2, 'existing' => 1, 'duplicate_file' => 1, 'invalid' => 1]);
    $this->assertDatabaseCount('places', 1);
    expect($service->confirm($batch->id, FleetExcelImport::fingerprint($batch))->result['places_created'])->toBe(2);
    $this->assertDatabaseCount('places', 3);
    $this->assertDatabaseCount('gps_positions', 0);
    $this->assertDatabaseHas('places', ['name' => 'Terminal', 'latitude' => -16.5, 'phone' => '123']);
    $service->confirm($batch->id, 'completed');
    $this->assertDatabaseCount('places', 3);
});

test('blocks duplicate manual places while allowing another location with the same name', function () {
    Place::factory()->create(['name' => 'Terminal', 'latitude' => -16, 'longitude' => -68]);
    expect(fn () => Place::factory()->create(['name' => ' terminal ', 'latitude' => -16, 'longitude' => -68]))->toThrow(ValidationException::class);
    Place::factory()->create(['name' => 'Terminal', 'latitude' => -17, 'longitude' => -69]);
    $this->assertDatabaseCount('places', 2);
});

test('requires unchanged place review and skips places added after analysis', function () {
    $service = app(PlaceExcelImport::class);
    $batch = $service->prepare(fleetTestUpload([['Terminal', -16, -68]], ['Nombre', 'Latitud', 'Longitud']), ['name' => 0, 'latitude' => 1, 'longitude' => 2], 'Activos', 1);
    expect(fn () => $service->confirm($batch->id, 'stale'))->toThrow(ValidationException::class);
    Place::factory()->create(['name' => 'Terminal', 'latitude' => -16, 'longitude' => -68]);
    $result = $service->confirm($batch->id, FleetExcelImport::fingerprint($batch))->result;
    expect($result['places_created'])->toBe(0)->and($result['concurrent_duplicates'])->toBe(1);
    $this->assertDatabaseCount('places', 1);
});
