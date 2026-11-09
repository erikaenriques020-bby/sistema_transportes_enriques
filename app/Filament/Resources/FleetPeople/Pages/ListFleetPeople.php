<?php

namespace App\Filament\Resources\FleetPeople\Pages;

use App\Filament\Pages\AnalizarExcelFlota;
use App\Filament\Resources\FleetPeople\FleetPersonResource;
use App\Models\FleetPerson;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListFleetPeople extends ListRecords
{
    protected static string $resource = FleetPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Cargar Excel de conductores')->icon('heroicon-o-arrow-up-tray')->url(AnalizarExcelFlota::getUrl(['kind' => 'drivers'])),
            Action::make('reuseOwner')->label('Un dueño también conduce')->schema([
                Select::make('person_id')->label('Dueño existente')->options(fn (): array => FleetPerson::owners()->where('is_driver', false)->get()->mapWithKeys(fn (FleetPerson $person): array => [$person->id => $person->name.' · '.($person->document_number ?: 'Sin documento').' · #'.$person->id])->all())->searchable()->required(),
            ])->action(function (array $data): void {
                FleetPerson::owners()->where('is_driver', false)->findOrFail($data['person_id'])->update(['is_driver' => true]);
            }),
            CreateAction::make(),
        ];
    }
}
