<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function rawMaterialPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Flour',
            'stock_quantity' => 500.00,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ], $overrides);
    }

    public function test_index_returns_raw_material_list(): void
    {
        RawMaterial::factory()->count(2)->create();

        $response = $this->getJson('/api/raw-materials');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_raw_material(): void
    {
        $response = $this->postJson('/api/raw-materials', $this->rawMaterialPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Flour')
            ->assertJsonPath('data.unit', 'gram');
        $this->assertDatabaseHas('raw_materials', ['name' => 'Flour', 'unit' => 'gram']);
    }

    public function test_show_returns_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->getJson("/api/raw-materials/{$created['id']}");

        $response->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.name', 'Flour');
    }

    public function test_update_updates_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->putJson("/api/raw-materials/{$created['id']}", [
            'name' => 'Whole Wheat Flour',
            'stock_quantity' => 300.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Whole Wheat Flour')
            ->assertJsonPath('data.stock_quantity', 300);
        $this->assertDatabaseHas('raw_materials', ['id' => $created['id'], 'name' => 'Whole Wheat Flour']);
    }

    public function test_destroy_deletes_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->deleteJson("/api/raw-materials/{$created['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('raw_materials', ['id' => $created['id']]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/raw-materials', [
            'name' => '',
            'stock_quantity' => -5,
            'unit' => '',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_raw_material_returns_404(): void
    {
        $response = $this->getJson('/api/raw-materials/999999');

        $response->assertNotFound();
    }
}
