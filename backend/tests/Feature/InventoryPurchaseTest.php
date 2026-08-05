<?php

namespace Tests\Feature;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesOwner;
use Tests\TestCase;

class InventoryPurchaseTest extends TestCase
{
    use AuthenticatesOwner, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticateOwner();
    }

    public function test_index_returns_purchase_list(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram']);
        InventoryPurchase::factory()->count(2)->create(['raw_material_id' => $rawMaterial->id]);

        $response = $this->getJson('/api/inventory-purchases');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.raw_material.name', 'Flour');
    }

    public function test_store_creates_purchase_and_increments_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);

        $response = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 50);
        $this->assertDatabaseHas('inventory_purchases', ['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);
        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_show_returns_purchase(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $purchase = InventoryPurchase::factory()->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);

        $response = $this->getJson("/api/inventory-purchases/{$purchase->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $purchase->id)
            ->assertJsonPath('data.quantity', 50);
    }

    public function test_update_updates_purchase_and_adjusts_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $response = $this->putJson("/api/inventory-purchases/{$purchase['id']}", [
            'quantity' => 80,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity', 80);
        $this->assertEquals(180, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_destroy_deletes_purchase_and_decrements_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $response = $this->deleteJson("/api/inventory-purchases/{$purchase['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('inventory_purchases', ['id' => $purchase['id']]);
        $this->assertEquals(100, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => 999999,
            'quantity' => 0,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_purchase_returns_404(): void
    {
        $response = $this->getJson('/api/inventory-purchases/999999');

        $response->assertNotFound();
    }

    public function test_delete_rejects_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 10]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $rawMaterial->update(['stock_quantity' => 0]);

        $response = $this->deleteJson("/api/inventory-purchases/{$purchase['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase['id']]);
    }
}
