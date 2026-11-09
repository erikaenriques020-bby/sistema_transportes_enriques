<?php

namespace App\Filament\Resources\Trucks\Pages;

use App\Filament\Pages\AnalizarExcelFlota;
use App\Filament\Resources\Trucks\TruckResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrucks extends ListRecords
{
    protected static string $resource = TruckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ingresarDatosPorExcel')
                ->label('Ingresar datos por Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->url(AnalizarExcelFlota::getUrl()),
            CreateAction::make(),
        ];
    }
}
