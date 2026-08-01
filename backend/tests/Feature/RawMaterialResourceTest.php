<?php

namespace Tests\Feature;

use App\Http\Resources\RawMaterialResource;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_material_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create([
            'name' => 'Flour',
            'stock_quantity' => 500,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ]);

        $resource = (new RawMaterialResource($rawMaterial))->resolve();

        $this->assertEquals($rawMaterial->id, $resource['id']);
        $this->assertEquals('Flour', $resource['name']);
        $this->assertEquals(500, $resource['stock_quantity']);
        $this->assertEquals('gram', $resource['unit']);
        $this->assertEquals('2026-08-30', $resource['expiration_date']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
