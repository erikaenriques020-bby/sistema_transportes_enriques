<?php

namespace Database\Factories;

use App\Models\ReportRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportRecord>
 */
class ReportRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_file' => null,
            'source_hash' => null,
            'source_sheet' => null,
            'source_row' => null,
            'record_data' => [
                'cod' => (string) fake()->unique()->numberBetween(1, 9999),
                'fecha' => fake()->date('Y-m-d'),
                'planta' => fake()->company(),
                'cliente' => fake()->company(),
                'placa_nacional' => strtoupper(fake()->bothify('####???')),
            ],
        ];
    }
}
