<?php

namespace Tests\Feature;

use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new PaymentRequest)->rules();
    }

    private function createOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 100000,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'order_id' => $this->createOrder()->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ];
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make($this->validPayload(), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_order_id_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['order_id']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_order_id_must_exist(): void
    {
        $data = $this->validPayload();
        $data['order_id'] = 999999;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_payment_method_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['payment_method']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_method', $validator->errors()->toArray());
    }

    public function test_payment_amount_is_required_and_positive(): void
    {
        $data = $this->validPayload();
        $data['payment_amount'] = 0;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_amount', $validator->errors()->toArray());
    }

    public function test_payment_date_is_nullable(): void
    {
        $data = $this->validPayload();
        $data['payment_date'] = null;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_allows_partial_payload(): void
    {
        $request = PaymentRequest::create('/api/payments/1', 'PUT', [
            'payment_date' => now()->format('Y-m-d H:i:s'),
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
