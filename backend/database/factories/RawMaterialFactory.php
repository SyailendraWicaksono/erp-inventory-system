<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'stock_quantity' => 100,
            'unit' => 'gram',
        ];
    }
}
