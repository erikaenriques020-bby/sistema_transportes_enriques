<?php

namespace App\Filament\Resources\ReportRecords\Schemas;

use App\Models\ReportRecord;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReportRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        $fields = ReportRecord::fields();

        return $schema->components([
            Section::make('Registro del viaje')->schema(self::inputs(array_slice($fields, 0, 17)))->columns(3)->columnSpanFull(),
            Section::make('Despacho, descarga y diferencias')->schema(self::inputs(array_slice($fields, 17)))->columns(3)->collapsed()->columnSpanFull(),
        ]);
    }

    /** @param array<int, array{index:int, key:string, label:string}> $fields */
    private static function inputs(array $fields): array
    {
        return array_map(fn (array $field): TextInput => TextInput::make('record_data.'.$field['key'])
            ->label($field['label'])
            ->maxLength(1000), $fields);
    }
}
