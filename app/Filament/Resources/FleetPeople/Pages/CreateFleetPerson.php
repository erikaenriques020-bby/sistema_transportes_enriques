<?php

namespace App\Filament\Resources\FleetPeople\Pages;

use App\Filament\Resources\FleetPeople\FleetPersonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFleetPerson extends CreateRecord
{
    protected static string $resource = FleetPersonResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return FleetPersonResource::getUrl('index');
    }
}
