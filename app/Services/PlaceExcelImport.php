<?php

namespace App\Services;

use App\Models\FleetImport;
use App\Models\GpsImportRow;
use App\Models\Place;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlaceExcelImport
{
    public function prepare(UploadedFile $file, array $fields, string $sheet, int $headerRow): FleetImport
    {
        Validator::make(['file' => $file, 'fields' => $fields, 'sheet' => $sheet, 'header' => $headerRow], [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:30720'],
            'fields' => ['required', 'array:'.implode(',', array_keys(LocationImportSchema::fields('places')))],
            'fields.*' => ['nullable', 'integer', 'min:0'],
            'fields.name' => ['required'], 'fields.latitude' => ['required'], 'fields.longitude' => ['required'],
            'sheet' => ['required', 'string'], 'header' => ['required', 'integer', 'min:1'],
        ])->validate();
        $fields = array_filter($fields, fn ($value): bool => $value !== '' && $value !== null);
        if (count($fields) !== count(array_unique($fields))) {
            throw ValidationException::withMessages(['mapping' => 'Cada variable debe usar una columna distinta.']);
        }
        $sha = hash_file('sha256', $file->getRealPath());
        $path = 'fleet-imports/'.$sha.'.xlsx';
        if (! Storage::disk('local')->putFileAs('fleet-imports', $file, $sha.'.xlsx')) {
            throw ValidationException::withMessages(['file' => 'No se pudo conservar el archivo original.']);
        }

        return DB::transaction(function () use ($file, $fields, $sheet, $headerRow, $sha, $path): FleetImport {
            $batch = FleetImport::firstOrCreate(['sha256' => $sha, 'kind' => 'places'], ['original_name' => $file->getClientOriginalName(), 'source_path' => $path, 'preview' => [], 'user_id' => auth()->id()]);
            $batch = FleetImport::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'completed') {
                return $batch;
            }
            $batch->touch();
            GpsImportRow::where('fleet_import_id', $batch->id)->delete();
            $existing = Place::get()->groupBy(fn (Place $place): string => Place::identityKey($place->name, $place->latitude, $place->longitude));
            $seen = [];
            $headers = [];
            foreach ((new XlsxReportReader)->rows($file->getRealPath(), $sheet) as $number => $row) {
                if ($number === $headerRow) {
                    $headers = $row;
                }
                if ($number <= $headerRow || ! array_filter($row, fn (string $value): bool => $value !== '')) {
                    continue;
                }
                $attributes = [];
                foreach ($fields as $field => $index) {
                    $attributes[$field] = trim($row[$index] ?? '') ?: null;
                }
                $attributes['name'] = preg_replace('/\s+/u', ' ', trim($attributes['name'] ?? ''));
                $lat = LocationImportSchema::coordinate($row[$fields['latitude']] ?? '', 90);
                $lng = LocationImportSchema::coordinate($row[$fields['longitude']] ?? '', 180);
                $attributes['latitude'] = $lat;
                $attributes['longitude'] = $lng;
                $validator = Validator::make($attributes, [
                    'name' => ['required', 'string', 'max:255'], 'latitude' => ['required'], 'longitude' => ['required'],
                    'place_group' => ['nullable', 'string', 'max:255'], 'icon' => ['nullable', 'string', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
                    'description' => ['nullable', 'string', 'max:65535'], 'address' => ['nullable', 'string', 'max:65535'],
                ]);
                $status = 'new';
                $reason = null;
                if ($validator->fails()) {
                    $status = 'invalid';
                    $reason = 'Nombre o datos inválidos. Coordenadas requeridas: latitud [-90,90], longitud [-180,180].';
                } else {
                    $key = Place::identityKey($attributes['name'], $lat, $lng);
                    if (isset($seen[$key])) {
                        $status = 'duplicate_file';
                        $reason = 'Lugar repetido dentro del archivo: mismo nombre y coordenadas.';
                    } elseif ($existing->has($key)) {
                        $status = 'existing';
                        $reason = 'Este nombre y coordenadas ya están en el catálogo. Se conservan sus datos actuales.';
                    }
                    $seen[$key] = true;
                }
                $source = [];
                foreach ($row as $index => $value) {
                    $source[($index + 1).':'.($headers[$index] ?? 'Sin encabezado')] = $value;
                }
                GpsImportRow::create(['fleet_import_id' => $batch->id, 'row_number' => $number, 'name' => mb_substr($attributes['name'], 0, 255) ?: null, 'latitude' => $lat, 'longitude' => $lng, 'status' => $status, 'reason' => $reason, 'payload' => ['attributes' => $attributes, 'source' => $source]]);
            }
            $rows = GpsImportRow::where('fleet_import_id', $batch->id);
            $counts = (clone $rows)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count): int => (int) $count)->all();
            $batch->update(['preview' => ['version' => 1, 'fields' => $fields, 'sheet' => $sheet, 'header_row' => $headerRow, 'counts' => $counts, 'rows' => array_sum($counts), 'places' => $counts['new'] ?? 0, 'trucks' => 0, 'unknown_plates' => 0, 'from' => null, 'to' => null]]);

            return $batch->refresh();
        });
    }

    public function confirm(int $importId, string $fingerprint): FleetImport
    {
        return DB::transaction(function () use ($importId, $fingerprint): FleetImport {
            $batch = FleetImport::lockForUpdate()->where('kind', 'places')->findOrFail($importId);
            if ($batch->status === 'completed') {
                return $batch;
            }
            $batch->touch();
            if (($batch->preview['version'] ?? null) !== 1 || ! hash_equals(FleetExcelImport::fingerprint($batch), $fingerprint)
                || ! Storage::disk('local')->exists($batch->source_path) || ! hash_equals($batch->sha256, hash_file('sha256', Storage::disk('local')->path($batch->source_path)))) {
                throw ValidationException::withMessages(['import' => 'El archivo o la revisión cambió. Vuelve a analizar antes de registrar lugares.']);
            }
            $created = 0;
            $skipped = 0;
            $known = Place::lockForUpdate()->get()->mapWithKeys(fn (Place $place): array => [Place::identityKey($place->name, $place->latitude, $place->longitude) => true]);
            foreach (GpsImportRow::where('fleet_import_id', $batch->id)->where('status', 'new')->orderBy('id')->cursor() as $row) {
                $data = $row->payload['attributes'];
                $key = Place::identityKey($data['name'], $data['latitude'], $data['longitude']);
                if ($known->has($key)) {
                    $skipped++;
                    $row->update(['status' => 'existing', 'reason' => 'Lugar registrado después de la revisión. No se duplica.']);

                    continue;
                }
                $place = Place::create($data);
                $row->update(['status' => 'imported', 'payload' => [...$row->payload, 'place_id' => $place->id]]);
                $known->put($key, true);
                $created++;
            }
            $batch->update(['status' => 'completed', 'imported_at' => now(), 'decisions' => ['policy' => 'only_new_name_and_coordinates; preserve_existing_catalog'], 'result' => ['places_created' => $created, 'places_total' => Place::count(), 'concurrent_duplicates' => $skipped]]);

            return $batch->refresh();
        });
    }
}
