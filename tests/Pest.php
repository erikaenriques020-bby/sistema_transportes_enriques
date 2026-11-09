<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function fleetTestUpload(array $rows, array $headers = ['Placa', 'Conductor'], int $headerRow = 1, bool $sharedStrings = false): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'fleet-sheet');
    $options = new Options;
    $options->SHOULD_USE_INLINE_STRINGS = ! $sharedStrings;
    $writer = new Writer($options);
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Activos');
    for ($number = 1; $number < $headerRow; $number++) {
        $writer->addRow(Row::fromValues($number === 1 ? ['Reporte de prueba'] : []));
    }
    $writer->addRow(Row::fromValues($headers));
    foreach ($rows as $row) {
        $styles = [];
        foreach ($row as $index => $value) {
            if ($value instanceof DateTimeInterface) {
                $styles[$index] = (new Style)->setFormat('yyyy-mm-dd hh:mm:ss');
            }
        }
        $writer->addRow(Row::fromValuesWithStyles($row, columnStyles: $styles));
    }
    $writer->close();
    $contents = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('camiones.xlsx', $contents);
}
