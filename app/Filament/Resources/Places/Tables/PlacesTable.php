<?php

namespace App\Filament\Resources\Places\Tables;

use App\Filament\Resources\Places\Schemas\PlaceForm;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Lugar')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('latitude')
                    ->label('Latitud')
                    ->toggleable(),
                TextColumn::make('longitude')
                    ->label('Longitud')
                    ->toggleable(),
                TextColumn::make('place_group')
                    ->label('Grupo')
                    ->placeholder('Sin grupo')
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('Dirección del GPS')
                    ->placeholder('Sin dirección')
                    ->limit(45)
                    ->toggleable(),
                TextColumn::make('icon')
                    ->label('Icono')
                    ->placeholder('Sin icono')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)->recordAction('edit')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->modal()->schema(fn (Schema $schema): Schema => PlaceForm::configure($schema))->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter(),
            ]);
    }
}
