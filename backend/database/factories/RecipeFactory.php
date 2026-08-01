<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => fn () => Product::create([
                'name' => fake()->unique()->word(),
                'base_price' => 10000,
                'is_active' => true,
            ])->id,
            'recipe_name' => fake()->words(3, true),
        ];
    }
}
