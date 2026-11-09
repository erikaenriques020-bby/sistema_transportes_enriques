<?php

namespace App\Filament\Resources\FleetPeople;

use App\Filament\Resources\FleetPeople\Pages\CreateFleetPerson;
use App\Filament\Resources\FleetPeople\Pages\EditFleetPerson;
use App\Filament\Resources\FleetPeople\Pages\ListFleetPeople;
use App\Filament\Resources\FleetPeople\Schemas\FleetPersonForm;
use App\Filament\Resources\FleetPeople\Tables\FleetPeopleTable;
use App\Models\FleetPerson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FleetPersonResource extends Resource
{
    protected static ?string $model = FleetPerson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Conductores';

    protected static ?string $slug = 'conductores';

    protected static ?string $modelLabel = 'conductor';

    protected static ?string $pluralModelLabel = 'conductores';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->drivers();
    }

    public static function form(Schema $schema): Schema
    {
        return FleetPersonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FleetPeopleTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFleetPeople::route('/'),
            'create' => CreateFleetPerson::route('/create'),
            'edit' => EditFleetPerson::route('/{record}/edit'),
        ];
    }
}
