<?php

namespace App\Filament\Resources\FleetPeople\Pages;

use App\Filament\Resources\FleetPeople\FleetPersonResource;
use Filament\Resources\Pages\EditRecord;

class EditFleetPerson extends EditRecord
{
    protected static string $resource = FleetPersonResource::class;
}
