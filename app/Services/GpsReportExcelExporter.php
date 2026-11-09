<?php

namespace App\Services;

use App\Models\GpsPosition;
use App\Models\Truck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GpsReportExcelExporter
{
    public function singleTruck(Truck $truck, string $dateFrom, string $dateTo): BinaryFileResponse
    {
        $query = GpsPosition::query()
            ->leftJoin('fleet_imports as source_imports', 'source_imports.id', '=', 'gps_positions.fleet_import_id')
            ->where('gps_positions.truck_id', $truck->id)
            ->where('gps_positions.observed_at', '>=', $dateFrom.' 00:00:00')
            ->where('gps_positions.observed_at', '<', $this->endExclusive($dateTo).' 00:00:00')
            ->whereBetween('gps_positions.latitude', [-90, 90])
            ->whereBetween('gps_positions.longitude', [-180, 180])
            ->select('gps_positions.*', 'source_imports.original_name as report_source_name')
            ->orderBy('gps_positions.observed_at')->orderBy('gps_positions.id');

        $filename = 'reporte-camion-'.$this->safeFilename($truck->plate).'-'.$dateFrom.'-'.$dateTo.'.xlsx';

        return $this->download($filename, function (Writer $writer) use ($query, $truck, $dateFrom, $dateTo): void {
            $writer->getCurrentSheet()->setName('Reporte GPS');
            $this->writeMetadata($writer, 'Reporte GPS por camión', $dateFrom, $dateTo);
            $this->writeRow($writer, ['Placa', 'Conductor informado', 'Fecha y hora GPS', 'Latitud', 'Longitud', 'Evento GPS', 'Velocidad', 'Dirección', 'Lugar cercano', 'Archivo fuente', 'Fila de origen']);

            foreach ($query->cursor() as $position) {
                $details = $position->details ?? [];
                $this->writeRow($writer, [
                    $truck->plate,
                    $details['observed_driver'] ?? $truck->conductor_name ?? '',
                    $position->observed_at->format('d/m/Y H:i:s'),
                    $position->latitude,
                    $position->longitude,
                    $details['event'] ?? '',
                    $details['speed'] ?? '',
                    $details['address'] ?? '',
                    $details['nearest_place'] ?? '',
                    $position->report_source_name ?? '',
                    $position->source_row ?? '',
                ]);
            }
        });
    }

    /** @param Collection<int, Truck> $trucks
     * @param  array<int, array{count:int, first:?string, last:?string}>  $summaries
     */
    public function multipleTrucks(Collection $trucks, array $summaries, string $dateFrom, string $dateTo): BinaryFileResponse
    {
        $truckIds = $trucks->modelKeys();
        $query = GpsPosition::query()
            ->leftJoin('fleet_imports as source_imports', 'source_imports.id', '=', 'gps_positions.fleet_import_id')
            ->join('trucks as report_trucks', 'report_trucks.id', '=', 'gps_positions.truck_id')
            ->whereIn('gps_positions.truck_id', $truckIds)
            ->where('gps_positions.observed_at', '>=', $dateFrom.' 00:00:00')
            ->where('gps_positions.observed_at', '<', $this->endExclusive($dateTo).' 00:00:00')
            ->whereBetween('gps_positions.latitude', [-90, 90])
            ->whereBetween('gps_positions.longitude', [-180, 180])
            ->select('gps_positions.*', 'source_imports.original_name as report_source_name', 'report_trucks.plate as report_plate', 'report_trucks.conductor_name as report_driver')
            ->orderBy('report_trucks.plate')->orderBy('gps_positions.observed_at')->orderBy('gps_positions.id');

        return $this->download('reporte-varios-camiones-'.$dateFrom.'-'.$dateTo.'.xlsx', function (Writer $writer) use ($query, $trucks, $summaries, $dateFrom, $dateTo): void {
            $writer->getCurrentSheet()->setName('Resumen');
            $this->writeMetadata($writer, 'Resumen GPS por camión', $dateFrom, $dateTo);
            $this->writeRow($writer, ['Placa', 'Conductor informado', 'Posiciones válidas', 'Primera observación GPS', 'Última observación GPS']);
            foreach ($trucks as $truck) {
                $summary = $summaries[$truck->id] ?? ['count' => 0, 'first' => null, 'last' => null];
                $this->writeRow($writer, [$truck->plate, $truck->conductor_name ?? '', $summary['count'], $this->formatTimestamp($summary['first']), $this->formatTimestamp($summary['last'])]);
            }

            $writer->addNewSheetAndMakeItCurrent()->setName('Detalle GPS');
            $this->writeMetadata($writer, 'Ubicaciones y eventos GPS', $dateFrom, $dateTo);
            $this->writeRow($writer, ['Placa', 'Conductor informado', 'Fecha y hora GPS', 'Latitud', 'Longitud', 'Evento GPS', 'Velocidad', 'Dirección', 'Lugar cercano', 'Archivo fuente', 'Fila de origen']);
            foreach ($query->cursor() as $position) {
                $details = $position->details ?? [];
                $this->writeRow($writer, [
                    $position->report_plate,
                    $details['observed_driver'] ?? $position->report_driver ?? '',
                    $position->observed_at->format('d/m/Y H:i:s'),
                    $position->latitude,
                    $position->longitude,
                    $details['event'] ?? '',
                    $details['speed'] ?? '',
                    $details['address'] ?? '',
                    $details['nearest_place'] ?? '',
                    $position->report_source_name ?? '',
                    $position->source_row ?? '',
                ]);
            }
        });
    }

    private function download(string $filename, callable $writeWorkbook): BinaryFileResponse
    {
        $path = tempnam(storage_path('app'), 'gps-report-');
        if ($path === false) {
            throw new \RuntimeException('No se pudo preparar el archivo de Excel.');
        }

        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $writeWorkbook($writer);
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
                // Preserve the original export error.
            }
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function writeMetadata(Writer $writer, string $title, string $dateFrom, string $dateTo): void
    {
        $this->writeRow($writer, [$title]);
        $this->writeRow($writer, ['Período GPS', $dateFrom, 'al', $dateTo]);
        $this->writeRow($writer, ['Generado', now('America/La_Paz')->format('d/m/Y H:i:s').' (America/La_Paz)']);
        $this->writeRow($writer, ['Nota', 'La fecha y hora corresponden a la observación GPS; no es seguimiento en tiempo real.']);
        $this->writeRow($writer, []);
    }

    private function writeRow(Writer $writer, array $values): void
    {
        $writer->addRow(Row::fromValues($values));
    }

    private function endExclusive(string $dateTo): string
    {
        return CarbonImmutable::parse($dateTo)->addDay()->toDateString();
    }

    private function formatTimestamp(?string $timestamp): string
    {
        return $timestamp ? CarbonImmutable::parse($timestamp)->format('d/m/Y H:i:s') : '';
    }

    private function safeFilename(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? '', '-') ?: 'camion';
    }
}
