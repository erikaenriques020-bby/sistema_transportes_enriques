<?php

namespace App\Filament\Resources\Owners\Pages;

use App\Filament\Resources\Owners\OwnerResource;
use App\Models\FleetPerson;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListOwners extends ListRecords
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reuseDriver')->label('Un conductor también es dueño')->icon('heroicon-o-user-plus')
                ->schema([Select::make('person_id')->label('Conductor existente')->options(fn (): array => FleetPerson::drivers()->where('is_owner', false)->orderBy('name')->get()->mapWithKeys(fn (FleetPerson $person): array => [$person->id => $person->name.' · '.($person->document_number ?: 'Sin documento').' · #'.$person->id])->all())->searchable()->required()])
                ->action(function (array $data): void {
                    FleetPerson::drivers()->where('is_owner', false)->findOrFail($data['person_id'])->update(['is_owner' => true]);
                }),
            CreateAction::make(),
        ];
    }
}
