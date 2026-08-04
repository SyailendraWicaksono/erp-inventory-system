<?php

namespace Tests\Feature;

use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithItem(): Order
    {
        $customer = Customer::factory()->create(['name' => 'Budi', 'phone_number' => '081234567890']);
        $product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'customization_note' => null,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        return $order;
    }

    public function test_order_resource_has_expected_shape(): void
    {
        $order = $this->createOrderWithItem();
        $order->load(['customer', 'items.product', 'productionSchedule']);

        $resource = (new OrderResource($order))->resolve();

        $this->assertEquals($order->id, $resource['id']);
        $this->assertEquals($order->order_number, $resource['order_number']);
        $this->assertEquals($order->pickup_datetime, $resource['pickup_datetime']);
        $this->assertEquals($order->order_status, $resource['order_status']);
        $this->assertEquals($order->customer->name, $resource['customer']['name']);
        $this->assertEquals($order->customer->phone_number, $resource['customer']['phone_number']);
        $this->assertCount(1, $resource['items']);
        $this->assertEquals(50000, (float) $resource['items'][0]['unit_price']);
        $this->assertEquals(100000, (float) $resource['items'][0]['subtotal']);
        $this->assertEquals('Chocolate Cake', $resource['items'][0]['product']['name']);
        $this->assertNull($resource['production_schedule']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }

    public function test_order_resource_includes_production_schedule_when_present(): void
    {
        $order = $this->createOrderWithItem();
        $schedule = $order->productionSchedule()->create([
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ]);
        $order->load(['customer', 'items.product', 'productionSchedule']);

        $resource = (new OrderResource($order))->resolve();

        $this->assertEquals($schedule->id, $resource['production_schedule']['id']);
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $resource['production_schedule']['production_status']);
        $this->assertNull($resource['production_schedule']['start_time']);
    }
}
