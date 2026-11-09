<?php

namespace Database\Factories;

use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

class GpsPositionFactory extends Factory
{
    public function definition(): array
    {
        return ['truck_id' => Truck::factory(), 'observed_at' => fake()->dateTimeBetween('-7 days', 'now'), 'latitude' => fake()->latitude(-22, -10), 'longitude' => fake()->longitude(-69, -58)];
    }
}
