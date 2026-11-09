<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ModulePlaceholder extends Page
{
    protected static ?string $slug = 'menu';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.module-placeholder';

    #[Url]
    public string $section = '';

    /** @var array<string, array{title: string, description: string}> */
    private const SECTIONS = [
        'importaciones' => [
            'title' => 'Importaciones',
            'description' => 'Aquí organizaremos la carga y el historial de los archivos Excel y CSV de i-monnet.',
        ],
        'cargar-archivo' => [
            'title' => 'Cargar archivo',
            'description' => 'Aquí se preparará la carga de archivos GPS, con validación del tipo de reporte y su estructura.',
        ],
        'historial-importaciones' => [
            'title' => 'Historial de importaciones',
            'description' => 'Aquí se consultarán los archivos recibidos, sus períodos y resultados de procesamiento.',
        ],
        'calidad-datos' => [
            'title' => 'Calidad de datos',
            'description' => 'Aquí se mostrarán filas incompletas, formatos inesperados y otros problemas detectados al importar.',
        ],
        'mapa-posiciones' => [
            'title' => 'Mapa y posiciones',
            'description' => 'Aquí se consultarán las posiciones contenidas en los archivos GPS importados.',
        ],
        'eventos-gps' => [
            'title' => 'Eventos GPS',
            'description' => 'Aquí se revisarán los eventos reportados por i-monnet, conservando su clasificación original.',
        ],
        'incidencias-alertas' => [
            'title' => 'Incidencias y alertas',
            'description' => 'Este módulo se habilitará cuando Logística defina qué eventos requieren atención y con qué criterios.',
        ],
        'resumen-seguimiento' => [
            'title' => 'Resumen de seguimiento',
            'description' => 'Aquí se preparará un resumen del período importado, indicando siempre el archivo fuente.',
        ],
        'ubicaciones-recorridos' => [
            'title' => 'Ubicaciones y recorridos GPS',
            'description' => 'Aquí se consultarán ubicaciones y recorridos disponibles según los archivos importados.',
        ],
        'reporte-eventos' => [
            'title' => 'Eventos GPS por unidad y período',
            'description' => 'Aquí se organizarán consultas de eventos filtradas por unidad y rango de fechas.',
        ],
        'exportar-reportes' => [
            'title' => 'Exportar reportes',
            'description' => 'Aquí se definirán las descargas de reportes cuando se acuerden los formatos de salida.',
        ],
        'programaciones' => [
            'title' => 'Programaciones',
            'description' => 'Aquí se organizarán las instrucciones logísticas asignadas por cliente.',
        ],
        'parciales' => [
            'title' => 'Parciales',
            'description' => 'Aquí se organizarán los parciales vinculados a las programaciones.',
        ],
        'viajes' => [
            'title' => 'Viajes',
            'description' => 'Aquí se registrarán los viajes y sus etapas cuando se definan con Logística.',
        ],
        'recepcion-documental' => [
            'title' => 'Recepción documental',
            'description' => 'Aquí se organizará la recepción de documentos al retorno de cada viaje.',
        ],
        'unidades' => [
            'title' => 'Unidades',
            'description' => 'Aquí se administrará la flota, que puede cambiar con la incorporación de camiones.',
        ],
        'identificadores-gps' => [
            'title' => 'Identificadores GPS',
            'description' => 'Aquí se relacionarán las unidades con los identificadores usados por i-monnet.',
        ],
        'conductores' => [
            'title' => 'Conductores',
            'description' => 'Aquí se organizará el catálogo de conductores y sus relaciones con la operación.',
        ],
        'clientes' => [
            'title' => 'Clientes',
            'description' => 'Aquí se administrará el catálogo de clientes atendidos por la empresa.',
        ],
        'productos' => [
            'title' => 'Productos',
            'description' => 'Aquí se organizará el catálogo de productos transportados.',
        ],
        'usuarios-roles' => [
            'title' => 'Usuarios y roles',
            'description' => 'Aquí se administrarán usuarios y, cuando se definan, sus roles y permisos.',
        ],
        'configuracion' => [
            'title' => 'Configuración',
            'description' => 'Aquí se organizarán las opciones generales que se definan para el sistema.',
        ],
    ];

    public function mount(): void
    {
        $this->section = (string) request()->query('section', $this->section);

        abort_unless(array_key_exists($this->section, self::SECTIONS), 404);
    }

    public function getTitle(): string
    {
        return self::SECTIONS[$this->section]['title'] ?? 'Sección del sistema';
    }

    public function getDescription(): string
    {
        return self::SECTIONS[$this->section]['description'] ?? 'Esta opción forma parte de la organización del sistema.';
    }
}
