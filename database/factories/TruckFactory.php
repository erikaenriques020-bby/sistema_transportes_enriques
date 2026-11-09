<?php

namespace Database\Factories;

use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Truck>
 */
class TruckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => fake()->unique()->bothify('####???'),
            'gps_asset_identifier' => null,
            'make' => null,
            'model' => null,
            'owner_id' => null,
            'driver_id' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
