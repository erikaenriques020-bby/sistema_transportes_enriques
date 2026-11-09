<?php

namespace App\Services;

use App\Models\FleetImport;
use App\Models\FleetPerson;
use App\Models\Truck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FleetExcelImport
{
    public static function normalize(string $value): string
    {
        return Truck::normalizePlate($value);
    }

    public static function fingerprint(FleetImport $import): string
    {
        return hash('sha256', json_encode($import->preview, JSON_THROW_ON_ERROR));
    }

    public function prepare(UploadedFile $file, array $fields, string $sheet, int $headerRow): array
    {
        Validator::make(['fields' => $fields, 'sheet' => $sheet, 'header' => $headerRow], [
            'fields.plate' => ['required', 'integer', 'min:0'],
            'fields.driver' => ['nullable', 'integer', 'min:0', 'different:fields.plate'],
            'fields.date' => ['nullable', 'integer', 'min:0'],
            'fields.time' => ['nullable', 'integer', 'min:0'],
            'fields.datetime' => ['nullable', 'integer', 'min:0'],
            'sheet' => ['required', 'string'], 'header' => ['required', 'integer', 'min:1'],
        ])->validate();
        $sha = hash_file('sha256', $file->getRealPath());
        $grouped = [];
        $invalid = [];
        $rows = 0;
        foreach ((new XlsxReportReader)->rows($file->getRealPath(), $sheet) as $number => $row) {
            if ($number <= $headerRow || ! array_filter($row, fn (string $value): bool => $value !== '')) {
                continue;
            }
            $rows++;
            $plate = self::normalize($row[$fields['plate']] ?? '');
            if ($plate === '' || mb_strlen($plate) > 20) {
                $invalid[] = ['row' => $number, 'reason' => 'Placa vacía o mayor de 20 caracteres.'];

                continue;
            }
            $name = isset($fields['driver']) ? preg_replace('/\s+/u', ' ', trim($row[$fields['driver']] ?? '')) : '';
            if (mb_strlen($name) > 255) {
                $invalid[] = ['row' => $number, 'reason' => 'Conductor demasiado largo: se dejó sin nombre.'];
                $name = '';
            }
            $grouped[$plate] ??= ['plate' => $plate, 'drivers' => [], 'occurrences' => 0, 'rows' => [], 'latest_at' => null, 'latest_names' => [], 'invalid_times' => false];
            $grouped[$plate]['occurrences']++;
            if (count($grouped[$plate]['rows']) < 5) {
                $grouped[$plate]['rows'][] = $number;
            }
            $hasTimeMapping = isset($fields['datetime']) || (isset($fields['date'], $fields['time']));
            $observedAt = ReportObservationTime::parse($row, $fields);
            if ($hasTimeMapping && $observedAt === null && $name !== '') {
                $grouped[$plate]['invalid_times'] = true;
            }
            if ($observedAt !== null) {
                if ($grouped[$plate]['latest_at'] === null || $observedAt > $grouped[$plate]['latest_at']) {
                    $grouped[$plate]['latest_at'] = $observedAt;
                    $grouped[$plate]['latest_names'] = [];
                }
                if ($observedAt === $grouped[$plate]['latest_at'] && $name !== '') {
                    $grouped[$plate]['latest_names'][self::normalize($name)] = $name;
                }
            }
            if ($name !== '') {
                $grouped[$plate]['drivers'][self::normalize($name)] = $name;
            }
        }
        $existing = Truck::all()->keyBy(fn (Truck $truck): string => self::normalize($truck->plate));
        $new = [];
        $existingCount = 0;
        $updates = [];
        $observations = [];
        $reviewIssues = [];
        $allNames = [];
        $newNames = [];
        $withoutDriver = 0;
        $multipleDrivers = [];
        $namePlates = [];
        $knownNames = Truck::whereNotNull('conductor_name')->pluck('conductor_name')->map(fn (string $name): string => self::normalize($name))->flip();
        foreach ($grouped as $candidate) {
            $withoutDriver += (int) (count($candidate['drivers']) === 0);
            foreach ($candidate['drivers'] as $key => $name) {
                $allNames[$key] = true;
                $namePlates[$key] ??= ['name' => $name, 'plates' => []];
                $namePlates[$key]['plates'][] = $candidate['plate'];
            }
            if (count($candidate['drivers']) > 1) {
                $multipleDrivers[] = ['plate' => $candidate['plate'], 'drivers' => array_values($candidate['drivers']), 'rows' => $candidate['rows']];
            }
            $candidate['drivers'] = array_values($candidate['drivers']);
            $candidate['latest_names'] = array_values($candidate['latest_names']);
            $candidate['conductor_name'] = $candidate['latest_at'] !== null
                ? (count($candidate['latest_names']) === 1 && ! $candidate['invalid_times'] ? $candidate['latest_names'][0] : null)
                : (count($candidate['drivers']) === 1 && ! $candidate['invalid_times'] ? $candidate['drivers'][0] : null);
            if ($existing->has($candidate['plate'])) {
                $existingCount++;
                $truck = $existing->get($candidate['plate']);
                if (! $candidate['conductor_name']) {
                    if ($candidate['drivers'] || $truck->conductor_name) {
                        $reviewIssues[] = ['plate' => $candidate['plate'], 'reason' => 'Última observación sin conductor, fechas inválidas o nombres empatados: se conserva el dato actual.'];
                    }

                    continue;
                }
                if (self::normalize((string) $truck->conductor_name) === self::normalize($candidate['conductor_name'])) {
                    if ($candidate['latest_at'] && (! $truck->driver_observed_at || $candidate['latest_at'] > $truck->driver_observed_at->format('Y-m-d H:i:s'))) {
                        $observations[] = ['truck_id' => $truck->id, 'name' => $truck->conductor_name, 'driver_id' => $truck->driver_id, 'recorded_at' => $truck->driver_recorded_at?->format('Y-m-d H:i:s'), 'latest_at' => $candidate['latest_at']];
                    }

                    continue;
                }
                if ($truck->driver_observed_at && $candidate['latest_at'] && $candidate['latest_at'] <= $truck->driver_observed_at->format('Y-m-d H:i:s')) {
                    $reviewIssues[] = ['plate' => $candidate['plate'], 'reason' => 'Reporte anterior o con la misma fecha GPS: no se propone reemplazar una asignación más reciente.'];

                    continue;
                }
                $candidate['truck_id'] = $truck->id;
                $candidate['previous_name'] = $truck->conductor_name;
                $candidate['snapshot'] = ['name' => $truck->conductor_name, 'driver_id' => $truck->driver_id, 'observed_at' => $truck->driver_observed_at?->format('Y-m-d H:i:s'), 'recorded_at' => $truck->driver_recorded_at?->format('Y-m-d H:i:s')];
                $candidate['date_warning'] = ! $candidate['latest_at'] ? 'Sin fecha/hora suficiente: confirmar manualmente.' : (! $truck->driver_observed_at ? 'El dato anterior no tiene fecha GPS: confirma que corresponde actualizarlo.' : null);
                $updates[] = $candidate;

                continue;
            }
            foreach ($candidate['drivers'] as $name) {
                if (! $knownNames->has(self::normalize($name))) {
                    $newNames[self::normalize($name)] = true;
                }
            }
            $new[] = $candidate;
        }
        $preview = ['version' => 2, 'trucks' => $new, 'updates' => $updates, 'observations' => $observations, 'review_issues' => $reviewIssues, 'existing_count' => $existingCount, 'unique_plates' => count($grouped),
            'plates_with_multiple_drivers' => $multipleDrivers,
            'names_on_multiple_plates' => array_values(array_filter($namePlates, fn (array $entry): bool => count($entry['plates']) > 1)),
            'driver_names' => count($allNames), 'trucks_with_driver' => count($grouped) - $withoutDriver,
            'trucks_without_driver' => $withoutDriver, 'new_driver_names' => count($newNames),
            'rows' => $rows, 'repeated_observations' => array_sum(array_column($grouped, 'occurrences')) - count($grouped),
            'invalid' => $invalid, 'sheet' => $sheet, 'header_row' => $headerRow, 'fields' => $fields];
        $sourcePath = 'fleet-imports/'.$sha.'.xlsx';
        if (! Storage::disk('local')->putFileAs('fleet-imports', $file, $sha.'.xlsx')) {
            throw ValidationException::withMessages(['import' => 'No se pudo conservar el archivo original.']);
        }

        return DB::transaction(function () use ($sha, $sourcePath, $file, $preview): array {
            $import = FleetImport::firstOrCreate(['sha256' => $sha, 'kind' => 'trucks'], ['original_name' => $file->getClientOriginalName(), 'source_path' => $sourcePath, 'preview' => $preview, 'user_id' => auth()->id()]);
            $import = FleetImport::lockForUpdate()->findOrFail($import->id);
            // A completed file remains immutable. Its plates are still compared to the current list.
            if ($import->status !== 'completed') {
                $import->update(['preview' => $preview]);
            }

            return ['import' => $import, 'preview' => $preview];
        });
    }

    public function confirm(int $importId, string $fingerprint, array $choices, array $updateChoices = []): FleetImport
    {
        return DB::transaction(function () use ($importId, $fingerprint, $choices, $updateChoices): FleetImport {
            $import = FleetImport::lockForUpdate()->where('kind', 'trucks')->findOrFail($importId);
            if ($import->status === 'completed') {
                return $import;
            }
            $import->touch();
            if (($import->preview['version'] ?? null) !== 2 || ! hash_equals(self::fingerprint($import), $fingerprint)) {
                throw ValidationException::withMessages(['import' => 'La revisión cambió. Genera nuevamente la vista previa.']);
            }
            if (! Storage::disk('local')->exists($import->source_path) || ! hash_equals($import->sha256, hash_file('sha256', Storage::disk('local')->path($import->source_path)))) {
                throw ValidationException::withMessages(['import' => 'El archivo original falta o cambió. Vuelve a cargarlo.']);
            }
            $existing = Truck::lockForUpdate()->pluck('plate')->map(fn (string $plate): string => self::normalize($plate))->flip();
            $knownNames = Truck::whereNotNull('conductor_name')->pluck('conductor_name')->map(fn (string $name): string => self::normalize($name))->flip();
            $createdNames = [];
            $result = ['trucks_created' => 0, 'trucks_updated' => 0, 'updates_skipped' => 0, 'existing_count' => $import->preview['existing_count'], 'truck_ids' => []];
            $decisions = [];
            foreach ($import->preview['observations'] ?? [] as $observation) {
                $truck = Truck::lockForUpdate()->find($observation['truck_id']);
                if ($truck && $truck->conductor_name === $observation['name'] && $truck->driver_id === $observation['driver_id'] && $truck->driver_recorded_at?->format('Y-m-d H:i:s') === $observation['recorded_at'] && (! $truck->driver_observed_at || $observation['latest_at'] > $truck->driver_observed_at->format('Y-m-d H:i:s'))) {
                    $truck->update(['driver_observed_at' => $observation['latest_at'], 'driver_source_import_id' => $import->id]);
                }
            }
            foreach ($import->preview['trucks'] as $index => $candidate) {
                if ($existing->has($candidate['plate'])) {
                    $result['existing_count']++;

                    continue;
                }
                $name = trim((string) ($choices[$index]['conductor_name'] ?? ''));
                if ($name !== '' && ! in_array($name, $candidate['drivers'], true)) {
                    throw ValidationException::withMessages(["truckChoices.$index.conductor_name" => 'Selecciona un nombre observado o deja el conductor vacío.']);
                }
                $truck = Truck::create(['plate' => $candidate['plate'], 'conductor_name' => $name ?: null, 'driver_observed_at' => $name !== '' && $name === $candidate['conductor_name'] ? ($candidate['latest_at'] ?? null) : null, 'driver_source_import_id' => $import->id, 'is_active' => true]);
                $existing->put($candidate['plate'], true);
                $result['trucks_created']++;
                if ($name !== '' && ! $knownNames->has(self::normalize($name))) {
                    $createdNames[self::normalize($name)] = true;
                }
                $result['truck_ids'][] = $truck->id;
                $this->ensureDriverName($name);
                $decisions[] = ['plate' => $candidate['plate'], 'conductor_name' => $name ?: null];
            }
            foreach ($import->preview['updates'] ?? [] as $index => $candidate) {
                if (! filter_var($updateChoices[$index]['approve'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $result['updates_skipped']++;

                    continue;
                }
                $truck = Truck::lockForUpdate()->findOrFail($candidate['truck_id']);
                $snapshot = ['name' => $truck->conductor_name, 'driver_id' => $truck->driver_id, 'observed_at' => $truck->driver_observed_at?->format('Y-m-d H:i:s'), 'recorded_at' => $truck->driver_recorded_at?->format('Y-m-d H:i:s')];
                if ($snapshot !== $candidate['snapshot']) {
                    throw ValidationException::withMessages(['import' => 'El camión '.$truck->plate.' cambió después de la revisión. Genera otra vista previa.']);
                }
                $this->ensureDriverName($candidate['conductor_name']);
                $truck->update(['conductor_name' => $candidate['conductor_name'], 'driver_observed_at' => $candidate['latest_at'], 'driver_source_import_id' => $import->id]);
                $result['trucks_updated']++;
                $decisions[] = ['plate' => $truck->plate, 'previous_name' => $candidate['previous_name'], 'conductor_name' => $candidate['conductor_name'], 'observed_at' => $candidate['latest_at'], 'approved' => true];
            }
            $result['driver_names_created'] = count($createdNames);
            $result['trucks_total'] = Truck::count();
            $result['driver_names_total'] = Truck::whereNotNull('conductor_name')->pluck('conductor_name')->filter(fn (string $name): bool => trim($name) !== '')->map(fn (string $name): string => self::normalize($name))->unique()->count();
            $result['trucks_without_driver'] = Truck::whereNull('conductor_name')->orWhere('conductor_name', '')->count();
            $import->update(['status' => 'completed', 'decisions' => $decisions, 'result' => $result, 'imported_at' => now()]);

            return $import->refresh();
        });
    }

    private function ensureDriverName(string $name): void
    {
        if (trim($name) === '') {
            return;
        }
        $key = self::normalize($name);
        if (! FleetPerson::get(['name'])->contains(fn (FleetPerson $person): bool => self::normalize($person->name) === $key)) {
            FleetPerson::create(['name' => $name, 'is_driver' => true, 'is_owner' => false, 'is_active' => true]);
        }
    }
}
