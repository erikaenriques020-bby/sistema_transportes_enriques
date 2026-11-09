<?php

namespace App\Filament\Resources\FleetPeople\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FleetPersonForm
{
    public static function configure(Schema $schema, bool $ownerMode = false): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la persona')
                    ->schema([
                        TextInput::make('name')->label('Nombre completo')->required()->maxLength(255),
                        TextInput::make('document_number')->label('Carnet o documento')->maxLength(100)->unique(ignoreRecord: true),
                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(255),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Roles en la flota')
                    ->description('Una persona puede ser dueño, chofer o cumplir ambos roles.')
                    ->schema([
                        Toggle::make('is_owner')->label('Es dueño')->default($ownerMode)->helperText('Puede tener varios camiones.')->live(),
                        Toggle::make('is_driver')->label('Es conductor')->default(! $ownerMode)->helperText('Puede tener ambos roles en la misma ficha.')->live(),
                    ])->columns(2)->columnSpanFull(),
                Toggle::make('is_active')->label('Registro activo')->default(true)->required(),
            ]);
    }
}
