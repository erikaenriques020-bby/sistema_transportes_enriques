<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $places = [
            ['VILLA TUNARI', 'black01', '-16.975012', '-65.419836', null, 'RUTA NACIONAL 4', 'VILLA TUNARI'],
            ['IVIRGARZAMA', 'black01', '-17.034898', '-64.871367', null, 'RUTA NACIONAL 4/15', 'IVIRGARZAMA'],
            ['CHIMORE', 'black01', '-16.996321', '-65.155618', null, 'RUTA NACIONAL 4', 'CHIMORE'],
            ['BULO0 BULO', 'black01', '-17.253521', '-64.362057'],
            ['EL PALMAR', 'black01', '-17.368172', '-64.038592'],
            ['BUENA VISTA', 'black01', '-17.448784', '-63.679935'],
            ['PORTACHUELO', 'black01', '-17.361437', '-63.410678'],
            ['MONTERO', 'black01', '-17.318649', '-63.269816'],
            ['VILLA TUNARI', 'black01', '-16.973521', '-65.415981'],
            ['CRISTAL MAYU', 'black01', '-17.007461', '-65.643633'],
            ['CORANI', 'black01', '-17.20452', '-65.891005'],
            ['COLOMI', 'black01', '-17.331491', '-65.87094'],
            ['SACABA', 'black01', '-17.400236', '-66.038574'],
            ['QUILLACOLLO', 'black01', '-17.394245', '-66.282439'],
            ['SUTICOLLO', 'black01', '-17.471908', '-66.338946'],
            ['PAROTANI', 'black01', '-17.567326', '-66.347374'],
            ['CONFITAL', 'black01', '-17.679751', '-66.72981'],
            ['PONGO', 'black01', '-17.712224', '-66.552663'],
            ['WARNES', 'black01', '-17.50502', '-63.170377'],
            ['CARACOLLO', 'black01', '-17.650801', '-67.210168'],
            ['SICA SICA', 'black01', '-17.333422', '-67.744478'],
            ['PATACAMAYA', 'black01', '-17.251656', '-67.891088'],
            ['THOLAR', 'black01', '-17.010803', '-68.067163'],
            ['CALAMARCA', 'black01', '-16.909235', '-68.119353'],
            ['ACHICA', 'black01', '-16.712915', '-68.186103'],
            ['APACHETA', 'black01', '-16.654793', '-68.179851'],
            ['AQUALAND', 'black01', '-17.642227', '-63.157515'],
            ['EMPRESA GRANOS', 'new_234_building', '-17.764437', '-63.082834'],
            ['EMPRESA GRANOS PARQUEO1/2', 'new_234_building', '-17.759788', '-63.08385'],
            ['EMPRESA GRANOS PARQUEO 2/2', 'new_234_building', '-17.760753', '-63.083461'],
            ['AV. SANTA FE', 'black04', '-16.498428', '-68.230184'],
            ['EMPRESA QUIPUS', 'black04', '-16.521627', '-68.29669'],
            ['LAJA', 'black04', '-16.531279', '-68.383927'],
            ['TAMBILLO', 'black04', '-16.575937', '-68.506187'],
            ['PUENTE GUAQUIRA', 'black04', '-16.589443', '-68.565961'],
            ['TIAHUANACU', 'black04', '-16.57115', '-68.672794'],
            ['GUAQUI', 'black04', '-16.599992', '-68.837182'],
            ['CURVA DESAGUADERO', 'black04', '-16.554201', '-69.027801'],
            ['E.S. DESAGUADERO', 'black04', '-16.569098', '-69.028867'],
            ['FILA DESAGUADERO', 'black04', '-16.573567', '-69.029431'],
            ['FRONTERA BOLIVIA - PERU', 'AlarmaON.gif', '-16.57958511278023', '-69.03051077511597'],
            ['FRONTERA PERU - BOLIVIA', 'AlarmaON.gif', '-16.580068', '-69.037334'],
            ['TERMINAL AGERSA', 'new_786_revision-enterprise_', '-16.575258', '-69.048845'],
            ['TERMINAL ANDESGATE', 'new_786_revision-enterprise_', '-16.580313', '-69.044745'],
            ['PLANTA RICO', 'cluster3', '-17.756532', '-63.138864', '33461035', 'INDUSTRIAS OLEAGINOSAS', 'INDUSTRIAS OLEAGINOSAS SA'],
            ['GRAN ALIMENTO', 'new_234_building', '-17.762587', '-63.02784', null, 'CARGA', '-17.762587, -63.02784'],
            ['CAICO', 'new_234_building', '-17.222667', '-62.903609', null, null, '-17.222675,-62.903258'],
            ['PROLEGA', 'new_234_building', '-17.314358', '-63.253785', null, null, '-17.313802,-63.254407'],
            ['AGROTRAM', 'new_234_building', '-17.315429896998666', '-63.24620804625635', null, null, '-17.314525,-63.246747'],
            ['PUERTO SUREZ', 'Alarma', '-18.986577', '-57.829706', null, null, 'PUERTO SUAREZ'],
            ['INSER', 'new_234_building', '-17.719752527653466', '-62.89514067805215', null, null, '-17.764313,-62.996822'],
        ];

        foreach ($places as $place) {
            [$name, $icon, $latitude, $longitude, $phone, $description, $address] = array_pad($place, 7, null);

            Place::firstOrCreate(
                [
                    'name' => $name,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
                [
                    'icon' => $icon,
                    'phone' => $phone,
                    'description' => $description,
                    'address' => $address,
                ],
            );
        }
    }
}
