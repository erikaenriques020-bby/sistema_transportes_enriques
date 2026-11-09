<?php

namespace App\Filament\Resources\ReportRecords\Tables;

use App\Filament\Resources\ReportRecords\ReportRecordResource;
use App\Filament\Resources\ReportRecords\Schemas\ReportRecordForm;
use App\Models\ReportRecord;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class ReportRecordsTable
{
    private const SEARCHABLE_FIELDS = ['cod', 'fecha', 'cliente', 'fac', 'placa_nacional', 'conductor_nacional'];

    public static function configure(Table $table): Table
    {
        $columns = array_map(function (array $field): TextInputColumn {
            $key = $field['key'];
            $column = TextInputColumn::make($key)
                ->label($field['label'])
                ->state(fn (ReportRecord $record): string => (string) ($record->record_data[$field['key']] ?? ''))
                ->placeholder('—')
                ->rules(['nullable', 'string', 'max:1000'])
                ->extraInputAttributes(['aria-label' => $field['label']])
                ->disabled(fn (ReportRecord $record): bool => ! ReportRecordResource::canEdit($record))
                ->updateStateUsing(function (ReportRecord $record, ?string $state) use ($key): ?string {
                    abort_unless(ReportRecordResource::canEdit($record), 403);
                    Validator::make(['value' => $state], ['value' => ['nullable', 'string', 'max:1000']])->validate();
                    $data = $record->record_data ?? [];
                    $data[$key] = $state ?? '';
                    $record->record_data = $data;
                    $record->save();

                    return $state;
                });

            if (in_array($field['key'], self::SEARCHABLE_FIELDS, true)) {
                $column->searchable(query: fn (Builder $query, string $search): Builder => $query->where('record_data->'.$field['key'], 'like', '%'.$search.'%'));
            }

            if (in_array($field['key'], ['cod', 'fecha', 'cliente', 'placa_nacional'], true)) {
                $key = $field['key'];
                $column->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('record_data->'.$key, $direction));
            }

            return $column;
        }, ReportRecord::fields());

        return $table->columns([
            ...$columns,
            TextColumn::make('source_file')->label('Archivo fuente')->placeholder('Registro manual')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('source_row')->label('Fila del Excel')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ])
            ->recordUrl(null)->recordAction('edit')
            ->recordActions([
                EditAction::make()->modal()->schema(fn (Schema $schema): Schema => ReportRecordForm::configure($schema))
                    ->modalWidth('7xl')->stickyModalHeader()->stickyModalFooter()
                    ->icon('heroicon-o-pencil-square')->successNotificationTitle('Registro actualizado'),
            ])
            ->toolbarActions([])
            ->paginated([100, 250, 500, 1000])
            ->defaultPaginationPageOption(250)
            ->defaultSort('source_row');
    }
}
