<?php

namespace App\Filament\Resources\Places\Pages;

use App\Filament\Pages\ImportarPosicionesGps;
use App\Filament\Resources\Places\PlaceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlaces extends ListRecords
{
    protected static string $resource = PlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Importar Excel de lugares')->icon('heroicon-o-arrow-up-tray')->url(ImportarPosicionesGps::getUrl(['target' => 'places'])),
            CreateAction::make(),
        ];
    }
}
