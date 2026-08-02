<?php

namespace Tests\Feature;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPurchaseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_inventory_purchase(): void
    {
        $purchase = InventoryPurchase::factory()->create();

        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
        $this->assertSame(50, (int) $purchase->quantity);
        $this->assertNotNull($purchase->purchase_date);
    }

    public function test_belongs_to_raw_material(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $purchase = InventoryPurchase::factory()->create(['raw_material_id' => $rawMaterial->id]);

        $this->assertTrue($purchase->rawMaterial->is($rawMaterial));
    }
}
