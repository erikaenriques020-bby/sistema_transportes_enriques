<?php

namespace App\Services;

use App\Models\FleetImport;
use App\Models\GpsImportRow;
use App\Models\GpsPosition;
use App\Models\Truck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GpsExcelImport
{
    public function prepare(UploadedFile $file, array $fields, string $sheet, int $headerRow): FleetImport
    {
        Validator::make(['file' => $file, 'fields' => $fields, 'sheet' => $sheet, 'header' => $headerRow], [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:30720'],
            'fields' => ['required', 'array:'.implode(',', array_keys(LocationImportSchema::fields('gps')))],
            'fields.*' => ['nullable', 'integer', 'min:0'],
            'fields.plate' => ['required', 'integer', 'min:0'],
            'fields.latitude' => ['required', 'integer', 'min:0'],
            'fields.longitude' => ['required', 'integer', 'min:0'],
            'fields.datetime' => ['nullable', 'integer', 'min:0'],
            'fields.date' => ['required_without:fields.datetime', 'nullable', 'integer', 'min:0'],
            'fields.time' => ['required_without:fields.datetime', 'nullable', 'integer', 'min:0'],
            'sheet' => ['required', 'string'], 'header' => ['required', 'integer', 'min:1'],
        ])->validate();
        $fields = array_filter($fields, fn ($value): bool => $value !== null && $value !== '');
        if (count($fields) !== count(array_unique($fields)) || (isset($fields['datetime']) && (isset($fields['date']) || isset($fields['time'])))) {
            throw ValidationException::withMessages(['mapping' => 'No repitas columnas. Usa Fecha y Hora separadas o una sola columna Fecha y hora.']);
        }
        $sha = hash_file('sha256', $file->getRealPath());
        $sourcePath = 'fleet-imports/'.$sha.'.xlsx';
        if (! Storage::disk('local')->putFileAs('fleet-imports', $file, $sha.'.xlsx')) {
            throw ValidationException::withMessages(['file' => 'No se pudo conservar el Excel original.']);
        }

        return DB::transaction(function () use ($file, $fields, $sheet, $headerRow, $sha, $sourcePath): FleetImport {
            $batch = FleetImport::firstOrCreate(['sha256' => $sha, 'kind' => 'gps'], ['original_name' => $file->getClientOriginalName(), 'source_path' => $sourcePath, 'preview' => [], 'user_id' => auth()->id()]);
            $batch = FleetImport::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'completed') {
                return $batch;
            }
            $batch->touch();
            GpsImportRow::where('fleet_import_id', $batch->id)->delete();
            $trucks = Truck::get(['id', 'plate'])->keyBy(fn (Truck $truck): string => Truck::normalizePlate($truck->plate));
            $seen = [];
            $conflicts = [];
            $buffer = [];
            $headers = [];
            foreach ((new XlsxReportReader)->rows($file->getRealPath(), $sheet) as $number => $row) {
                if ($number === $headerRow) {
                    $headers = $row;
                }
                if ($number <= $headerRow || ! array_filter($row, fn (string $value): bool => $value !== '')) {
                    continue;
                }
                $plate = Truck::normalizePlate($row[$fields['plate']] ?? '');
                $at = ReportObservationTime::parse($row, $fields);
                $lat = LocationImportSchema::coordinate($row[$fields['latitude']] ?? '', 90);
                $lng = LocationImportSchema::coordinate($row[$fields['longitude']] ?? '', 180);
                $truck = $trucks->get($plate);
                $status = 'new';
                $reason = null;
                if ($plate === '' || mb_strlen($plate) > 20 || $at === null || $lat === null || $lng === null) {
                    $status = 'invalid';
                    $reason = match (true) {
                        $plate === '' || mb_strlen($plate) > 20 => 'Placa vacía o mayor de 20 caracteres.',
                        $at === null => 'Fecha u hora vacía o inválida. Usa fecha día/mes/año o año-mes-día.',
                        default => 'Coordenada vacía o inválida: latitud entre -90 y 90; longitud entre -180 y 180.',
                    };
                } elseif (! $truck) {
                    $status = 'unknown_plate';
                    $reason = 'Placa no registrada en Camiones. Regístrala y vuelve a analizar; esta carga no crea camiones.';
                } else {
                    $key = $truck->id.'|'.$at;
                    $coordinates = $lat.'|'.$lng;
                    if (isset($seen[$key])) {
                        if ($seen[$key] === $coordinates) {
                            $status = 'duplicate_file';
                            $reason = 'Posición repetida dentro del Excel.';
                        } else {
                            $conflicts[$key] = [$truck->id, $at];
                            $status = 'conflict_file';
                            $reason = 'La misma placa y fecha/hora presenta coordenadas diferentes. Se omiten todas las filas de ese instante.';
                        }
                    } else {
                        $seen[$key] = $coordinates;
                    }
                }
                $details = [];
                foreach (LocationImportSchema::GPS_DETAILS as $field => $definition) {
                    if (isset($fields[$field])) {
                        $details[$field] = $row[$fields[$field]] ?? '';
                    }
                }
                $source = [];
                foreach ($row as $index => $value) {
                    $source[($index + 1).':'.($headers[$index] ?? 'Sin encabezado')] = $value;
                }
                $buffer[] = ['fleet_import_id' => $batch->id, 'row_number' => $number, 'plate' => mb_substr($plate, 0, 255) ?: null, 'truck_id' => $truck?->id, 'observed_at' => $at, 'latitude' => $lat, 'longitude' => $lng, 'status' => $status, 'reason' => $reason, 'payload' => json_encode(['details' => $details, 'source' => $source], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
                if (count($buffer) >= 500) {
                    GpsImportRow::insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                GpsImportRow::insert($buffer);
            }
            foreach (array_chunk(array_values($conflicts), 100) as $chunk) {
                GpsImportRow::where('fleet_import_id', $batch->id)->whereIn('status', ['new', 'duplicate_file', 'conflict_file'])->where(function ($query) use ($chunk): void {
                    foreach ($chunk as [$truckId, $at]) {
                        $query->orWhere(fn ($pair) => $pair->where('truck_id', $truckId)->where('observed_at', $at));
                    }
                })->update(['status' => 'conflict_file', 'reason' => 'La misma placa y fecha/hora presenta coordenadas diferentes. Se omiten todas las filas de ese instante.']);
            }
            $this->classifyExisting($batch->id, false);
            $rows = GpsImportRow::where('fleet_import_id', $batch->id);
            $counts = (clone $rows)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count): int => (int) $count)->all();
            $new = (clone $rows)->where('status', 'new');
            $batch->update(['preview' => ['version' => 2, 'fields' => $fields, 'sheet' => $sheet, 'header_row' => $headerRow, 'counts' => $counts, 'rows' => array_sum($counts), 'trucks' => (clone $new)->distinct()->count('truck_id'), 'from' => (clone $new)->min('observed_at'), 'to' => (clone $new)->max('observed_at'), 'unknown_plates' => (clone $rows)->where('status', 'unknown_plate')->distinct()->count('plate')]]);

            return $batch->refresh();
        });
    }

    public function confirm(int $importId, string $fingerprint): FleetImport
    {
        return DB::transaction(function () use ($importId, $fingerprint): FleetImport {
            $batch = FleetImport::lockForUpdate()->where('kind', 'gps')->findOrFail($importId);
            if ($batch->status === 'completed') {
                return $batch;
            }
            $batch->touch();
            if (($batch->preview['version'] ?? null) !== 2 || ! hash_equals(FleetExcelImport::fingerprint($batch), $fingerprint)
                || ! Storage::disk('local')->exists($batch->source_path)
                || ! hash_equals($batch->sha256, hash_file('sha256', Storage::disk('local')->path($batch->source_path)))) {
                throw ValidationException::withMessages(['import' => 'La revisión o el archivo cambió. Vuelve a analizar el Excel antes de guardar.']);
            }
            if (GpsImportRow::where('fleet_import_id', $batch->id)->where('status', 'unknown_plate')->exists()) {
                throw ValidationException::withMessages(['import' => 'Hay placas sin registrar. Regístralas en Camiones y vuelve a analizar antes de confirmar esta carga.']);
            }
            $skipped = $this->classifyExisting($batch->id, true);
            $trucks = Truck::lockForUpdate()->get(['id', 'plate'])->keyBy('id');
            $created = 0;
            $ids = [];
            $lastDate = null;
            $mapTruckId = null;
            GpsImportRow::where('fleet_import_id', $batch->id)->where('status', 'new')->chunkById(500, function ($rows) use ($batch, $trucks, &$created, &$ids, &$lastDate, &$mapTruckId): void {
                $records = [];
                foreach ($rows as $row) {
                    $truck = $trucks->get($row->truck_id);
                    if (! $truck || Truck::normalizePlate($truck->plate) !== $row->plate) {
                        throw ValidationException::withMessages(['import' => 'Una placa cambió o fue eliminada después de revisar. Vuelve a analizar.']);
                    }
                    $records[] = ['truck_id' => $truck->id, 'observed_at' => $row->observed_at->format('Y-m-d H:i:s'), 'latitude' => $row->latitude, 'longitude' => $row->longitude, 'fleet_import_id' => $batch->id, 'source_row' => $row->row_number, 'details' => json_encode($row->payload['details'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'source_metadata' => json_encode($row->payload['source'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()];
                    $ids[$truck->id] = true;
                    $day = $row->observed_at->format('Y-m-d');
                    if ($lastDate === null || $day > $lastDate) {
                        $lastDate = $day;
                        $mapTruckId = $truck->id;
                    }
                }
                GpsPosition::insert($records);
                $created += count($records);
                GpsImportRow::whereIn('id', $rows->pluck('id'))->update(['status' => 'imported']);
            });
            $batch->update(['status' => 'completed', 'imported_at' => now(), 'decisions' => ['policy' => 'insert_new_only; omit_invalid_conflicting; require_known_plates; preserve_fleet'], 'result' => ['positions_created' => $created, 'trucks_affected' => count($ids), 'concurrent_duplicates' => $skipped, 'positions_total' => GpsPosition::count(), 'first_truck_id' => $mapTruckId, 'last_date' => $lastDate]]);

            return $batch->refresh();
        });
    }

    private function classifyExisting(int $batchId, bool $confirming): int
    {
        $skipped = 0;
        DB::table('gps_import_rows as r')->join('gps_positions as p', fn ($join) => $join->on('p.truck_id', '=', 'r.truck_id')->on('p.observed_at', '=', 'r.observed_at'))
            ->where('r.fleet_import_id', $batchId)->where('r.status', 'new')
            ->select('r.id', 'r.latitude', 'r.longitude', 'p.latitude as stored_latitude', 'p.longitude as stored_longitude')
            ->chunkById(500, function ($rows) use ($confirming, &$skipped): void {
                $same = [];
                $conflicts = [];
                foreach ($rows as $row) {
                    if (self::canonical($row->latitude) === self::canonical($row->stored_latitude) && self::canonical($row->longitude) === self::canonical($row->stored_longitude)) {
                        $same[] = $row->id;
                    } else {
                        $conflicts[] = $row->id;
                    }
                }
                if ($confirming && $conflicts) {
                    throw ValidationException::withMessages(['import' => 'Aparecieron coordenadas distintas después de la revisión. No se guardó esta carga. Vuelve a analizar para revisar los conflictos.']);
                }
                if ($same) {
                    GpsImportRow::whereIn('id', $same)->update(['status' => 'existing', 'reason' => 'Esta posición ya está registrada.']);
                    $skipped += count($same);
                }
                if ($conflicts) {
                    GpsImportRow::whereIn('id', $conflicts)->update(['status' => 'conflict_existing', 'reason' => 'Ya existe esta placa y fecha/hora con otras coordenadas. No se sobrescribe la posición guardada.']);
                }
            }, 'r.id', 'id');

        return $skipped;
    }

    private static function canonical(float|string $value): string
    {
        $rounded = round((float) $value, 7);

        return number_format($rounded == 0.0 ? 0.0 : $rounded, 7, '.', '');
    }
}
