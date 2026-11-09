<?php

namespace App\Filament\Pages;

use App\Models\FleetImport;
use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Inicio';

    protected string $view = 'filament.pages.fleet-dashboard';

    public function getWidgets(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return ['truckCount' => Truck::count(), 'driverCount' => FleetPerson::drivers()->count(), 'ownerCount' => FleetPerson::owners()->count(), 'withoutDriver' => Truck::whereNull('conductor_name')->orWhere('conductor_name', '')->count(), 'latestImport' => FleetImport::where('status', 'completed')->latest('imported_at')->first()];
    }
}
