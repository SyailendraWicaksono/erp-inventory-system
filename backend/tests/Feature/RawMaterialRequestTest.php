<?php

namespace Tests\Feature;

use App\Http\Requests\RawMaterialRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RawMaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new RawMaterialRequest)->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 500.00,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_name_is_required(): void
    {
        $validator = Validator::make([
            'stock_quantity' => 100,
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_stock_quantity_is_required(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stock_quantity', $validator->errors()->toArray());
    }

    public function test_stock_quantity_must_not_be_negative(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => -1,
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stock_quantity', $validator->errors()->toArray());
    }

    public function test_unit_is_required(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 100,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('unit', $validator->errors()->toArray());
    }

    public function test_expiration_date_must_be_a_valid_date(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 100,
            'unit' => 'gram',
            'expiration_date' => 'not-a-date',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expiration_date', $validator->errors()->toArray());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = RawMaterialRequest::create('/api/raw-materials/1', 'PUT', [
            'name' => 'Whole Wheat Flour',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
