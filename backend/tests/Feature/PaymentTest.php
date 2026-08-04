<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(string $status = Order::ORDER_STATUS_CONFIRMED, float $total = 100000): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => $status,
            'total_price' => $total,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $order = $this->createOrder();

        return array_merge([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ], $overrides);
    }

    public function test_index_returns_payments_latest_first_with_nested_order(): void
    {
        $this->postJson('/api/payments', $this->validPayload())->json('data');
        $second = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->getJson('/api/payments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second['id'])
            ->assertJsonPath('data.0.order.order_status', 'confirmed');
        $this->assertArrayHasKey('order', $response->json('data.0'));
    }

    public function test_store_creates_recorded_payment(): void
    {
        $response = $this->postJson('/api/payments', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'recorded')
            ->assertJsonPath('data.payment_method', 'cash');
        $this->assertDatabaseHas('payments', ['id' => $response->json('data.id')]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/payments', ['payment_method' => 'cash']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_rejects_pending_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_PENDING);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_payment(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->getJson("/api/payments/{$payment['id']}");

        $response->assertOk()->assertJsonPath('data.id', $payment['id']);
    }

    public function test_show_missing_payment_returns_404(): void
    {
        $this->getJson('/api/payments/999999')->assertNotFound();
    }

    public function test_update_changes_payment_while_recorded(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->putJson("/api/payments/{$payment['id']}", [
            'payment_method' => 'transfer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'transfer')
            ->assertJsonPath('data.payment_status', 'recorded');
    }

    public function test_update_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->putJson("/api/payments/{$payment['id']}", ['payment_method' => 'transfer']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_destroy_deletes_recorded_payment(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->deleteJson("/api/payments/{$payment['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('payments', ['id' => $payment['id']]);
    }

    public function test_destroy_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->deleteJson("/api/payments/{$payment['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('payments', ['id' => $payment['id']]);
    }

    public function test_verify_marks_paid_and_completes_finished_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.order.order_status', 'completed');
        $this->assertSame('completed', Order::find($order->id)->order_status);
    }

    public function test_verify_rejects_order_not_finished(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_verify_rejects_duplicate_verify(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_completed_order_rejects_new_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_COMPLETED);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }
}