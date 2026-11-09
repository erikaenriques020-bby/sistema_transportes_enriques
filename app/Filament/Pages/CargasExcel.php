<?php

namespace App\Filament\Pages;

use App\Models\FleetImport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;

class CargasExcel extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Cargas de Excel';

    protected static ?string $navigationLabel = 'Cargas de Excel';

    protected string $view = 'filament.pages.cargas-excel';

    protected function getViewData(): array
    {
        return ['imports' => FleetImport::latest('id')->paginate(20)];
    }
}
