<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesOwner;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use AuthenticatesOwner, RefreshDatabase;

    private Product $product;

    private RawMaterial $rawMaterial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticateOwner();

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
    }

    private function recipePayload(): array
    {
        return [
            'recipe_name' => 'Chocolate Cake A',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500.00],
            ],
        ];
    }

    public function test_index_returns_recipe_list(): void
    {
        $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload());

        $response = $this->getJson("/api/products/{$this->product->id}/recipes");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_recipe(): void
    {
        $response = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake A')
            ->assertJsonCount(1, 'data.recipe_details')
            ->assertJsonPath('data.recipe_details.0.raw_material_id', $this->rawMaterial->id);
    }

    public function test_show_returns_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->getJson("/api/products/{$this->product->id}/recipes/{$created['id']}");

        $response->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake A');
    }

    public function test_update_replaces_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->putJson("/api/products/{$this->product->id}/recipes/{$created['id']}", [
            'recipe_name' => 'Chocolate Cake B',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 300.00],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake B')
            ->assertJsonCount(1, 'data.recipe_details')
            ->assertJsonPath('data.recipe_details.0.raw_material_id', $this->rawMaterial->id);
    }

    public function test_destroy_deletes_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->deleteJson("/api/products/{$this->product->id}/recipes/{$created['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('recipes', ['id' => $created['id']]);
        $this->assertDatabaseMissing('recipe_details', ['recipe_id' => $created['id']]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson("/api/products/{$this->product->id}/recipes", [
            'recipe_name' => '',
            'recipe_details' => [],
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_recipe_of_another_product_returns_404(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/products/{$otherProduct->id}/recipes/{$created['id']}");

        $response->assertNotFound();
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $response = $this->postJson('/api/products/999999/recipes', $this->recipePayload());

        $response->assertNotFound();
    }

    public function test_index_on_nonexistent_product_returns_404(): void
    {
        $response = $this->getJson('/api/products/999999/recipes');

        $response->assertNotFound();
    }
}
