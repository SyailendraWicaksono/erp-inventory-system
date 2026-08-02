<?php

namespace Tests\Feature;

use App\Http\Requests\InventoryPurchaseRequest;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InventoryPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new InventoryPurchaseRequest)->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50.00,
            'purchase_date' => '2026-08-01 09:00:00',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_raw_material_id_is_required(): void
    {
        $validator = Validator::make([
            'quantity' => 50,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raw_material_id', $validator->errors()->toArray());
    }

    public function test_raw_material_id_must_exist(): void
    {
        $validator = Validator::make([
            'raw_material_id' => 999999,
            'quantity' => 50,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raw_material_id', $validator->errors()->toArray());
    }

    public function test_quantity_is_required(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }

    public function test_quantity_must_be_positive(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 0,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = InventoryPurchaseRequest::create('/api/inventory-purchases/1', 'PUT', [
            'quantity' => 75,
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_rejects_null_purchase_date(): void
    {
        $request = InventoryPurchaseRequest::create('/api/inventory-purchases/1', 'PUT', [
            'purchase_date' => null,
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('purchase_date', $validator->errors()->toArray());
    }
}
