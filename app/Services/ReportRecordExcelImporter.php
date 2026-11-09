<?php

namespace App\Services;

use App\Models\ReportRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ReportRecordExcelImporter
{
    public function import(string $path, string $filename, string $sheet = 'Det'): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo Excel de origen.');
        }

        $reader = new XlsxReportReader;
        $formulaCache = $this->formulaCache($path, $sheet);
        $hash = hash_file('sha256', $path);
        $columns = ReportRecord::fields();
        $rows = $reader->rows($path, $sheet);
        $header = null;
        $buffer = [];
        $inserted = 0;
        $considered = 0;

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                $header = $row;
                $this->validateHeader($row, $columns);

                continue;
            }

            if (! array_filter($row, fn (string $value): bool => trim($value) !== '')) {
                continue;
            }

            $data = [];
            foreach ($columns as $column) {
                $value = $row[$column['index']] ?? '';
                if (str_starts_with(trim($value), '=')) {
                    $value = $formulaCache[$rowNumber][$column['index']] ?? '';
                }
                $data[$column['key']] = $value;
            }

            $buffer[] = [
                'source_file' => $filename,
                'source_hash' => $hash,
                'source_sheet' => $sheet,
                'source_row' => $rowNumber,
                'record_data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $considered++;

            if (count($buffer) === 500) {
                $inserted += DB::table('report_records')->insertOrIgnore($buffer);
                $buffer = [];
            }
        }

        if ($header === null) {
            throw new RuntimeException('La hoja seleccionada no contiene encabezados.');
        }
        if ($buffer !== []) {
            $inserted += DB::table('report_records')->insertOrIgnore($buffer);
        }

        return ['inserted' => $inserted, 'considered' => $considered, 'already_imported' => $considered - $inserted, 'source_hash' => $hash];
    }

    /** @param array<int, string> $headers
     * @param  array<int, array{index:int, key:string, label:string}>  $columns
     */
    private function validateHeader(array $headers, array $columns): void
    {
        foreach ($columns as $column) {
            $expected = $column['index'] === 9 ? '' : $column['label'];
            if ($column['index'] === 16) {
                $expected = 'FLETE';
            }
            if (in_array($column['index'], [18, 20], true)) {
                $expected = 'FECHA DESCARGUIO';
            }
            if (mb_strtoupper(trim($headers[$column['index']] ?? '')) !== $expected) {
                throw new RuntimeException('La estructura de la hoja DET no coincide con la tabla esperada.');
            }
        }
    }

    /** @return array<int, array<int, string>> Cached results are read only; workbook formulas are never executed. */
    private function formulaCache(string $path, string $sheetName): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el libro Excel.');
        }

        try {
            $workbookXml = $archive->getFromName('xl/workbook.xml');
            $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
            if ($workbookXml === false || $relationshipsXml === false) {
                throw new RuntimeException('El libro Excel no tiene una estructura reconocida.');
            }

            $workbook = simplexml_load_string($workbookXml, options: LIBXML_NONET);
            $relationships = simplexml_load_string($relationshipsXml, options: LIBXML_NONET);
            if (! $workbook || ! $relationships) {
                throw new RuntimeException('No se pudo leer la estructura interna del libro Excel.');
            }

            $relationshipId = null;
            foreach ($workbook->sheets->sheet as $sheet) {
                if ((string) $sheet['name'] === $sheetName) {
                    $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $relationshipId = (string) ($attributes['id'] ?? '');
                    break;
                }
            }
            if (! $relationshipId) {
                throw new RuntimeException('No se encontró la hoja '.$sheetName.' en el libro Excel.');
            }

            $target = null;
            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    $target = (string) $relationship['Target'];
                    break;
                }
            }
            if (! $target) {
                throw new RuntimeException('No se encontró el contenido de la hoja '.$sheetName.'.');
            }

            $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $sheetXml = $archive->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new RuntimeException('No se pudo leer la hoja '.$sheetName.'.');
            }

            $cache = [];
            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $sheetXml, $cells, PREG_SET_ORDER);
            foreach ($cells as $cell) {
                if (! preg_match('/\br="([A-Z]+)(\d+)"/', $cell[1], $reference)
                    || ! preg_match('/<f(?:\s[^>]*)?(?:\/\s*>|>.*?<\/f\s*>)/s', $cell[2])
                    || ! preg_match('/<v(?:\s[^>]*)?>(.*?)<\/v\s*>/s', $cell[2], $value)) {
                    continue;
                }

                $rowNumber = (int) $reference[2];
                $columnIndex = $this->columnIndex($reference[1]);
                $cache[$rowNumber][$columnIndex] = html_entity_decode($value[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            return $cache;
        } finally {
            $archive->close();
        }
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }
}
