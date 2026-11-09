<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Trucks\TruckResource;
use App\Models\GpsPosition;
use App\Services\GpsReportExcelExporter;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class ReporteCamion extends Page
{
    use WithPagination;

    protected static ?string $slug = 'reportes/por-camion';

    protected static ?string $title = 'Reporte por camión';

    protected static ?string $navigationLabel = 'Por camión';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected string $view = 'filament.pages.reporte-camion';

    public ?int $truckId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $hasQueried = false;

    public function updatedTruckId(): void
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
        $today = now('America/La_Paz')->toDateString();
        $this->dateFrom = now('America/La_Paz')->subDays(6)->toDateString();
        $this->dateTo = $today;
        $requestedTruck = request()->integer('truck');
        if ($requestedTruck > 0 && TruckResource::getEloquentQuery()->whereKey($requestedTruck)->exists()) {
            $this->truckId = $requestedTruck;
        }
    }

    public function runReport(): void
    {
        $this->hasQueried = false;
        $this->validate([
            'truckId' => ['required', 'integer', 'exists:trucks,id'],
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
        ], [
            'truckId.required' => 'Selecciona un camión.',
            'dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);

        $truck = TruckResource::getEloquentQuery()->findOrFail($this->truckId);
        abort_unless(TruckResource::canView($truck), 403);
        $this->hasQueried = true;
        $this->resetPage();
    }

    public function exportExcel(GpsReportExcelExporter $exporter): BinaryFileResponse
    {
        $this->runReport();
        $truck = TruckResource::getEloquentQuery()->findOrFail($this->truckId);
        abort_unless(TruckResource::canView($truck), 403);

        return $exporter->singleTruck($truck, $this->dateFrom, $this->dateTo);
    }

    protected function getViewData(): array
    {
        $trucks = TruckResource::getEloquentQuery()->orderBy('plate')->get(['id', 'plate', 'conductor_name']);
        $positions = null;
        $summary = null;

        if ($this->hasQueried && $this->truckId && $this->validDateRange()) {
            $base = $this->positionQuery();
            $summary = [
                'count' => (clone $base)->count(),
                'first' => (clone $base)->min('observed_at'),
                'last' => (clone $base)->max('observed_at'),
            ];
            $positions = $base->with('fleetImport:id,original_name')->orderBy('observed_at')->orderBy('id')->paginate(50);
        }

        return ['trucks' => $trucks, 'positions' => $positions, 'summary' => $summary];
    }

    private function positionQuery()
    {
        $endExclusive = CarbonImmutable::parse($this->dateTo)->addDay()->toDateString();

        return GpsPosition::query()->where('truck_id', $this->truckId)
            ->where('observed_at', '>=', $this->dateFrom.' 00:00:00')
            ->where('observed_at', '<', $endExclusive.' 00:00:00')
            ->validCoordinates();
    }

    private function validDateRange(): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->dateTo) === 1
            && $this->dateTo >= $this->dateFrom;
    }
}
