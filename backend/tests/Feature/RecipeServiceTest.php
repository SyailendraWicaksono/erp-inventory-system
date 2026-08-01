<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Services\RecipeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private RawMaterial $rawMaterial;
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $this->rawMaterial = RawMaterial::create([
            'name' => 'Flour',
            'stock_quantity' => 1000,
            'unit' => 'gram',
        ]);

        $this->service = new RecipeService();
    }

    public function test_create_recipe_with_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'Chocolate Cake A',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500],
            ],
        ]);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $this->rawMaterial->id,
        ]);
    }

    public function test_get_all_filters_by_product(): void
    {
        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);
        $this->service->create($otherProduct->id, [
            'recipe_name' => 'B',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $recipes = $this->service->getAll($this->product->id);

        $this->assertCount(1, $recipes);
        $this->assertEquals('A', $recipes->first()->recipe_name);
    }

    public function test_update_replaces_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'Old',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500],
            ],
        ]);

        $otherRawMaterial = RawMaterial::create([
            'name' => 'Sugar',
            'stock_quantity' => 100,
            'unit' => 'gram',
        ]);

        $updated = $this->service->update($this->product->id, $recipe->id, [
            'recipe_name' => 'New',
            'recipe_details' => [
                ['raw_material_id' => $otherRawMaterial->id, 'quantity' => 100],
            ],
        ]);

        $this->assertEquals('New', $updated->recipe_name);
        $this->assertDatabaseMissing('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $this->rawMaterial->id,
        ]);
        $this->assertDatabaseHas('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $otherRawMaterial->id,
        ]);
    }

    public function test_get_by_id_throws_when_recipe_not_in_product(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->getById($otherProduct->id, $recipe->id);
    }

    public function test_delete_removes_recipe_and_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $this->service->delete($this->product->id, $recipe->id);

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_details', ['recipe_id' => $recipe->id]);
    }
}
