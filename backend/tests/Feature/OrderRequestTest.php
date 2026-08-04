<?php

namespace Tests\Feature;

use App\Http\Requests\OrderRequest;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new OrderRequest)->rules();
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);
    }

    private function validPayload(): array
    {
        $product = $this->createProduct();

        return [
            'customer_name' => 'Budi',
            'phone_number' => '081234567890',
            'pickup_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 55000,
                ],
            ],
        ];
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make($this->validPayload(), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_customer_name_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['customer_name']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('customer_name', $validator->errors()->toArray());
    }

    public function test_phone_number_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['phone_number']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone_number', $validator->errors()->toArray());
    }

    public function test_pickup_datetime_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['pickup_datetime']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pickup_datetime', $validator->errors()->toArray());
    }

    public function test_items_is_required_array_with_min_product(): void
    {
        $data = $this->validPayload();
        $data['items'] = [];

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items', $validator->errors()->toArray());
    }

    public function test_items_product_id_must_exist(): void
    {
        $data = $this->validPayload();
        $data['items'][0]['product_id'] = 999999;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.product_id', $validator->errors()->toArray());
    }

    public function test_items_quantity_min_one(): void
    {
        $data = $this->validPayload();
        $data['items'][0]['quantity'] = 0;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0.quantity', $validator->errors()->toArray());
    }

    public function test_items_unit_price_is_nullable_numeric_min_zero(): void
    {
        $data = $this->validPayload();
        $data['items'][0]['unit_price'] = null;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_allows_partial_payload(): void
    {
        $product = $this->createProduct();
        $request = OrderRequest::create('/api/orders/1', 'PUT', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
