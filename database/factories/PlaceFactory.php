<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'place_group' => null,
            'icon' => null,
            'latitude' => fake()->latitude(-22, -9),
            'longitude' => fake()->longitude(-70, -57),
            'phone' => null,
            'email' => null,
            'description' => null,
            'address' => null,
        ];
    }
}
