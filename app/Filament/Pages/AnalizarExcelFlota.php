<?php

namespace App\Filament\Pages;

use App\Services\DriverExcelImport;
use App\Services\FleetExcelImport;
use App\Services\XlsxReportReader;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

class AnalizarExcelFlota extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'analizar-excel-flota';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.analizar-excel-flota';

    public $file = null;

    #[Url]
    public string $kind = 'trucks';

    #[Locked]
    public string $importKind = 'trucks';

    public array $updateChoices = [];

    #[Locked]
    public int $updatesPage = 1;

    public function updatedKind(): void
    {
        $this->kind = $this->kind === 'drivers' ? 'drivers' : 'trucks';
        $this->updatedFile();
    }

    public function updatedUpdateChoices(): void
    {
        $this->reviewConfirmed = false;
    }

    public function nextUpdatesPage(): void
    {
        $this->updatesPage = min($this->updatesPage + 1, max(1, (int) ceil(count($this->preview['updates'] ?? []) / 6)));
    }

    public function previousUpdatesPage(): void
    {
        $this->updatesPage = max(1, $this->updatesPage - 1);
    }

    public function hasReviewActions(): bool
    {
        return count($this->preview['trucks'] ?? []) + count($this->preview['drivers'] ?? []) + count($this->preview['updates'] ?? []) + count($this->preview['observations'] ?? []) > 0 && $this->previewFingerprint !== null;
    }

    public ?string $selectedSheet = null;

    public ?int $headerRow = null;

    public array $mapping = [];

    public array $truckChoices = [];

    public bool $reviewConfirmed = false;

    #[Locked]
    public int $wizardStep = 2;

    #[Locked]
    public int $reviewPage = 1;

    public function openReview(): void
    {
        if ($this->analysis) {
            $this->dispatch('close-modal', id: 'fleet-relationships');
            if (! $this->preview) {
                $this->wizardStep = 2;
            }
            $this->dispatch('open-modal', id: 'fleet-review');
        }
    }

    public function openRelationships(): void
    {
        if ($this->preview) {
            $this->dispatch('close-modal', id: 'fleet-review');
            $this->dispatch('open-modal', id: 'fleet-relationships');
        }
    }

    public function backToColumns(): void
    {
        $this->cancelImport();
        $this->wizardStep = 2;
        $this->dispatch('open-modal', id: 'fleet-review');
    }

    public function nextReviewPage(): void
    {
        $this->reviewPage = min($this->reviewPage + 1, max(1, (int) ceil(count($this->preview[$this->importKind === 'drivers' ? 'drivers' : 'trucks'] ?? []) / 6)));
    }

    public function previousReviewPage(): void
    {
        $this->reviewPage = max(1, $this->reviewPage - 1);
    }

    protected function getViewData(): array
    {
        return [
            'reviewRows' => array_slice($this->preview[$this->importKind === 'drivers' ? 'drivers' : 'trucks'] ?? [], ($this->reviewPage - 1) * 6, 6, true),
            'reviewPages' => max(1, (int) ceil(count($this->preview[$this->importKind === 'drivers' ? 'drivers' : 'trucks'] ?? []) / 6)),
            'updateRows' => array_slice($this->preview['updates'] ?? [], ($this->updatesPage - 1) * 6, 6, true),
            'updatesPages' => max(1, (int) ceil(count($this->preview['updates'] ?? []) / 6)),
        ];
    }

    #[Locked]
    public array $columns = [];

    #[Locked]
    public array $sheets = [];

    #[Locked]
    public ?array $analysis = null;

    #[Locked]
    public ?array $preview = null;

    #[Locked]
    public ?int $importId = null;

    #[Locked]
    public ?string $previewFingerprint = null;

    #[Locked]
    public ?array $importResult = null;

    public function getTitle(): string
    {
        return $this->kind === 'drivers' ? 'Cargar Excel de conductores' : 'Cargar Excel de camiones';
    }

    public function cancelImport(): void
    {
        $this->preview = null;
        $this->importId = null;
        $this->previewFingerprint = null;
        $this->importResult = null;
        $this->truckChoices = [];
        $this->updateChoices = [];
        $this->updatesPage = 1;
        $this->reviewConfirmed = false;
        $this->reviewPage = 1;
        $this->resetValidation();
    }

    public function updatedMapping(): void
    {
        $this->cancelImport();
    }

    public function updatedTruckChoices(): void
    {
        $this->reviewConfirmed = false;
    }

    public function updatedSelectedSheet(): void
    {
        $this->analysis = null;
        $this->columns = [];
        $this->headerRow = null;
        $this->cancelImport();
    }

    public function updatedHeaderRow(): void
    {
        $this->analysis = null;
        $this->columns = [];
        $this->cancelImport();
    }

    public function updatedFile(): void
    {
        $this->analysis = null;
        $this->columns = [];
        $this->mapping = [];
        $this->sheets = [];
        $this->selectedSheet = null;
        $this->headerRow = null;
        $this->cancelImport();
    }

    public function clearFile(): void
    {
        $this->file = null;
        $this->updatedFile();
    }

    public function analyze(XlsxReportReader $reader): void
    {
        $this->cancelImport();
        $this->analysis = null;
        $this->columns = [];
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:30720'], 'headerRow' => ['nullable', 'integer', 'min:1', 'max:1048576'], 'selectedSheet' => ['nullable', 'string']], [
            'file.required' => 'Selecciona un archivo Excel .xlsx.', 'file.mimes' => 'Selecciona un archivo .xlsx válido.', 'file.max' => 'El archivo no debe superar 30 MB.',
        ]);
        try {
            $inspection = $reader->inspect($this->file->getRealPath(), $this->selectedSheet, $this->headerRow);
            $this->columns = $inspection['columns'];
            $this->sheets = $inspection['sheets'];
            unset($inspection['columns']);
            $this->analysis = $inspection;
            $this->selectedSheet = $inspection['sheet'];
            $this->headerRow = $inspection['header_row'];
            $saved = DB::table('report_column_profiles')->where('report_key', $this->kind === 'drivers' ? 'drivers' : 'simple_fleet')->first();
            $savedMappings = json_decode($saved?->column_mappings ?? '[]', true);
            $this->mapping = [];
            foreach ($this->columns as $position => $column) {
                $this->mapping[$position] = $savedMappings[$column['index'].':'.$column['name']] ?? 'discard';
            }
            $this->wizardStep = 2;
            $this->dispatch('open-modal', id: 'fleet-review');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'No se pudo leer el Excel. Comprueba el archivo, la hoja y la fila de encabezados.');
        }
    }

    public function saveMapping(): void
    {
        if (! $this->analysis) {
            $this->addError('mapping', 'Analiza el archivo antes de seleccionar las columnas.');

            return;
        }
        $rules = [];
        foreach ($this->columns as $position => $column) {
            $rules["mapping.$position"] = ['required', $this->kind === 'drivers' ? 'in:driver.name,document,phone,discard' : 'in:truck.plate,driver.name,date,time,datetime,discard'];
        }
        $this->validate($rules);
        $selected = array_filter($this->mapping, fn (string $value): bool => $value !== 'discard');
        if (count($selected) !== count(array_unique($selected)) || ! in_array($this->kind === 'drivers' ? 'driver.name' : 'truck.plate', $selected, true) || (in_array('datetime', $selected, true) && (in_array('date', $selected, true) || in_array('time', $selected, true))) || (in_array('date', $selected, true) !== in_array('time', $selected, true))) {
            $this->addError('mapping', $this->kind === 'drivers' ? 'Selecciona una columna Nombre del conductor, sin repetir variables.' : 'Selecciona una sola columna Placa. Para ordenar por fecha, asigna Fecha y Hora juntas o una columna Fecha y hora. No repitas variables.');

            return;
        }
        $values = [];
        foreach ($this->columns as $position => $column) {
            $values[$column['index'].':'.$column['name']] = $this->mapping[$position];
        }
        DB::table('report_column_profiles')->upsert([[
            'report_key' => $this->kind === 'drivers' ? 'drivers' : 'simple_fleet', 'report_name' => $this->getTitle(), 'column_mappings' => json_encode($values, JSON_UNESCAPED_UNICODE),
            'duplicate_policy' => 'exclude', 'created_at' => now(), 'updated_at' => now(),
        ]], ['report_key'], ['column_mappings', 'updated_at']);
    }

    public function prepareImport(FleetExcelImport $importer): void
    {
        $this->cancelImport();
        $this->saveMapping();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $fields = [];
        foreach ($this->columns as $position => $column) {
            if ($this->mapping[$position] === 'truck.plate') {
                $fields['plate'] = $column['index'];
            } elseif ($this->mapping[$position] === 'driver.name') {
                $fields['driver'] = $column['index'];
            } elseif ($this->mapping[$position] !== 'discard') {
                $fields[$this->mapping[$position]] = $column['index'];
            }
        }
        try {
            $this->importKind = $this->kind === 'drivers' ? 'drivers' : 'trucks';
            $prepared = ($this->importKind === 'drivers' ? app(DriverExcelImport::class) : $importer)->prepare($this->file, $fields, $this->analysis['sheet'], $this->analysis['header_row']);
            $import = $prepared['import'];
            $this->importId = $import->id;
            $this->preview = $prepared['preview'];
            $this->wizardStep = 3;
            $this->dispatch('open-modal', id: 'fleet-review');
            if ($import->status === 'completed') {
                $this->importResult = $import->result;
                $this->preview['trucks'] = [];
                $this->preview['drivers'] = [];
                $this->preview['updates'] = [];
                $this->preview['observations'] = [];

                return;
            }
            $this->previewFingerprint = FleetExcelImport::fingerprint($import);
            foreach ($this->preview['trucks'] as $index => $truck) {
                $this->truckChoices[$index] = ['conductor_name' => $truck['conductor_name'] ?? ''];
            }
            foreach ($this->preview['updates'] ?? [] as $index => $truck) {
                $this->updateChoices[$index] = ['approve' => false];
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('import', 'No se pudo preparar la revisión. Vuelve a cargar el archivo.');
        }
    }

    public function confirmImport(FleetExcelImport $importer): void
    {
        $this->validate(['reviewConfirmed' => ['accepted']], ['reviewConfirmed.accepted' => 'Confirma que revisaste los registros antes de guardar.']);
        if (! $this->importId || ! $this->previewFingerprint) {
            $this->addError('import', 'Prepara la vista previa antes de guardar.');

            return;
        }
        $import = $this->importKind === 'drivers'
            ? app(DriverExcelImport::class)->confirm($this->importId, $this->previewFingerprint)
            : $importer->confirm($this->importId, $this->previewFingerprint, $this->truckChoices, $this->updateChoices);
        $this->importResult = $import->result;
        $this->preview = null;
        $this->reviewConfirmed = false;
        $this->dispatch('close-modal', id: 'fleet-review');
        $this->dispatch('open-modal', id: 'fleet-success');
        Notification::make()->title('Registro exitoso')->success()->send();
    }
}
