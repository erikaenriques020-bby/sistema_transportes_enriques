<?php

namespace App\Filament\Resources\Places\Schemas;

use App\Models\Place;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del lugar')
                    ->required()
                    ->rules([fn (Get $get, ?Place $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if (is_numeric($get('latitude')) && is_numeric($get('longitude')) && Place::where('import_key', Place::identityKey($value, $get('latitude'), $get('longitude')))->whereKeyNot($record?->id ?? 0)->exists()) {
                            $fail('Ya existe este lugar con el mismo nombre y coordenadas.');
                        }
                    }])
                    ->maxLength(255),
                TextInput::make('place_group')
                    ->label('Grupo')
                    ->maxLength(255),
                TextInput::make('icon')
                    ->label('Icono del GPS')
                    ->maxLength(255),
                TextInput::make('latitude')
                    ->label('Latitud')
                    ->required()
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->step('any'),
                TextInput::make('longitude')
                    ->label('Longitud')
                    ->required()
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->step('any'),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Textarea::make('address')
                    ->label('Dirección del archivo GPS')
                    ->columnSpanFull(),
            ]);
    }
}
