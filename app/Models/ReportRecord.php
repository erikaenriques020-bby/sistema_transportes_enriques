<?php

namespace App\Models;

use Database\Factories\ReportRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportRecord extends Model
{
    /** @use HasFactory<ReportRecordFactory> */
    use HasFactory;

    protected $fillable = ['source_file', 'source_hash', 'source_sheet', 'source_row', 'record_data'];

    protected function casts(): array
    {
        return ['source_row' => 'integer', 'record_data' => 'array'];
    }

    /** @return array<int, array{index:int, key:string, label:string}> */
    public static function fields(): array
    {
        return [
            ['index' => 0, 'key' => 'cod', 'label' => 'COD'],
            ['index' => 1, 'key' => 'doc', 'label' => 'DOC'],
            ['index' => 2, 'key' => 'fecha', 'label' => 'FECHA'],
            ['index' => 3, 'key' => 'planta', 'label' => 'PLANTA'],
            ['index' => 4, 'key' => 'cliente', 'label' => 'CLIENTE'],
            ['index' => 5, 'key' => 'fac', 'label' => 'FAC'],
            ['index' => 6, 'key' => 'flete', 'label' => 'FLETE'],
            ['index' => 7, 'key' => 'cod2', 'label' => 'COD2'],
            ['index' => 8, 'key' => 'placa_nacional', 'label' => 'PLACA N'],
            ['index' => 9, 'key' => 'conductor_nacional', 'label' => 'COLUMNA J (sin encabezado en el Excel)'],
            ['index' => 10, 'key' => 'ci_nacional', 'label' => 'CI N'],
            ['index' => 11, 'key' => 'celular_nacional', 'label' => 'CELULAR'],
            ['index' => 12, 'key' => 'peso_bruto', 'label' => 'PESO BRUTO'],
            ['index' => 13, 'key' => 'peso_neto', 'label' => 'PESO NETO'],
            ['index' => 14, 'key' => 'sacos', 'label' => 'SACOS'],
            ['index' => 15, 'key' => 'parcial', 'label' => 'PARCIAL'],
            ['index' => 16, 'key' => 'flete_2', 'label' => 'FLETE (segunda columna)'],
            ['index' => 17, 'key' => 'fecha_carguio', 'label' => 'FECHA CARGUIO'],
            ['index' => 18, 'key' => 'fecha_descarguio_1', 'label' => 'FECHA DESCARGUIO (primera columna)'],
            ['index' => 19, 'key' => 'fecha_despacho', 'label' => 'FECHA DESPACHO'],
            ['index' => 20, 'key' => 'fecha_descarguio_2', 'label' => 'FECHA DESCARGUIO (segunda columna)'],
            ['index' => 21, 'key' => 'factura_despacho', 'label' => 'FACTURA QUE SE DESPACHO'],
            ['index' => 22, 'key' => 'sacos_faltantes', 'label' => 'SACOS FALTANTES'],
            ['index' => 23, 'key' => 'sacos_sobrantes', 'label' => 'SACOS SOBRANTES'],
            ['index' => 24, 'key' => 'sacos_rotos', 'label' => 'SACOS ROTOS'],
            ['index' => 25, 'key' => 'sacos_mojados', 'label' => 'SACOS MOJADOS'],
            ['index' => 26, 'key' => 'sacos_bajados', 'label' => 'SACOS BAJADOS'],
            ['index' => 27, 'key' => 'sacos_aumentados', 'label' => 'SACOS AUMENTADOS'],
            ['index' => 28, 'key' => 'transbordo_descarguio', 'label' => 'TRANSBORDO O DESCARGUIO'],
            ['index' => 29, 'key' => 'conductor_internacional', 'label' => 'CONDUCTOR I'],
            ['index' => 30, 'key' => 'ci_internacional', 'label' => 'CI I'],
            ['index' => 31, 'key' => 'adicional', 'label' => 'ADICIONAL'],
            ['index' => 32, 'key' => 'celular_internacional', 'label' => 'CELULAR I'],
            ['index' => 33, 'key' => 'tm_terminal', 'label' => 'TM TERMINAL'],
            ['index' => 34, 'key' => 'fecha_finalizada', 'label' => 'FECHA FINALIZADA'],
        ];
    }
}
