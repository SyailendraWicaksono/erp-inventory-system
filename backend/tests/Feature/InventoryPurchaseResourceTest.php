<?php

namespace Tests\Feature;

use App\Http\Resources\InventoryPurchaseResource;
use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPurchaseResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_purchase_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram']);
        $purchase = InventoryPurchase::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 500,
            'purchase_date' => '2026-08-01 09:00:00',
        ]);
        $purchase->load('rawMaterial');

        $resource = (new InventoryPurchaseResource($purchase))->resolve();

        $this->assertEquals($purchase->id, $resource['id']);
        $this->assertEquals($rawMaterial->id, $resource['raw_material_id']);
        $this->assertEquals(500, $resource['quantity']);
        $this->assertEquals('2026-08-01 09:00:00', $resource['purchase_date']);
        $this->assertEquals(['id' => $rawMaterial->id, 'name' => 'Flour', 'unit' => 'gram'], $resource['raw_material']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
