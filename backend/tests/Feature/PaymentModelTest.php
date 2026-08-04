<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertSame(Payment::PAYMENT_STATUS_RECORDED, $payment->payment_status);
        $this->assertNotNull($payment->payment_method);
        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $payment->order->order_status);
    }

    public function test_has_status_constants(): void
    {
        $this->assertSame('recorded', Payment::PAYMENT_STATUS_RECORDED);
        $this->assertSame('paid', Payment::PAYMENT_STATUS_PAID);
    }

    public function test_belongs_to_order(): void
    {
        $payment = Payment::factory()->create();

        $this->assertTrue($payment->order->is(Order::find($payment->order_id)));
    }
}
