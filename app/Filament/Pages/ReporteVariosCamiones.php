<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Trucks\TruckResource;
use App\Models\GpsPosition;
use App\Models\Truck;
use App\Services\GpsReportExcelExporter;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class ReporteVariosCamiones extends Page
{
    use WithPagination;

    protected static ?string $slug = 'reportes/varios-camiones';

    protected static ?string $title = 'Reporte de varios camiones';

    protected static ?string $navigationLabel = 'Varios camiones';

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected string $view = 'filament.pages.reporte-varios-camiones';

    public array $truckIds = [];

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $hasQueried = false;

    public function updatedTruckIds(): void
    {
        $this->hasQueried = false;
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->hasQueried = false;
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->hasQueried = false;
        $this->resetPage();
    }

    public static function canAccess(): bool
    {
        return TruckResource::canViewAny();
    }

    public function mount(): void
    {
        $this->dateFrom = now('America/La_Paz')->subDays(6)->toDateString();
        $this->dateTo = now('America/La_Paz')->toDateString();
    }

    public function runReport(): void
    {
        $this->hasQueried = false;
        $this->validate([
            'truckIds' => ['required', 'array', 'min:1'],
            'truckIds.*' => ['required', 'integer', 'distinct', 'exists:trucks,id'],
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
        ], [
            'truckIds.required' => 'Selecciona al menos un camión.',
            'truckIds.min' => 'Selecciona al menos un camión.',
            'dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);

        $allowedIds = TruckResource::getEloquentQuery()->whereKey($this->truckIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(count($allowedIds) === count($this->truckIds), 403);
        $this->truckIds = $allowedIds;
        $this->hasQueried = true;
        $this->resetPage();
    }

    public function exportExcel(GpsReportExcelExporter $exporter): BinaryFileResponse
    {
        $this->runReport();
        $trucks = TruckResource::getEloquentQuery()->whereKey($this->truckIds)->orderBy('plate')->get();
        abort_unless($trucks->count() === count($this->truckIds), 403);
        $endExclusive = CarbonImmutable::parse($this->dateTo)->addDay()->toDateString();
        $summaries = DB::table('gps_positions')
            ->selectRaw('truck_id, COUNT(*) as positions_count, MIN(observed_at) as first_at, MAX(observed_at) as last_at')
            ->whereIn('truck_id', $this->truckIds)
            ->where('observed_at', '>=', $this->dateFrom.' 00:00:00')
            ->where('observed_at', '<', $endExclusive.' 00:00:00')
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180])
            ->groupBy('truck_id')->get()->keyBy('truck_id')
            ->map(fn ($row): array => ['count' => (int) $row->positions_count, 'first' => $row->first_at, 'last' => $row->last_at])->all();

        return $exporter->multipleTrucks($trucks, $summaries, $this->dateFrom, $this->dateTo);
    }

    protected function getViewData(): array
    {
        $trucks = TruckResource::getEloquentQuery()->orderBy('plate')->get(['id', 'plate', 'conductor_name']);
        $summaries = collect();
        $positions = null;

        if ($this->hasQueried && $this->truckIds && $this->dateFrom !== '' && $this->dateTo >= $this->dateFrom) {
            $base = $this->positionQuery();
            $aggregates = (clone $base)->selectRaw('truck_id, COUNT(*) as positions_count, MIN(observed_at) as first_at, MAX(observed_at) as last_at')
                ->groupBy('truck_id')->get()->keyBy('truck_id');
            $summaries = $trucks->whereIn('id', $this->truckIds)->map(fn (Truck $truck): array => [
                'plate' => $truck->plate,
                'driver' => $truck->conductor_name,
                'count' => (int) ($aggregates->get($truck->id)?->positions_count ?? 0),
                'first' => $aggregates->get($truck->id)?->first_at,
                'last' => $aggregates->get($truck->id)?->last_at,
            ])->values();
            $positions = $base->with(['truck:id,plate', 'fleetImport:id,original_name'])->orderBy('observed_at')->orderBy('truck_id')->paginate(50);
        }

        return ['trucks' => $trucks, 'summaries' => $summaries, 'positions' => $positions];
    }

    private function positionQuery()
    {
        $endExclusive = CarbonImmutable::parse($this->dateTo)->addDay()->toDateString();

        return GpsPosition::query()->whereIn('truck_id', $this->truckIds)
            ->where('observed_at', '>=', $this->dateFrom.' 00:00:00')
            ->where('observed_at', '<', $endExclusive.' 00:00:00')
            ->validCoordinates();
    }
}
