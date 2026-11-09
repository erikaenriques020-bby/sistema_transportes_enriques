<?php

namespace App\Filament\Resources\Trucks\Schemas;

use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TruckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Camión')->description('Solo la placa es obligatoria.')->schema([
                TextInput::make('plate')->label('Placa')->required()->maxLength(20)
                    ->dehydrateStateUsing(fn (string $state): string => Truck::normalizePlate($state))
                    ->mutateStateForValidationUsing(fn (?string $state): string => Truck::normalizePlate((string) $state))
                    ->unique(ignoreRecord: true),
                self::personSelect(true),
                self::personSelect(false)->live(),
                TextInput::make('conductor_name')->label('Conductor informado por GPS o manual')->maxLength(255)->placeholder('Sin conductor informado')->visible(fn (Get $get): bool => blank($get('driver_id')))->helperText('Puede quedar vacío. Un nombre del GPS no confirma la identidad de una persona.'),
                Toggle::make('is_active')->label('Camión activo')->default(true)
                    ->helperText('Inactivar conserva la placa y su información; no elimina el camión.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Datos opcionales')->collapsed()->schema([
                TextInput::make('make')->label('Marca')->maxLength(255),
                TextInput::make('model')->label('Modelo')->maxLength(255),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    private static function personSelect(bool $owner): Select
    {
        $role = $owner ? 'is_owner' : 'is_driver';
        $label = $owner ? 'dueño' : 'conductor';

        return Select::make($owner ? 'owner_id' : 'driver_id')
            ->label($owner ? 'Dueño (opcional)' : 'Conductor de la lista (opcional)')
            ->relationship($owner ? 'owner' : 'driver', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where($role, true))
            ->getOptionLabelFromRecordUsing(fn (FleetPerson $record): string => self::personLabel($record))
            ->searchable(['name', 'document_number'])->preload()->searchPrompt('Escribe un nombre o documento para buscar')
            ->helperText('Busca escribiendo o pulsa + para agregar. '.($owner ? 'El GPS no modifica el dueño.' : 'Selecciona la ficha cuya identidad hayas confirmado.'))
            ->createOptionForm([
                Select::make('existing_person_id')->label('Reutilizar persona registrada')->searchable()->live()
                    ->getSearchResultsUsing(fn (string $search): array => FleetPerson::query()->where(fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%')->orWhere('document_number', 'like', '%'.$search.'%'))->orderBy('name')->limit(50)->get()->mapWithKeys(fn (FleetPerson $person): array => [$person->id => self::personLabel($person)])->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($person = FleetPerson::find($value)) ? self::personLabel($person) : null)
                    ->helperText('Si ya existe como dueño o conductor, selecciónala aquí. Se habilitará el rol de '.$label.' sin duplicar su ficha.'),
                TextInput::make('name')->label('Nombre completo')->required(fn (Get $get): bool => blank($get('existing_person_id')))->maxLength(255)->visible(fn (Get $get): bool => blank($get('existing_person_id'))),
                TextInput::make('document_number')->label('Documento (opcional)')->maxLength(100)->visible(fn (Get $get): bool => blank($get('existing_person_id'))),
                TextInput::make('phone')->label('Teléfono (opcional)')->tel()->maxLength(255)->visible(fn (Get $get): bool => blank($get('existing_person_id'))),
            ])
            ->createOptionUsing(function (array $data, Schema $schema) use ($role, $label): int {
                return DB::transaction(function () use ($data, $schema, $role, $label): int {
                    if (filled($data['existing_person_id'] ?? null)) {
                        $person = FleetPerson::find($data['existing_person_id']);
                        if (! $person) {
                            throw ValidationException::withMessages([$schema->getStatePath().'.existing_person_id' => 'La persona ya no está disponible. Vuelve a buscarla.']);
                        }
                        $person->update([$role => true]);

                        return $person->id;
                    }
                    $key = Truck::normalizePlate($data['name']);
                    if (FleetPerson::get(['name'])->contains(fn (FleetPerson $person): bool => Truck::normalizePlate($person->name) === $key)) {
                        throw ValidationException::withMessages([$schema->getStatePath().'.name' => 'Este nombre ya se encuentra registrado. Busca la persona en «Reutilizar persona registrada» para seleccionarla como '.$label.'.']);
                    }
                    try {
                        return FleetPerson::create(['name' => $data['name'], 'document_number' => $data['document_number'] ?? null, 'phone' => $data['phone'] ?? null, 'is_owner' => $role === 'is_owner', 'is_driver' => $role === 'is_driver', 'is_active' => true])->id;
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([$schema->getStatePath().'.name' => collect($exception->errors())->flatten()->first()]);
                    }
                });
            })
            ->createOptionAction(fn (Action $action): Action => $action->label('Agregar '.$label)->modalHeading('Agregar o seleccionar '.$label)->modalSubmitActionLabel('Usar en este camión')->modalWidth('xl'));
    }

    private static function personLabel(FleetPerson $person): string
    {
        return $person->name.' · '.($person->document_number ?: 'Sin documento').' · #'.$person->id;
    }
}
