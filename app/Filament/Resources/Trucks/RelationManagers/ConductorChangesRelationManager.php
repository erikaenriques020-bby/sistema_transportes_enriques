<?php

namespace App\Filament\Resources\Trucks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConductorChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'conductorChanges';

    protected static ?string $title = 'Historial de conductores';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('old_name')->label('Anterior')->placeholder('Sin conductor'),
            TextColumn::make('new_name')->label('Nuevo')->placeholder('Sin conductor'),
            TextColumn::make('observed_at')->label('Fecha y hora GPS')->dateTime('d/m/Y H:i:s')->placeholder('Sin fecha GPS'),
            TextColumn::make('created_at')->label('Registrado en el sistema')->dateTime('d/m/Y H:i:s'),
            TextColumn::make('fleet_import_id')->label('Carga Excel')->placeholder('Cambio manual o dato inicial'),
        ])->defaultSort('id', 'desc');
    }
}
