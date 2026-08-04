<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_order_with_required_fields(): void
    {
        $order = Order::factory()->create();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertNotNull($order->order_number);
        $this->assertSame(Order::ORDER_STATUS_PENDING, $order->order_status);
        $this->assertEquals(0, (float) $order->total_price);
        $this->assertNotNull($order->customer);
    }

    public function test_has_constants_for_statuses(): void
    {
        $this->assertSame('pending', Order::ORDER_STATUS_PENDING);
        $this->assertSame('confirmed', Order::ORDER_STATUS_CONFIRMED);
        $this->assertSame('finished', Order::ORDER_STATUS_FINISHED);
        $this->assertSame('completed', Order::ORDER_STATUS_COMPLETED);
    }

    public function test_belongs_to_customer(): void
    {
        $order = Order::factory()->create();

        $this->assertTrue($order->customer->is(Customer::find($order->customer_id)));
    }

    public function test_has_many_items(): void
    {
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => Product::create([
                'name' => 'Chocolate Cake',
                'base_price' => 50000,
                'is_active' => true,
            ])->id,
            'quantity' => 2,
            'customization_note' => null,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        $this->assertCount(1, $order->items);
    }

    public function test_order_item_unit_price_is_fillable(): void
    {
        $order = Order::factory()->create();
        $product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 55000,
            'subtotal' => 110000,
        ]);

        $this->assertEquals(55000, (float) $item->unit_price);
    }
}
