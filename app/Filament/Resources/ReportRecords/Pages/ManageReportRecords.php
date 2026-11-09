<?php

namespace App\Filament\Resources\ReportRecords\Pages;

use App\Filament\Resources\ReportRecords\ReportRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageReportRecords extends ManageRecords
{
    protected static string $resource = ReportRecordResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'report-records-page'];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo registro')->modalWidth('7xl')->stickyModalHeader()->stickyModalFooter(),
        ];
    }
}
