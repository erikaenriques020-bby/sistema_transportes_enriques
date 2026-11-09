<?php

namespace Database\Seeders;

use App\Services\ReportRecordExcelImporter;
use Illuminate\Database\Seeder;

class ReportRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = storage_path('app/private/report-records-source.xlsx');
        if (! is_file($path)) {
            return;
        }

        app(ReportRecordExcelImporter::class)->import($path, basename($path), 'Det');
    }
}
