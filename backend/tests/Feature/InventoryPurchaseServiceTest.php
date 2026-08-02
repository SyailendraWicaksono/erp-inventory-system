<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Services\InventoryPurchaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryPurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryPurchaseService;
    }

    public function test_get_all_returns_all_purchases(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $this->service->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);
        $this->service->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 30]);

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_persists_purchase_and_increments_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);

        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_create_defaults_purchase_date_to_now(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->assertNotNull($purchase->purchase_date);
    }

    public function test_update_adjusts_stock_by_difference(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['quantity' => 80]);

        $this->assertEquals(180, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_update_changes_raw_material_and_moves_stock(): void
    {
        $old = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $new = RawMaterial::factory()->create(['stock_quantity' => 200]);
        $purchase = $this->service->create([
            'raw_material_id' => $old->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['raw_material_id' => $new->id]);

        $this->assertEquals(100, (float) $old->refresh()->stock_quantity);
        $this->assertEquals(250, (float) $new->refresh()->stock_quantity);
    }

    public function test_update_with_same_raw_material_does_not_double_adjust(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['raw_material_id' => $rawMaterial->id]);

        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_delete_decrements_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->delete($purchase->id);

        $this->assertDatabaseMissing('inventory_purchases', ['id' => $purchase->id]);
        $this->assertEquals(100, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_update_guard_rolls_back_when_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 30]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $rawMaterial->update(['stock_quantity' => 10]);

        try {
            $this->service->update($purchase->id, ['quantity' => 5]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id, 'quantity' => 50]);
            $this->assertEquals(10, (float) $rawMaterial->refresh()->stock_quantity);
        }
    }

    public function test_delete_guard_rolls_back_when_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 30]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $rawMaterial->update(['stock_quantity' => 10]);

        try {
            $this->service->delete($purchase->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
            $this->assertEquals(10, (float) $rawMaterial->refresh()->stock_quantity);
        }
    }

    public function test_stock_values_are_rounded_to_two_decimals(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 0]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 0.1,
        ]);
        $this->service->update($purchase->id, ['quantity' => 0.2]);
        $this->service->update($purchase->id, ['quantity' => 0.3]);

        $this->assertEquals(0.3, (float) $rawMaterial->refresh()->stock_quantity);
    }
}
