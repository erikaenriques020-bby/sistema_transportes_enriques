<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Trucks\TruckResource;
use App\Models\GpsPosition;
use App\Models\Place;
use App\Models\Truck;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class UbicacionActivos extends Page
{
    use WithPagination;

    protected static ?string $slug = 'ubicacion/activos';

    protected static ?string $title = 'Activos';

    protected ?string $heading = '';

    protected static ?string $navigationLabel = 'Activos';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Ubicación';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected string $view = 'filament.pages.ubicacion-activos';

    protected \Filament\Support\Enums\Width|string|null $maxContentWidth = Width::Full;

    public string $search = '';

    public string $positionFilter = 'all';

    public function updatedPositionFilter(): void
    {
        $this->validate(['positionFilter' => ['in:all,located,missing,active,inactive']]);
        $this->resetPage();
    }

    public function showFleet(): void
    {
        $this->selectedTruckId = null;
        $this->selection = null;
        $this->points = [];
        $this->routeSummary = null;
        $this->positionMessage = 'Vista general de las últimas posiciones registradas. Selecciona un camión para consultar sus datos.';
        $this->resetValidation();
        $this->dispatchMap(false);
    }

    public string $date = '';

    #[Locked]
    public ?int $selectedTruckId = null;

    #[Locked]
    public ?array $selection = null;

    #[Locked]
    public array $points = [];

    #[Locked]
    public ?array $routeSummary = null;

    #[Locked]
    public string $positionMessage = 'Selecciona un camión para consultar sus registros GPS.';

    public static function canAccess(): bool
    {
        return TruckResource::canViewAny();
    }

    public function mount(): void
    {
        $this->date = now('America/La_Paz')->toDateString();
        $truckId = request()->integer('truck');
        if ($truckId > 0) {
            $this->selectTruck($truckId);
        }
        $date = request()->query('date');
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            $this->date = $date;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectTruck(int $truckId): void
    {
        $truck = TruckResource::getEloquentQuery()->with(['owner', 'latestGpsPosition'])->findOrFail($truckId);
        abort_unless(TruckResource::canView($truck), 403);
        $this->selectedTruckId = $truck->id;
        $position = $truck->latestGpsPosition;
        $this->selection = ['id' => $truck->id, 'plate' => $truck->plate, 'driver' => $truck->conductor_name, 'owner' => $truck->owner?->name, 'active' => $truck->is_active, 'gps' => $position ? $this->point($position) : null];
        $this->points = $position ? [$this->point($position)] : [];
        $this->routeSummary = null;
        if ($position) {
            $this->date = $position->observed_at->toDateString();
        }
        $this->positionMessage = $position ? 'Última posición registrada: '.$position->observed_at->format('d/m/Y H:i:s').' · No es ubicación en tiempo real.' : 'Este camión no tiene posiciones GPS válidas importadas.';
        $this->resetValidation();
        $this->dispatchMap(false);
    }

    public function showRoute(): void
    {
        $this->points = [];
        $this->routeSummary = null;
        $this->dispatchMap(false);
        $this->validate(['date' => ['required', 'date_format:Y-m-d'], 'selectedTruckId' => ['required', 'integer']], ['selectedTruckId.required' => 'Selecciona un camión antes de consultar el recorrido.']);
        $truck = TruckResource::getEloquentQuery()->findOrFail($this->selectedTruckId);
        abort_unless(TruckResource::canView($truck), 403);
        $query = $truck->gpsPositions()->validCoordinates()->where('observed_at', '>=', $this->date.' 00:00:00')->where('observed_at', '<', CarbonImmutable::parse($this->date)->addDay()->toDateString().' 00:00:00')->orderBy('observed_at')->orderBy('id');
        $total = (clone $query)->count();
        $stride = max(1, (int) ceil(max(0, $total - 1) / 4999));
        $index = 0;
        $coordinates = [];
        foreach ($query->cursor(['id', 'latitude', 'longitude', 'observed_at', 'details']) as $position) {
            $coordinates[$position->latitude.':'.$position->longitude] = true;
            if ($index % $stride === 0 || $index === $total - 1) {
                $this->points[] = $this->point($position);
            }
            $index++;
        }
        if ($this->points) {
            $this->routeSummary = ['total' => $total, 'displayed' => count($this->points), 'distinct_locations' => count($coordinates), 'from' => $this->points[0]['at'], 'to' => $this->points[array_key_last($this->points)]['at']];
        }
        $this->positionMessage = $this->points ? $total.' posiciones registradas el '.$this->date.'. La línea une puntos por hora; no se ajusta a carreteras.'.($total > count($this->points) ? ' Se muestran '.count($this->points).' puntos representativos de todo el día, incluyendo inicio y fin; la línea está simplificada.' : '') : 'No hay posiciones GPS válidas para la fecha seleccionada.';
        $this->dispatchMap(true);
    }

    private function point(GpsPosition $position): array
    {
        return ['lat' => $position->latitude, 'lng' => $position->longitude, 'at' => $position->observed_at->format('d/m/Y H:i:s'), 'details' => $position->details ?? []];
    }

    private function dispatchMap(bool $route): void
    {
        $this->dispatch('fleet-map-data', payload: ['truck' => $this->selection, 'points' => $this->points, 'route' => $route]);
    }

    protected function getViewData(): array
    {
        $query = TruckResource::getEloquentQuery();

        return [
            'trucks' => (clone $query)->with(['owner', 'latestGpsPosition'])->when($this->search !== '', fn (Builder $q): Builder => $q->where(fn (Builder $nested): Builder => $nested->where('plate', 'like', '%'.$this->search.'%')->orWhere('conductor_name', 'like', '%'.$this->search.'%')))
                ->when($this->positionFilter === 'located', fn (Builder $q): Builder => $q->whereHas('latestGpsPosition'))
                ->when($this->positionFilter === 'missing', fn (Builder $q): Builder => $q->whereDoesntHave('latestGpsPosition'))
                ->when(in_array($this->positionFilter, ['active', 'inactive']), fn (Builder $q): Builder => $q->where('is_active', $this->positionFilter === 'active'))
                ->orderBy('plate')->paginate(15),
            'fleetPositions' => (clone $query)->with(['owner', 'latestGpsPosition'])->whereHas('latestGpsPosition')->orderBy('plate')->get()->filter(fn (Truck $truck): bool => TruckResource::canView($truck))->map(fn (Truck $truck): array => ['truck' => ['id' => $truck->id, 'plate' => $truck->plate, 'driver' => $truck->conductor_name, 'owner' => $truck->owner?->name, 'active' => $truck->is_active], 'point' => $this->point($truck->latestGpsPosition)])->values()->all(),
            'truckCount' => (clone $query)->count(),
            'locatedCount' => (clone $query)->whereHas('gpsPositions', fn (Builder $q): Builder => $q->validCoordinates())->count(),
            'places' => Place::whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])->orderBy('name')->get(['id', 'name', 'latitude', 'longitude'])->map(fn (Place $place): array => ['name' => $place->name, 'lat' => (float) $place->latitude, 'lng' => (float) $place->longitude])->all(),
        ];
    }
}
