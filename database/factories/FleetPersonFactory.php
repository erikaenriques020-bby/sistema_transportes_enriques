<?php

namespace Database\Factories;

use App\Models\FleetPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetPerson>
 */
class FleetPersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document_number' => fake()->unique()->numerify('########'),
            'phone' => fake()->phoneNumber(),
            'is_owner' => false,
            'is_driver' => true,
            'owner_id' => null,
            'is_active' => true,
        ];
    }
}
