<?php

namespace App\Filament\Pages;

use App\Models\Truck;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Conductores extends Page
{
    protected static ?string $slug = 'conductores-resumen';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Conductores';

    protected string $view = 'filament.pages.conductores';

    protected function getViewData(): array
    {
        $trucks = Truck::orderBy('plate')->get(['id', 'plate', 'conductor_name']);
        $missing = $trucks->filter(fn (Truck $truck): bool => blank($truck->conductor_name));

        return ['truckCount' => $trucks->count(), 'missingDrivers' => $missing,
            'conductors' => $trucks->filter(fn (Truck $truck): bool => filled($truck->conductor_name))
                ->groupBy(fn (Truck $truck): string => Truck::normalizePlate($truck->conductor_name))
                ->map(fn ($trucks): array => ['name' => $trucks->first()->conductor_name, 'plates' => $trucks->pluck('plate')->all()])->sortBy('name')->values()];
    }
}
