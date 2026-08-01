<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'raw_material_id' => fn () => RawMaterial::create([
                'name' => fake()->unique()->word(),
                'stock_quantity' => 100,
                'unit' => 'gram',
            ])->id,
            'quantity' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
