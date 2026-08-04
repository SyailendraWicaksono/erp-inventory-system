<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderService;
    }

    private function createProduct(string $name = 'Chocolate Cake', bool $isActive = true, float $basePrice = 50000): Product
    {
        return Product::create([
            'name' => $name,
            'base_price' => $basePrice,
            'is_active' => $isActive,
        ]);
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Budi',
            'phone_number' => '081234567890',
            'pickup_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ], $overrides);
    }

    public function test_get_all_returns_orders_latest_first(): void
    {
        $product = $this->createProduct();
        $this->service->create($this->payload($product));
        $second = $this->service->create($this->payload($product));

        $orders = $this->service->getAll();

        $this->assertCount(2, $orders);
        $this->assertSame($second->id, $orders->first()->id);
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_creates_order_as_pending(): void
    {
        $product = $this->createProduct();

        $order = $this->service->create($this->payload($product));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => Order::ORDER_STATUS_PENDING,
        ]);
    }

    public function test_create_generates_unique_order_number_with_format(): void
    {
        $product = $this->createProduct();
        $first = $this->service->create($this->payload($product));
        $second = $this->service->create($this->payload($product));

        $pattern = '/^ORD-\d{8}-\d{6}$/';

        $this->assertMatchesRegularExpression($pattern, $first->order_number);
        $this->assertMatchesRegularExpression($pattern, $second->order_number);
        $this->assertNotSame($first->order_number, $second->order_number);
    }

    public function test_create_reuses_existing_customer_by_phone(): void
    {
        $product = $this->createProduct();
        $this->service->create($this->payload($product));

        $second = $this->service->create($this->payload($product));

        $this->assertCount(1, Customer::all());
        $this->assertEquals($second->customer_id, Order::first()->customer_id);
    }

    public function test_create_creates_customer_for_new_phone(): void
    {
        $product = $this->createProduct();
        $this->service->create($this->payload($product));

        $order = $this->service->create($this->payload($product, ['phone_number' => '081299998888']));

        $this->assertCount(2, Customer::all());
        $this->assertSame('081299998888', $order->customer->phone_number);
    }

    public function test_create_defaults_unit_price_to_base_price(): void
    {
        $product = $this->createProduct('Chocolate Cake', true, 60000);

        $order = $this->service->create($this->payload($product, ['items' => [['product_id' => $product->id, 'quantity' => 2]]]));

        $item = $order->items->first();
        $this->assertEquals(60000, (float) $item->unit_price);
        $this->assertEquals(120000, (float) $item->subtotal);
        $this->assertEquals(120000, (float) $order->total_price);
    }

    public function test_create_respects_client_unit_price(): void
    {
        $product = $this->createProduct();

        $order = $this->service->create($this->payload($product, [
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 45000]],
        ]));

        $item = $order->items->first();
        $this->assertEquals(45000, (float) $item->unit_price);
        $this->assertEquals(135000, (float) $item->subtotal);
        $this->assertEquals(135000, (float) $order->total_price);
    }

    public function test_create_rejects_pickup_in_past(): void
    {
        $product = $this->createProduct();

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($product, [
            'pickup_datetime' => now()->subDay()->format('Y-m-d H:i:s'),
        ]));
    }

    public function test_update_replaces_items_and_recomputes_prices(): void
    {
        $product = $this->createProduct();
        $other = $this->createProduct('Vanilla Cake', true, 30000);
        $order = $this->service->create($this->payload($product));

        $updated = $this->service->update($order->id, [
            'items' => [
                ['product_id' => $other->id, 'quantity' => 2, 'unit_price' => 35000],
            ],
        ]);

        $this->assertCount(1, $updated->items);
        $this->assertEquals($other->id, $updated->items->first()->product_id);
        $this->assertEquals(70000, (float) $updated->total_price);
        $this->assertSame('pending', $updated->order_status);
    }

    public function test_update_rejects_non_pending_order(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));
        $this->service->confirm($order->id);

        $this->expectException(ValidationException::class);

        $this->service->update($order->id, ['items' => [['product_id' => $product->id, 'quantity' => 3]]]);
    }

    public function test_update_rejects_pickup_in_past(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));

        $this->expectException(ValidationException::class);

        $this->service->update($order->id, ['pickup_datetime' => now()->subDay()->format('Y-m-d H:i:s')]);
    }

    public function test_update_re_resolves_customer_on_phone_change(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));

        $updated = $this->service->update($order->id, [
            'phone_number' => '081277776666',
            'customer_name' => 'Andi',
        ]);

        $this->assertCount(2, Customer::all());
        $this->assertSame('081277776666', $updated->customer->phone_number);
        $this->assertSame('Andi', $updated->customer->name);
    }

    public function test_delete_removes_pending_order(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));

        $this->service->delete($order->id);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_delete_rejects_non_pending_order(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));
        $this->service->confirm($order->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($order->id);
    }

    public function test_confirm_transitions_pending_to_confirmed(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));

        $confirmed = $this->service->confirm($order->id);

        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $confirmed->order_status);
        $this->assertSame('confirmed', $order->refresh()->order_status);
    }

    public function test_confirm_rejects_duplicate_confirm(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));
        $this->service->confirm($order->id);

        $this->expectException(ValidationException::class);

        $this->service->confirm($order->id);
    }

    public function test_confirm_rejects_order_without_items(): void
    {
        $order = Order::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->confirm($order->id);
    }

    public function test_confirm_rejects_inactive_product(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));
        $product->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->service->confirm($order->id);
    }

    public function test_confirm_rejects_zero_unit_price(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product, [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 0]],
        ]));

        $this->expectException(ValidationException::class);

        $this->service->confirm($order->id);
    }

    public function test_confirm_rejects_pickup_in_past(): void
    {
        $product = $this->createProduct();
        $order = $this->service->create($this->payload($product));
        $order->update(['pickup_datetime' => now()->subDay()->format('Y-m-d H:i:s')]);

        $this->expectException(ValidationException::class);

        $this->service->confirm($order->id);
    }

    public function test_confirm_recomputes_subtotals_and_total(): void
    {
        $product = $this->createProduct('Chocolate Cake', true, 50000);
        $other = $this->createProduct('Vanilla Cake', true, 30000);
        $order = $this->service->create($this->payload($product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 60000],
                ['product_id' => $other->id, 'quantity' => 1],
            ],
        ]));

        $confirmed = $this->service->confirm($order->id);

        $this->assertEquals(120000, (float) $confirmed->items[0]->subtotal);
        $this->assertEquals(30000, (float) $confirmed->items[1]->subtotal);
        $this->assertEquals(150000, (float) $confirmed->total_price);
    }
}
