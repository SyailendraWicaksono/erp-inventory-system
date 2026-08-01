<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_raw_material(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $this->assertDatabaseHas('raw_materials', ['id' => $rawMaterial->id]);
        $this->assertSame(100, (int) $rawMaterial->stock_quantity);
        $this->assertSame('gram', $rawMaterial->unit);
    }

    public function test_has_many_recipe_details(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        RecipeDetail::factory()->count(2)->create(['raw_material_id' => $rawMaterial->id]);

        $this->assertCount(2, $rawMaterial->recipeDetails);
    }

    public function test_has_many_inventory_purchases(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $rawMaterial->inventoryPurchases()->create([
            'quantity' => 50,
            'purchase_date' => now(),
        ]);

        $this->assertCount(1, $rawMaterial->inventoryPurchases);
        $this->assertNotNull($rawMaterial->inventoryPurchases->first()->purchase_date);
    }
}
