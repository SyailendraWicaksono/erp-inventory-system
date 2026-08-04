<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function getAll(): Collection
    {
        return Payment::with('order')->latest('id')->get();
    }

    public function getById(int $id): Payment
    {
        return Payment::with('order')->findOrFail($id);
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            $this->assertOrderPayable($order);
            $this->assertNoExistingPayment($order);

            $amount = round((float) $data['payment_amount'], 2);
            $this->assertAmountMatchesTotal($amount, $order);

            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
                'payment_amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now(),
            ]);

            return $payment->load('order');
        });
    }

    public function update(int $id, array $data): Payment
    {
        return DB::transaction(function () use ($id, $data) {
            $payment = Payment::whereKey($id)->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertRecorded($payment);

            $newAmount = array_key_exists('payment_amount', $data)
                ? round((float) $data['payment_amount'], 2)
                : (float) $payment->payment_amount;
            $this->assertAmountMatchesTotal($newAmount, $order);

            $attributes = [];
            foreach (['payment_method', 'payment_amount', 'payment_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if (array_key_exists('payment_amount', $attributes)) {
                $attributes['payment_amount'] = $newAmount;
            }

            $payment->update($attributes);

            return $payment->refresh()->load('order');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $payment = Payment::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertRecorded($payment);

            $payment->delete();
        });
    }

    public function verify(int $id): Payment
    {
        return DB::transaction(function () use ($id) {
            $payment = Payment::whereKey($id)->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertRecorded($payment);
            $this->assertOrderFinished($order);
            $this->assertAmountMatchesTotal((float) $payment->payment_amount, $order);

            $payment->update([
                'payment_status' => Payment::PAYMENT_STATUS_PAID,
                'payment_date' => $payment->payment_date ?? now(),
            ]);
            $order->update(['order_status' => Order::ORDER_STATUS_COMPLETED]);

            return $payment->refresh()->load('order');
        });
    }

    private function assertOrderPayable(Order $order): void
    {
        if (! in_array($order->order_status, [Order::ORDER_STATUS_CONFIRMED, Order::ORDER_STATUS_FINISHED], true)) {
            throw ValidationException::withMessages([
                'order_id' => ['Payment can only be recorded for a confirmed or finished order.'],
            ]);
        }
    }

    private function assertNoExistingPayment(Order $order): void
    {
        if ($order->payment()->exists()) {
            throw ValidationException::withMessages([
                'order_id' => ['The order already has a payment.'],
            ]);
        }
    }

    private function assertRecorded(Payment $payment): void
    {
        if ($payment->payment_status !== Payment::PAYMENT_STATUS_RECORDED) {
            throw ValidationException::withMessages([
                'payment_status' => ['Only a recorded payment can be modified.'],
            ]);
        }
    }

    private function assertOrderFinished(Order $order): void
    {
        if ($order->order_status !== Order::ORDER_STATUS_FINISHED) {
            throw ValidationException::withMessages([
                'order_id' => ['Only a finished order can be completed by payment verification.'],
            ]);
        }
    }

    private function assertAmountMatchesTotal(float $amount, Order $order): void
    {
        if (round($amount, 2) !== round((float) $order->total_price, 2)) {
            throw ValidationException::withMessages([
                'payment_amount' => ['Payment amount must equal the order total.'],
            ]);
        }
    }
}
