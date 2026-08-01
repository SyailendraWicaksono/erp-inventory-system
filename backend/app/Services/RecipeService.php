<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function getAll(int $productId): Collection
    {
        Product::findOrFail($productId);

        return Recipe::with('recipeDetails.rawMaterial')
            ->where('product_id', $productId)
            ->latest()
            ->get();
    }

    public function getById(int $productId, int $recipeId): Recipe
    {
        return Recipe::with('recipeDetails.rawMaterial')
            ->where('id', $recipeId)
            ->where('product_id', $productId)
            ->firstOrFail();
    }

    public function create(int $productId, array $data): Recipe
    {
        return DB::transaction(function () use ($productId, $data) {
            $product = Product::findOrFail($productId);

            $recipe = $product->recipes()->create([
                'recipe_name' => $data['recipe_name'],
            ]);

            foreach ($data['recipe_details'] as $detail) {
                $recipe->recipeDetails()->create([
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                ]);
            }

            return $recipe->load('recipeDetails.rawMaterial');
        });
    }

    public function update(int $productId, int $recipeId, array $data): Recipe
    {
        return DB::transaction(function () use ($productId, $recipeId, $data) {
            $recipe = $this->getById($productId, $recipeId);

            $recipe->update([
                'recipe_name' => $data['recipe_name'],
            ]);

            $recipe->recipeDetails()->delete();

            foreach ($data['recipe_details'] as $detail) {
                $recipe->recipeDetails()->create([
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                ]);
            }

            return $recipe->load('recipeDetails.rawMaterial');
        });
    }

    public function delete(int $productId, int $recipeId): void
    {
        DB::transaction(function () use ($productId, $recipeId) {
            $this->getById($productId, $recipeId)->delete();
        });
    }
}
