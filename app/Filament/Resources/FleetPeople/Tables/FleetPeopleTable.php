<?php

namespace App\Filament\Resources\FleetPeople\Tables;

use App\Filament\Resources\FleetPeople\FleetPersonResource;
use App\Filament\Resources\FleetPeople\Schemas\FleetPersonForm;
use App\Filament\Resources\Owners\OwnerResource;
use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FleetPeopleTable
{
    public static function configure(Table $table, bool $ownerMode = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('document_number')->label('Documento')->searchable()->placeholder('Sin registrar'),
                TextColumn::make('phone')->label('Teléfono')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_owner')->label('También es dueño')->boolean()->visible(! $ownerMode),
                IconColumn::make('is_driver')->label('También conduce')->boolean()->visible($ownerMode),
                TextColumn::make('trucks_count')->counts('trucks')->label('Camiones propios')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('driven_trucks_count')->counts('drivenTrucks')->label('Camiones vinculados')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('trucks.plate')->label('Placas propias')->listWithLineBreaks()->placeholder('Sin camiones')->visible($ownerMode),
                TextColumn::make('trucks.conductor_name')->label('Conductores de sus camiones')->listWithLineBreaks()->placeholder('Sin conductor informado')->visible($ownerMode),
                TextColumn::make('gps_names')->label('Placas con este nombre en GPS')
                    ->state(fn (FleetPerson $record): string => Truck::get(['plate', 'conductor_name'])->filter(fn (Truck $truck): bool => Truck::normalizePlate((string) $truck->conductor_name) === Truck::normalizePlate($record->name))->pluck('plate')->implode(', '))
                    ->placeholder('Sin observaciones')->tooltip('Coincidencia de nombre; confirma la identidad desde Camiones antes de vincular una ficha.')->visible(! $ownerMode),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_owner')->label('Dueño'),
                TernaryFilter::make('is_driver')->label('Chofer'),
                TernaryFilter::make('is_active')->label('Estado'),
            ])
            ->recordActions([
                EditAction::make()->modal()->schema(fn (Schema $schema): Schema => FleetPersonForm::configure($schema, ownerMode: $ownerMode))->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()->successNotificationTitle('Datos actualizados'),
                Action::make('delete')->label('Eliminar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->authorize(fn (FleetPerson $record): bool => ($ownerMode ? OwnerResource::class : FleetPersonResource::class)::canDelete($record))
                    ->modalHeading(fn (FleetPerson $record): string => $record->deletionBlockReason() ? 'No se puede eliminar este registro' : 'Eliminar a '.$record->name)
                    ->modalDescription(fn (FleetPerson $record): string => ($record->deletionBlockReason()
                        ? $record->deletionBlockReason().' '.($record->is_active ? 'Puedes inactivar la ficha sin cambiar sus camiones ni su historial.' : 'La ficha ya está inactiva. Se conservan sus camiones y su historial.')
                        : 'Se eliminará esta ficha sin relaciones. Esta acción es permanente.').($record->is_owner && $record->is_driver ? ' Esta persona tiene ambos roles; la acción afecta a la misma ficha en Dueños y Conductores.' : ''))
                    ->modalSubmitAction(fn (Action $action, FleetPerson $record): Action|bool => $record->deletionBlockReason() ? false : $action)
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->extraModalFooterActions(fn (Action $action, FleetPerson $record): array => $record->deletionBlockReason() && $record->is_active && ($ownerMode ? OwnerResource::class : FleetPersonResource::class)::canEdit($record)
                        ? [$action->makeModalSubmitAction('deactivate', arguments: ['deactivate' => true])->label('Inactivar registro')->color('warning')->icon('heroicon-o-pause-circle')]
                        : [])
                    ->action(function (FleetPerson $record, array $arguments, Action $action) use ($ownerMode): void {
                        if ($arguments['deactivate'] ?? false) {
                            abort_unless(($ownerMode ? OwnerResource::class : FleetPersonResource::class)::canEdit($record), 403);
                        }
                        try {
                            DB::transaction(function () use ($record, $arguments): void {
                                $person = FleetPerson::lockForUpdate()->findOrFail($record->id);
                                if ($arguments['deactivate'] ?? false) {
                                    $person->update(['is_active' => false]);
                                } else {
                                    $person->delete();
                                }
                            });
                        } catch (ValidationException $exception) {
                            Notification::make()->title('No se puede eliminar')->body(collect($exception->errors())->flatten()->first())->warning()->send();
                            $action->halt();
                        }
                        Notification::make()->title(($arguments['deactivate'] ?? false) ? 'Registro inactivado; relaciones conservadas' : 'Registro eliminado')->success()->send();
                    }),
            ])
            ->recordUrl(null)->recordAction('edit')
            ->toolbarActions([]);
    }
}
