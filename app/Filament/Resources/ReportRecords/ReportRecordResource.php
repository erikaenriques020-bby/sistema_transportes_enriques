<?php

namespace App\Filament\Resources\ReportRecords;

use App\Filament\Resources\ReportRecords\Pages\ManageReportRecords;
use App\Filament\Resources\ReportRecords\Schemas\ReportRecordForm;
use App\Filament\Resources\ReportRecords\Tables\ReportRecordsTable;
use App\Models\ReportRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReportRecordResource extends Resource
{
    protected static ?string $model = ReportRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Registros';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'registros';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ReportRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReportRecords::route('/'),
        ];
    }
}
