<?php

namespace Database\Factories;

use App\Models\FleetImport;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

class GpsImportRowFactory extends Factory
{
    public function definition(): array
    {
        return ['fleet_import_id' => FleetImport::factory()->state(['kind' => 'gps']), 'row_number' => fake()->unique()->numberBetween(2, 1000000), 'plate' => 'TEST-GPS', 'truck_id' => Truck::factory(), 'observed_at' => '2026-10-27 09:00:00', 'latitude' => -16.5, 'longitude' => -68.1, 'status' => 'new'];
    }
}
