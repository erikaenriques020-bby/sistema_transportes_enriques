<?php

namespace App\Services;

use App\Models\FleetImport;
use App\Models\FleetPerson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DriverExcelImport
{
    public function prepare(UploadedFile $file, array $fields, string $sheet, int $headerRow): array
    {
        Validator::make($fields, ['driver' => ['required', 'integer', 'min:0'], 'document' => ['nullable', 'integer', 'min:0'], 'phone' => ['nullable', 'integer', 'min:0']])->validate();
        $groups = [];
        $invalid = [];
        $rows = 0;
        foreach ((new XlsxReportReader)->rows($file->getRealPath(), $sheet) as $number => $row) {
            if ($number <= $headerRow || ! array_filter($row)) {
                continue;
            }
            $rows++;
            $name = trim($row[$fields['driver']] ?? '');
            $document = isset($fields['document']) ? trim($row[$fields['document']] ?? '') : '';
            $phone = isset($fields['phone']) ? trim($row[$fields['phone']] ?? '') : '';
            if ($name === '' || mb_strlen($name) > 255 || mb_strlen($document) > 100 || mb_strlen($phone) > 255) {
                $invalid[] = ['row' => $number, 'reason' => 'Nombre vacío o campo demasiado largo.'];

                continue;
            }
            $key = $document !== '' ? 'doc:'.FleetExcelImport::normalize($document) : 'name:'.FleetExcelImport::normalize($name);
            $groups[$key] ??= ['name' => $name, 'document_number' => $document ?: null, 'phone' => $phone ?: null, 'rows' => []];
            if (FleetExcelImport::normalize($groups[$key]['name']) !== FleetExcelImport::normalize($name)) {
                $groups[$key]['conflicting_names'] = true;
            }
            if (count($groups[$key]['rows']) < 5) {
                $groups[$key]['rows'][] = $number;
            }
        }
        $people = FleetPerson::all();
        $candidates = [];
        $existing = 0;
        foreach ($groups as $person) {
            if ($person['conflicting_names'] ?? false) {
                $invalid[] = ['row' => $person['rows'][0], 'reason' => 'El mismo documento tiene varios nombres distintos. Verifica el documento antes de registrar.'];

                continue;
            }
            $matches = $people->filter(fn (FleetPerson $record): bool => ($person['document_number'] && FleetExcelImport::normalize((string) $record->document_number) === FleetExcelImport::normalize($person['document_number'])) || (! $person['document_number'] && FleetExcelImport::normalize($record->name) === FleetExcelImport::normalize($person['name'])));
            $nameMatches = $people->filter(fn (FleetPerson $record): bool => FleetExcelImport::normalize($record->name) === FleetExcelImport::normalize($person['name']));
            if ($matches->isNotEmpty()) {
                $existing++;
                if ($matches->count() > 1 || ! $matches->first()->is_driver) {
                    $invalid[] = ['row' => $person['rows'][0], 'reason' => 'Persona existente o nombre ambiguo: revisar sus roles desde Conductores/Dueños. No se crea otra ficha.'];
                }

                continue;
            }
            if ($nameMatches->isNotEmpty() && $nameMatches->contains(fn (FleetPerson $record): bool => blank($record->document_number))) {
                $invalid[] = ['row' => $person['rows'][0], 'reason' => 'Ya hay una persona con este nombre sin documento; verificar su identidad manualmente.'];

                continue;
            }
            $candidates[] = $person;
        }
        $preview = ['version' => 1, 'drivers' => $candidates, 'trucks' => [], 'updates' => [], 'rows' => $rows, 'existing_count' => $existing, 'unique_names' => count($groups), 'invalid' => $invalid];
        $sha = hash_file('sha256', $file->getRealPath());
        $path = 'fleet-imports/'.$sha.'.xlsx';
        if (! Storage::disk('local')->putFileAs('fleet-imports', $file, $sha.'.xlsx')) {
            throw ValidationException::withMessages(['import' => 'No se pudo conservar el archivo fuente.']);
        }
        $import = DB::transaction(function () use ($sha, $path, $file, $preview): FleetImport {
            $import = FleetImport::firstOrCreate(['sha256' => $sha, 'kind' => 'drivers'], ['original_name' => $file->getClientOriginalName(), 'source_path' => $path, 'preview' => $preview, 'user_id' => auth()->id()]);
            $import = FleetImport::lockForUpdate()->findOrFail($import->id);
            if ($import->status !== 'completed') {
                $import->update(['preview' => $preview]);
            }

            return $import;
        });

        return ['import' => $import, 'preview' => $preview];
    }

    public function confirm(int $importId, string $fingerprint): FleetImport
    {
        return DB::transaction(function () use ($importId, $fingerprint): FleetImport {
            $import = FleetImport::lockForUpdate()->where('kind', 'drivers')->findOrFail($importId);
            if ($import->status === 'completed') {
                return $import;
            }
            $import->touch();
            if (! hash_equals(FleetExcelImport::fingerprint($import), $fingerprint) || ! Storage::disk('local')->exists($import->source_path) || ! hash_equals($import->sha256, hash_file('sha256', Storage::disk('local')->path($import->source_path)))) {
                throw ValidationException::withMessages(['import' => 'La revisión o el archivo cambió. Vuelve a preparar la importación.']);
            }
            $people = FleetPerson::lockForUpdate()->get();
            $created = 0;
            foreach ($import->preview['drivers'] as $candidate) {
                if ($people->contains(fn (FleetPerson $person): bool => ($candidate['document_number'] && FleetExcelImport::normalize((string) $person->document_number) === FleetExcelImport::normalize($candidate['document_number'])) || (FleetExcelImport::normalize($person->name) === FleetExcelImport::normalize($candidate['name']) && (! $candidate['document_number'] || ! $person->document_number)))) {
                    continue;
                }
                $person = FleetPerson::create(['name' => $candidate['name'], 'document_number' => $candidate['document_number'], 'phone' => $candidate['phone'], 'is_driver' => true, 'is_owner' => false, 'is_active' => true]);
                $people->push($person);
                $created++;
            }
            $import->update(['status' => 'completed', 'decisions' => $import->preview['drivers'], 'result' => ['drivers_created' => $created, 'drivers_total' => FleetPerson::drivers()->count(), 'trucks_created' => 0], 'imported_at' => now()]);

            return $import->refresh();
        });
    }
}
