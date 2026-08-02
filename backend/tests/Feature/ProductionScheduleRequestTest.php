<?php

namespace Tests\Feature;

use App\Http\Requests\ProductionScheduleRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductionScheduleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new ProductionScheduleRequest)->rules();
    }

    private function createOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
    }

    public function test_valid_payload_passes(): void
    {
        $order = $this->createOrder();

        $validator = Validator::make([
            'order_id' => $order->id,
            'start_time' => now()->format('Y-m-d H:i:s'),
            'end_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_order_id_is_required(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_order_id_must_exist(): void
    {
        $validator = Validator::make(['order_id' => 999999], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_times_are_nullable_on_store(): void
    {
        $order = $this->createOrder();

        $validator = Validator::make(['order_id' => $order->id], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = ProductionScheduleRequest::create('/api/production-schedules/1', 'PUT', [
            'start_time' => now()->format('Y-m-d H:i:s'),
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
