<?php

namespace Tests\Feature;

use App\Services\RawMaterialService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialServiceTest extends TestCase
{
    use RefreshDatabase;

    private RawMaterialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RawMaterialService;
    }

    public function test_create_persists_raw_material(): void
    {
        $rawMaterial = $this->service->create([
            'name' => 'Flour',
            'stock_quantity' => 500,
            'unit' => 'gram',
        ]);

        $this->assertDatabaseHas('raw_materials', [
            'id' => $rawMaterial->id,
            'name' => 'Flour',
            'unit' => 'gram',
        ]);
    }

    public function test_get_all_returns_all_raw_materials(): void
    {
        $this->service->create(['name' => 'Sugar', 'stock_quantity' => 100, 'unit' => 'gram']);
        $this->service->create(['name' => 'Flour', 'stock_quantity' => 200, 'unit' => 'gram']);

        $rawMaterials = $this->service->getAll();

        $this->assertCount(2, $rawMaterials);
    }

    public function test_get_by_id_returns_raw_material(): void
    {
        $created = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $found = $this->service->getById($created->id);

        $this->assertEquals($created->id, $found->id);
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_update_changes_fields(): void
    {
        $rawMaterial = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $updated = $this->service->update($rawMaterial->id, [
            'stock_quantity' => 800,
            'expiration_date' => '2026-08-30',
        ]);

        $this->assertEquals(800, $updated->stock_quantity);
        $this->assertEquals('2026-08-30', $updated->expiration_date);
        $this->assertDatabaseHas('raw_materials', ['id' => $rawMaterial->id, 'stock_quantity' => 800]);
    }

    public function test_delete_removes_raw_material(): void
    {
        $rawMaterial = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $this->service->delete($rawMaterial->id);

        $this->assertDatabaseMissing('raw_materials', ['id' => $rawMaterial->id]);
    }
}
