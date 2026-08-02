<?php

namespace Tests\Feature;

use App\Http\Resources\InventoryAvailabilityResource;
use App\Models\RawMaterial;
use App\Services\InventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_returns_availability_for_all_raw_materials(): void
    {
        RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 20]);
        RawMaterial::factory()->create(['name' => 'Sugar', 'stock_quantity' => 5]);
        RawMaterial::factory()->create(['name' => 'Salt', 'stock_quantity' => 0]);

        $response = $this->getJson('/api/inventory/availability');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_endpoint_classifies_stock_status_at_boundaries(): void
    {
        RawMaterial::factory()->create(['name' => 'Avail', 'stock_quantity' => 10]);
        RawMaterial::factory()->create(['name' => 'Low', 'stock_quantity' => 9.99]);
        RawMaterial::factory()->create(['name' => 'Out', 'stock_quantity' => 0]);
        RawMaterial::factory()->create(['name' => 'Plenty', 'stock_quantity' => 10.01]);

        $response = $this->getJson('/api/inventory/availability');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.1.status', 'low')
            ->assertJsonPath('data.2.status', 'out_of_stock')
            ->assertJsonPath('data.3.status', 'available');
    }

    public function test_service_returns_classified_status(): void
    {
        RawMaterial::factory()->create(['stock_quantity' => 0]);

        $statuses = (new InventoryAvailabilityService)->getStatus();

        $this->assertCount(1, $statuses);
        $this->assertSame('out_of_stock', $statuses->first()->status);
    }

    public function test_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram', 'stock_quantity' => 20]);
        $rawMaterial->status = 'available';

        $resource = (new InventoryAvailabilityResource($rawMaterial))->resolve();

        $this->assertEquals($rawMaterial->id, $resource['id']);
        $this->assertEquals('Flour', $resource['name']);
        $this->assertEquals('gram', $resource['unit']);
        $this->assertEquals(20, $resource['stock_quantity']);
        $this->assertEquals('available', $resource['status']);
    }
}
