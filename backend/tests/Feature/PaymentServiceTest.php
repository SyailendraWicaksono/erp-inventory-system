<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService;
    }

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

    private function payload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => (float) $order->total_price,
        ], $overrides);
    }

    public function test_get_all_returns_all_payments(): void
    {
        $orderA = $this->createOrder();
        $orderB = $this->createOrder();
        $this->service->create($this->payload($orderA));
        $this->service->create($this->payload($orderB));

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_creates_payment_as_recorded(): void
    {
        $order = $this->createOrder();

        $payment = $this->service->create($this->payload($order));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'order_id' => $order->id,
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
        ]);
        $this->assertEquals(100000, (float) $payment->payment_amount);
    }

    public function test_create_defaults_payment_date_to_now(): void
    {
        $order = $this->createOrder();

        $payment = $this->service->create($this->payload($order));

        $this->assertNotNull($payment->payment_date);
    }

    public function test_create_rejects_pending_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_PENDING);

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_completed_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_COMPLETED);

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_second_payment_for_same_order(): void
    {
        $order = $this->createOrder();
        $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder();

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order, ['payment_amount' => 50000]));
    }

    public function test_create_throws_when_order_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create(['order_id' => 999999, 'payment_method' => 'cash', 'payment_amount' => 100000]);
    }

    public function test_update_changes_method_amount_date_while_recorded(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $updated = $this->service->update($payment->id, [
            'payment_method' => 'transfer',
            'payment_amount' => 100000,
            'payment_date' => '2026-08-06 09:00:00',
        ]);

        $this->assertSame('transfer', $updated->payment_method);
        $this->assertEquals(100000, (float) $updated->payment_amount);
        $this->assertEquals('2026-08-06 09:00:00', $updated->payment_date);
    }

    public function test_update_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->update($payment->id, ['payment_method' => 'transfer']);
    }

    public function test_update_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->update($payment->id, ['payment_amount' => 1]);
    }

    public function test_update_never_changes_order_id(): void
    {
        $order = $this->createOrder();
        $otherOrder = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $updated = $this->service->update($payment->id, [
            'payment_method' => 'transfer',
            'order_id' => $otherOrder->id,
        ]);

        $this->assertSame($payment->order_id, $updated->order_id);
    }

    public function test_delete_removes_recorded_payment(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $this->service->delete($payment->id);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_delete_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($payment->id);
    }

    public function test_verify_marks_paid_and_completes_finished_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));

        $verified = $this->service->verify($payment->id);

        $this->assertSame(Payment::PAYMENT_STATUS_PAID, $verified->payment_status);
        $this->assertSame(Order::ORDER_STATUS_COMPLETED, $order->refresh()->order_status);
    }

    public function test_verify_rejects_duplicate_verify(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }

    public function test_verify_rejects_order_not_finished(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_CONFIRMED);
        $payment = $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }

    public function test_verify_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $payment->update(['payment_amount' => 1]);

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }
}
