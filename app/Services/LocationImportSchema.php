<?php

namespace App\Services;

use Illuminate\Support\Str;

class LocationImportSchema
{
    public const GPS_DETAILS = [
        'gps_asset_identifier' => ['Identificador del activo GPS', ['activo', 'id activo']],
        'observed_driver' => ['Conductor informado por GPS', ['conductor', 'chofer']],
        'address' => ['Dirección GPS', ['direccion', 'address']],
        'heading' => ['Orientación', ['orientacion', 'rumbo']],
        'speed' => ['Velocidad informada', ['velocidad', 'speed']],
        'event' => ['Evento', ['evento', 'event']],
        'odometer' => ['Odómetro informado', ['odometro', 'odometer']],
        'nearest_place' => ['Lugar más cercano', ['lugar mas cercano']],
        'temperature' => ['Temperatura', ['temperatura']],
        'temperature2' => ['Temperatura 2', ['temperatura2', 'temperatura 2']],
        'map_link' => ['Referencia de mapa del archivo', ['ver mapa']],
        'buffered' => ['Buffer', ['buffer']],
        'received_at' => ['TimeStamp de recepción (no fecha GPS)', ['timestamp', 'fecha recepcion']],
        'geofence' => ['Geocerca informada', ['geocerca']],
    ];

    public static function fields(string $target): array
    {
        if ($target === 'places') {
            return [
                'name' => ['Nombre del lugar *', ['nombre del lugar', 'nombre', 'lugar', 'name']],
                'place_group' => ['Grupo', ['grupo', 'group']],
                'icon' => ['Icono del GPS', ['icono', 'icon']],
                'latitude' => ['Latitud *', ['latitud', 'latitude', 'lat']],
                'longitude' => ['Longitud *', ['longitud', 'longitude', 'lon', 'lng']],
                'phone' => ['Teléfono', ['telefono', 'phone']],
                'email' => ['Correo electrónico', ['email', 'correo', 'correo electronico']],
                'description' => ['Descripción', ['descripcion', 'description']],
                'address' => ['Dirección', ['direccion', 'address']],
            ];
        }

        return [
            'plate' => ['Placa *', ['placa', 'plate', 'matricula']],
            'date' => ['Fecha GPS', ['fecha', 'date']],
            'time' => ['Hora GPS', ['hora', 'time']],
            'datetime' => ['Fecha y hora GPS en una columna', ['fecha y hora', 'fecha hora', 'datetime', 'instante']],
            'latitude' => ['Latitud *', ['latitud', 'latitude', 'lat']],
            'longitude' => ['Longitud *', ['longitud', 'longitude', 'lng', 'lon']],
            ...self::GPS_DETAILS,
        ];
    }

    public static function guess(string $target, array $columns, array $profile = []): array
    {
        $fields = self::fields($target);
        $mapping = array_fill_keys(array_keys($fields), '');
        foreach ($columns as $column) {
            $signature = $column['index'].':'.$column['name'];
            $name = Str::lower(Str::ascii(trim($column['name'])));
            $field = array_key_exists($signature, $profile) ? $profile[$signature] : null;
            if (! array_key_exists($signature, $profile)) {
                foreach ($fields as $key => [, $aliases]) {
                    if (in_array($name, $aliases, true)) {
                        $field = $key;
                        break;
                    }
                }
            }
            if ($target === 'gps' && $name === 'timestamp' && $field === 'datetime') {
                $field = 'received_at';
            }
            if ($field && isset($fields[$field]) && $mapping[$field] === '') {
                $mapping[$field] = (string) $column['index'];
            }
        }
        if ($target === 'gps' && $mapping['date'] !== '' && $mapping['time'] !== '') {
            $mapping['datetime'] = '';
        }

        return $mapping;
    }

    public static function suggestedTarget(array $columns): ?string
    {
        $names = array_map(fn ($column): string => Str::lower(Str::ascii(trim($column['name']))), $columns);
        if (array_intersect($names, ['placa', 'plate', 'matricula'])) {
            return 'gps';
        }

        return array_intersect($names, ['nombre del lugar', 'nombre', 'name']) && array_intersect($names, ['latitud', 'latitude', 'lat']) ? 'places' : null;
    }

    public static function coordinate(string $value, int $maximum): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $value) || ! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        $rounded = round($number, 7);

        return is_finite($number) && $number >= -$maximum && $number <= $maximum ? number_format($rounded == 0.0 ? 0.0 : $rounded, 7, '.', '') : null;
    }
}
