<?php

namespace Tests\Feature;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_resource_has_expected_shape(): void
    {
        $payment = Payment::factory()->create([
            'payment_method' => 'cash',
            'payment_status' => Payment::PAYMENT_STATUS_PAID,
            'payment_amount' => 100000,
            'payment_date' => '2026-08-05 10:00:00',
        ]);
        $payment->load('order');

        $resource = (new PaymentResource($payment))->resolve();

        $this->assertEquals($payment->id, $resource['id']);
        $this->assertEquals($payment->order_id, $resource['order_id']);
        $this->assertEquals('cash', $resource['payment_method']);
        $this->assertEquals(Payment::PAYMENT_STATUS_PAID, $resource['payment_status']);
        $this->assertEquals(100000, (float) $resource['payment_amount']);
        $this->assertEquals('2026-08-05 10:00:00', $resource['payment_date']);
        $this->assertEquals($payment->order->order_number, $resource['order']['order_number']);
        $this->assertEquals($payment->order->order_status, $resource['order']['order_status']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
