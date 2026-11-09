<?php

namespace App\Filament\Resources\Trucks\Tables;

use App\Filament\Resources\Trucks\Schemas\TruckForm;
use App\Models\Truck;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrucksTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('plate')->label('Placa')->searchable()->sortable(),
            TextColumn::make('conductor_name')->label('Conductor')->searchable()->sortable()->placeholder('Sin conductor informado'),
            TextColumn::make('owner.name')->label('Dueño')->placeholder('Sin dueño registrado')->searchable()->toggleable(),
            TextColumn::make('driver_observed_at')->label('Fecha GPS del conductor')->date('d/m/Y', timezone: 'UTC')->sortable()->placeholder('Sin fecha GPS')
                ->tooltip('Fecha del reporte que respalda al conductor actual. El GPS se actualiza mediante Excel.'),
            TextColumn::make('driver_observed_time')->label('Hora GPS')->state(fn (Truck $record): ?string => $record->driver_observed_at?->format('H:i:s'))->placeholder('Sin hora GPS'),
            TextColumn::make('driver_recorded_at')->label('Último cambio en el sistema')->dateTime('d/m/Y H:i:s', timezone: 'America/La_Paz')->sortable()->placeholder('Dato inicial sin fecha de cambio'),
            TextColumn::make('is_active')->label('Estado')->badge()->sortable()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-pause-circle'),
        ])->filters([
            TernaryFilter::make('is_active')->label('Estado')->placeholder('Todos los estados')->trueLabel('Activos')->falseLabel('Inactivos'),
            TernaryFilter::make('has_conductor')->label('Conductor')->placeholder('Con y sin conductor')
                ->trueLabel('Con conductor informado')->falseLabel('Sin conductor informado')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('conductor_name')->where('conductor_name', '!=', ''),
                    false: fn (Builder $query): Builder => $query->where(fn (Builder $nested): Builder => $nested->whereNull('conductor_name')->orWhere('conductor_name', '')),
                ),
        ])->filtersTriggerAction(fn (Action $action): Action => $action->button()->label('Filtrar')->icon('heroicon-o-funnel'))
            ->recordUrl(null)->recordAction('edit')
            ->recordActions([EditAction::make()->modal()->schema(fn (Schema $schema): Schema => TruckForm::configure($schema))->modalWidth('4xl')->stickyModalHeader()->stickyModalFooter()->icon('heroicon-o-pencil-square')->successNotificationTitle('Camión actualizado')])->toolbarActions([])->defaultSort('plate');
    }
}
