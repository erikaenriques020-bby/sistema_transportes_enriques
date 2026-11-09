<?php

namespace Database\Factories;

use App\Models\FleetImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetImport>
 */
class FleetImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sha256' => hash('sha256', fake()->uuid()),
            'original_name' => 'seguimiento.xlsx',
            'source_path' => 'fleet-imports/test.xlsx',
            'status' => 'pending',
            'preview' => ['trucks' => [], 'drivers' => [], 'invalid' => []],
        ];
    }
}
