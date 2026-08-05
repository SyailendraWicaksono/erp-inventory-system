<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesOwner;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use AuthenticatesOwner, RefreshDatabase;

    private function createProduct(string $name = 'Chocolate Cake', bool $isActive = true): Product
    {
        return Product::create([
            'name' => $name,
            'base_price' => 50000,
            'is_active' => $isActive,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $product = $this->createProduct();

        return array_merge([
            'customer_name' => 'Budi',
            'phone_number' => '081234567890',
            'pickup_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    private function createPendingOrder(): Order
    {
        $customer = Customer::factory()->create();
        $product = $this->createProduct('Vanilla Cake');

        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);

        return $order;
    }

    public function test_index_returns_orders_latest_first_with_nested_data(): void
    {
        $this->authenticateOwner();

        $first = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $second = $this->postJson('/api/orders', $this->validPayload())->json('data');

        $response = $this->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second['id'])
            ->assertJsonPath('data.0.customer.phone_number', '081234567890')
            ->assertJsonCount(1, 'data.0.items');

        $product = $response->json('data.0.items.0.product');
        $this->assertArrayHasKey('id', $product);
        $this->assertArrayHasKey('name', $product);
        $this->assertArrayHasKey('base_price', $product);
        $this->assertArrayHasKey('is_active', $product);
        $this->assertNotSame($first['id'], $second['id']);
    }

    public function test_store_creates_pending_order(): void
    {
        $response = $this->postJson('/api/orders', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.customer.name', 'Budi');
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id')]);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{6}$/', $response->json('data.order_number'));
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/orders', ['items' => [['quantity' => 1]]]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_order(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');

        $response = $this->getJson("/api/orders/{$order['id']}");

        $response->assertOk()->assertJsonPath('data.id', $order['id']);
    }

    public function test_show_missing_order_returns_404(): void
    {
        $this->authenticateOwner();

        $this->getJson('/api/orders/999999')->assertNotFound();
    }

    public function test_update_replaces_items_while_pending(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $other = $this->createProduct('Red Velvet');

        $response = $this->putJson("/api/orders/{$order['id']}", [
            'items' => [['product_id' => $other->id, 'quantity' => 3]],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.product_id', $other->id)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.order_status', 'pending');
    }

    public function test_update_rejects_non_pending_order(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $this->patchJson("/api/orders/{$order['id']}/confirm")->assertOk();

        $response = $this->putJson("/api/orders/{$order['id']}", [
            'pickup_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_destroy_deletes_pending_order(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');

        $response = $this->deleteJson("/api/orders/{$order['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('orders', ['id' => $order['id']]);
    }

    public function test_destroy_rejects_non_pending_order(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $this->patchJson("/api/orders/{$order['id']}/confirm")->assertOk();

        $response = $this->deleteJson("/api/orders/{$order['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('orders', ['id' => $order['id']]);
    }

    public function test_confirm_confirms_order(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');

        $response = $this->patchJson("/api/orders/{$order['id']}/confirm");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', 'confirmed');
        $this->assertSame('confirmed', Order::find($order['id'])->order_status);
    }

    public function test_confirm_rejects_duplicate_confirm(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $this->patchJson("/api/orders/{$order['id']}/confirm")->assertOk();

        $response = $this->patchJson("/api/orders/{$order['id']}/confirm");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_order_resource_includes_production_schedule_when_present(): void
    {
        $this->authenticateOwner();

        $order = $this->postJson('/api/orders', $this->validPayload())->json('data');
        $this->patchJson("/api/orders/{$order['id']}/confirm")->assertOk();
        $this->postJson('/api/production-schedules', ['order_id' => $order['id']])->assertCreated();

        $response = $this->getJson("/api/orders/{$order['id']}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.production_schedule'));
        $this->assertSame('scheduled', $response->json('data.production_schedule.production_status'));
    }
}
