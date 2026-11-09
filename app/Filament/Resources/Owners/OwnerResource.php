<?php

namespace App\Filament\Resources\Owners;

use App\Filament\Resources\FleetPeople\Schemas\FleetPersonForm;
use App\Filament\Resources\FleetPeople\Tables\FleetPeopleTable;
use App\Filament\Resources\Owners\Pages\CreateOwner;
use App\Filament\Resources\Owners\Pages\EditOwner;
use App\Filament\Resources\Owners\Pages\ListOwners;
use App\Models\FleetPerson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OwnerResource extends Resource
{
    protected static ?string $model = FleetPerson::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Dueños';

    protected static ?string $modelLabel = 'dueño';

    protected static ?string $pluralModelLabel = 'dueños';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->owners();
    }

    public static function form(Schema $schema): Schema
    {
        return FleetPersonForm::configure($schema, ownerMode: true);
    }

    public static function table(Table $table): Table
    {
        return FleetPeopleTable::configure($table, ownerMode: true);
    }

    public static function getPages(): array
    {
        return ['index' => ListOwners::route('/'), 'create' => CreateOwner::route('/create'), 'edit' => EditOwner::route('/{record}/edit')];
    }
}
