<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Places\PlaceResource;
use App\Filament\Resources\Trucks\TruckResource;
use App\Models\FleetImport;
use App\Models\GpsImportRow;
use App\Services\FleetExcelImport;
use App\Services\GpsExcelImport;
use App\Services\LocationImportSchema;
use App\Services\PlaceExcelImport;
use App\Services\XlsxReportReader;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

class ImportarPosicionesGps extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'ubicacion/importar-gps';

    protected static ?string $title = 'Importar Excel de ubicación';

    protected static ?string $navigationLabel = 'Importar Excel';

    protected static string|UnitEnum|null $navigationGroup = 'Ubicación';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.importar-posiciones-gps';

    public $file = null;

    #[Url]
    public string $target = 'gps';

    #[Locked]
    public string $importTarget = 'gps';

    #[Locked]
    public ?string $sourceName = null;

    public function mount(): void
    {
        if (! in_array($this->target, ['gps', 'places'], true)) {
            $this->target = 'gps';
        }
        $review = request()->integer('review');
        if ($review > 0) {
            $batch = FleetImport::whereIn('kind', ['gps', 'places'])->findOrFail($review);
            $this->target = $batch->kind;
            $this->authorizeTarget();
            $this->loadReview($batch);
            $this->dispatch('open-modal', id: 'gps-review');
        }
    }

    public function updatedTarget(): void
    {
        $this->authorizeTarget();
        $this->resetReview();
        $this->seedMapping();
    }

    public function chooseTarget(string $target): void
    {
        $this->target = $target;
        $this->updatedTarget();
    }

    private function authorizeTarget(): void
    {
        $this->validate(['target' => ['required', 'in:gps,places']]);
        abort_unless($this->target === 'places' ? PlaceResource::canCreate() : TruckResource::canCreate(), 403);
    }

    private function seedMapping(): void
    {
        if (! $this->inspection) {
            $this->mapping = [];

            return;
        }
        $profile = DB::table('report_column_profiles')->where('report_key', $this->target === 'places' ? 'places' : 'gps_positions')->first();
        $this->mapping = LocationImportSchema::guess($this->target, $this->inspection['columns'], json_decode($profile?->column_mappings ?? '[]', true));
    }

    private function loadReview(FleetImport $batch): void
    {
        $this->importId = $batch->id;
        $this->importTarget = $batch->kind;
        $this->sourceName = $batch->original_name;
        $this->summary = $batch->preview;
        $this->completed = $batch->status === 'completed';
        $this->result = $this->completed ? $batch->result : null;
        $this->fingerprint = $this->completed ? null : FleetExcelImport::fingerprint($batch);
    }

    public ?string $sheet = null;

    public ?int $headerRow = null;

    public array $mapping = [];

    public bool $reviewConfirmed = false;

    public string $reviewFilter = 'all';

    #[Locked]
    public ?array $inspection = null;

    #[Locked]
    public ?int $importId = null;

    #[Locked]
    public ?string $fingerprint = null;

    #[Locked]
    public ?array $summary = null;

    #[Locked]
    public ?array $result = null;

    #[Locked]
    public int $reviewPage = 1;

    #[Locked]
    public bool $completed = false;

    public static function canAccess(): bool
    {
        return TruckResource::canCreate() || PlaceResource::canCreate();
    }

    public function updatedFile(): void
    {
        $this->inspection = null;
        $this->sheet = null;
        $this->headerRow = null;
        $this->mapping = [];
        $this->resetReview();
    }

    public function updatedSheet(): void
    {
        $this->inspection = null;
        $this->mapping = [];
        $this->headerRow = null;
        $this->resetReview();
    }

    public function updatedHeaderRow(): void
    {
        $this->inspection = null;
        $this->mapping = [];
        $this->resetReview();
    }

    public function updatedMapping(): void
    {
        $this->resetReview();
    }

    public function updatedReviewFilter(): void
    {
        $this->reviewPage = 1;
    }

    public function resetReview(): void
    {
        $this->sourceName = null;
        $this->importId = null;
        $this->fingerprint = null;
        $this->summary = null;
        $this->result = null;
        $this->completed = false;
        $this->reviewConfirmed = false;
        $this->reviewPage = 1;
        $this->reviewFilter = 'all';
        $this->resetValidation();
    }

    public function analyze(XlsxReportReader $reader): void
    {
        $this->authorizeTarget();
        $this->resetReview();
        $this->inspection = null;
        $this->mapping = [];
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:30720'], 'headerRow' => ['nullable', 'integer', 'min:1', 'max:1048576'], 'sheet' => ['nullable', 'string']], [
            'file.required' => 'Selecciona un archivo Excel antes de analizar.',
            'file.uploaded' => 'La subida del archivo falló. Comprueba tu conexión y vuelve a seleccionarlo.',
            'file.mimes' => 'El archivo debe ser un Excel .xlsx. Si es .xls o CSV, guárdalo como .xlsx antes de subirlo.',
            'file.max' => 'El archivo supera el límite de 30 MB.',
            'headerRow.integer' => 'La fila de encabezados debe ser un número entero.',
            'headerRow.min' => 'La fila de encabezados debe ser 1 o mayor.',
            'headerRow.max' => 'La fila de encabezados supera el tamaño permitido de una hoja Excel.',
        ]);
        try {
            $this->inspection = $reader->inspect($this->file->getRealPath(), $this->sheet, $this->headerRow);
            $this->sheet = $this->inspection['sheet'];
            $this->headerRow = $this->inspection['header_row'];
            $this->seedMapping();
        } catch (Throwable $exception) {
            report($exception);
            $this->inspection = null;
            $message = match (true) {
                $exception instanceof \RuntimeException && $exception->getMessage() === 'No se encontró la hoja seleccionada.' => 'La hoja seleccionada no existe en este archivo. Vuelve a seleccionar el Excel y elige una hoja disponible.',
                $exception instanceof \RuntimeException && in_array($exception->getMessage(), ['No hay encabezados.', 'La fila indicada no contiene encabezados.'], true) => 'No se encontraron encabezados en la fila indicada. Comprueba que el Excel contenga datos y selecciona la fila correcta.',
                default => 'No se pudo leer el contenido del Excel. Puede estar dañado, protegido con contraseña o no ser un .xlsx válido. Ábrelo en Excel, guárdalo como un nuevo .xlsx e inténtalo otra vez.',
            };
            $this->addError('file', $message);
            Notification::make()->title('No se pudo analizar el archivo')->body($message)->danger()->send();
        }
    }

    public function prepareImport(GpsExcelImport $importer): void
    {
        $this->authorizeTarget();
        $this->resetReview();
        if (! $this->inspection || ! $this->file) {
            $this->addError('import', 'Analiza el archivo antes de preparar la revisión.');

            return;
        }
        $fields = array_filter($this->mapping, fn ($value): bool => $value !== '' && $value !== null);
        $allowed = array_column($this->inspection['columns'], 'index');
        foreach ($fields as $index) {
            if (! in_array((string) $index, array_map('strval', $allowed), true)) {
                $this->addError('mapping', 'Selecciona columnas presentes en este archivo.');

                return;
            }
        }
        try {
            $batch = ($this->target === 'places' ? app(PlaceExcelImport::class) : $importer)->prepare($this->file, $fields, $this->inspection['sheet'], $this->inspection['header_row']);
            $this->loadReview($batch);
            $values = [];
            foreach ($this->inspection['columns'] as $column) {
                $field = array_search((string) $column['index'], array_map('strval', $fields), true);
                $values[$column['index'].':'.$column['name']] = $field === false ? null : $field;
            }
            DB::table('report_column_profiles')->upsert([['report_key' => $this->target === 'places' ? 'places' : 'gps_positions', 'report_name' => $this->target === 'places' ? 'Listado de lugares' : 'Posiciones GPS', 'column_mappings' => json_encode($values, JSON_UNESCAPED_UNICODE), 'duplicate_policy' => 'exclude', 'created_at' => now(), 'updated_at' => now()]], ['report_key'], ['column_mappings', 'updated_at']);
            $this->dispatch('open-modal', id: 'gps-review');
        } catch (ValidationException $exception) {
            $this->addError('mapping', collect($exception->errors())->flatten()->first());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('import', 'No se pudo preparar la revisión. No se guardaron posiciones; vuelve a analizar.');
        }
    }

    public function openReview(): void
    {
        if ($this->importId) {
            $this->dispatch('open-modal', id: 'gps-review');
        }
    }

    public function previousReviewPage(): void
    {
        $this->reviewPage = max(1, $this->reviewPage - 1);
    }

    public function nextReviewPage(): void
    {
        $this->reviewPage = min($this->reviewPage + 1, max(1, (int) ceil($this->reviewQuery()->count() / 8)));
    }

    public function confirmImport(GpsExcelImport $importer): void
    {
        $this->validate(['reviewConfirmed' => ['accepted']], ['reviewConfirmed.accepted' => 'Confirma que revisaste los datos y los registros omitidos.']);
        if (! $this->importId || ! $this->fingerprint || $this->completed) {
            $this->addError('import', 'Prepara una revisión pendiente antes de guardar.');

            return;
        }
        try {
            $this->target = $this->importTarget;
            $this->authorizeTarget();
            $batch = ($this->importTarget === 'places' ? app(PlaceExcelImport::class) : $importer)->confirm($this->importId, $this->fingerprint);
            $this->completed = true;
            $this->fingerprint = null;
            $this->result = $batch->result;
            $this->reviewConfirmed = false;
            $this->dispatch('close-modal', id: 'gps-review');
            $this->dispatch('open-modal', id: 'gps-success');
            Notification::make()->title($this->importTarget === 'places' ? 'Lugares registrados' : 'Posiciones GPS registradas')->success()->send();
        } catch (ValidationException $exception) {
            $this->addError('import', collect($exception->errors())->flatten()->first());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('import', 'No se pudo confirmar esta carga. Vuelve a analizar; las posiciones existentes se conservan.');
        }
    }

    private function reviewQuery(): Builder
    {
        $query = GpsImportRow::query()->where('gps_import_rows.fleet_import_id', $this->importId ?? 0)
            ->leftJoin('gps_positions as saved', fn ($join) => $join->on('saved.truck_id', '=', 'gps_import_rows.truck_id')->on('saved.observed_at', '=', 'gps_import_rows.observed_at'))
            ->select('gps_import_rows.*', 'saved.latitude as stored_latitude', 'saved.longitude as stored_longitude');

        return match ($this->reviewFilter) {
            'new' => $query->where('status', 'new'),
            'duplicates' => $query->whereIn('status', ['duplicate_file', 'existing']),
            'conflicts' => $query->whereIn('status', ['conflict_file', 'conflict_existing']),
            'invalid' => $query->where('status', 'invalid'),
            'unknown' => $query->where('status', 'unknown_plate'),
            default => $query,
        };
    }

    protected function getViewData(): array
    {
        $query = $this->reviewQuery();

        return ['fieldDefinitions' => LocationImportSchema::fields($this->target), 'suggestedTarget' => $this->inspection ? LocationImportSchema::suggestedTarget($this->inspection['columns']) : null, 'reviewRows' => $query->orderBy('row_number')->offset(($this->reviewPage - 1) * 8)->limit(8)->get(), 'reviewPages' => max(1, (int) ceil($this->reviewQuery()->count() / 8))];
    }
}
