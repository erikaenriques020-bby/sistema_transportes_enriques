<?php

namespace App\Services;

use DateTimeInterface;
use Generator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class XlsxReportReader
{
    private function open(string $path): Reader
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($path);

        return $reader;
    }

    public function sheets(string $path): array
    {
        $reader = $this->open($path);
        try {
            $names = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();
            }

            return $names;
        } finally {
            $reader->close();
        }
    }

    /** @return Generator<int, array<int, string>> Row numbers are one-based. */
    public function rows(string $path, string $sheetName): Generator
    {
        $reader = $this->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $sheetName) {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $number => $row) {
                    yield $number => array_map(static fn ($value): string => $value instanceof DateTimeInterface
                        ? $value->format('Y-m-d H:i:s') : (is_scalar($value) ? trim((string) $value) : ''), $row->toArray());
                }

                return;
            }
            throw new RuntimeException('No se encontró la hoja seleccionada.');
        } finally {
            $reader->close();
        }
    }

    public function inspect(string $path, ?string $sheetName = null, ?int $headerRow = null): array
    {
        $names = $this->sheets($path);
        $sheetName = $sheetName ?: (in_array('Activos', $names, true) ? 'Activos' : ($names[0] ?? ''));
        if ($headerRow === null) {
            $first = null;
            $bestScore = 0;
            foreach ($this->rows($path, $sheetName) as $number => $row) {
                if ($number > 50) {
                    break;
                }
                if (array_filter($row, fn (string $value): bool => $value !== '')) {
                    $first ??= $number;
                }
                $normalized = array_map(fn (string $value): string => Str::lower(Str::ascii(trim($value))), $row);
                $score = (array_intersect($normalized, ['placa', 'plate', 'matricula']) ? 20 : 0)
                    + (array_intersect($normalized, ['latitud', 'latitude', 'lat']) && array_intersect($normalized, ['longitud', 'longitude', 'lon', 'lng']) ? 10 : 0)
                    + (array_intersect($normalized, ['conductor', 'conductores', 'chofer', 'nombre', 'nombre completo', 'nombre del lugar']) ? 5 : 0);
                if ($score > $bestScore) {
                    $headerRow = $number;
                    $bestScore = $score;
                }
            }
            $headerRow ??= $first;
        }
        if (! $headerRow) {
            throw new RuntimeException('No hay encabezados.');
        }
        $columns = [];
        $count = 0;
        foreach ($this->rows($path, $sheetName) as $number => $row) {
            if ($number < $headerRow) {
                continue;
            }
            if ($number === $headerRow) {
                foreach ($row as $index => $name) {
                    if ($name !== '') {
                        $columns[] = ['index' => $index, 'name' => $name, 'sample' => [], 'empty' => 0];
                    }
                }

                continue;
            }
            if (! array_filter($row, fn (string $value): bool => $value !== '')) {
                continue;
            }
            $count++;
            foreach ($columns as &$column) {
                $value = $row[$column['index']] ?? '';
                if ($value === '') {
                    $column['empty']++;
                } elseif (count($column['sample']) < 3 && ! in_array($value, $column['sample'], true)) {
                    $column['sample'][] = $value;
                }
            }
            unset($column);
        }
        if (! $columns) {
            throw new RuntimeException('La fila indicada no contiene encabezados.');
        }

        return ['sheet' => $sheetName, 'sheets' => $names, 'header_row' => $headerRow, 'data_rows' => $count, 'columns' => $columns];
    }
}
