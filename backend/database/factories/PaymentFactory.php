<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => fn () => Order::factory()->create([
                'order_status' => Order::ORDER_STATUS_CONFIRMED,
                'total_price' => 100000,
            ])->id,
            'payment_method' => fake()->randomElement(['cash', 'transfer', 'e-wallet']),
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
            'payment_amount' => 100000,
            'payment_date' => null,
        ];
    }
}
