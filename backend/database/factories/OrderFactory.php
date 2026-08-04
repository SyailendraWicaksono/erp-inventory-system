<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => fn () => Customer::factory()->create()->id,
            'order_number' => fake()->unique()->numerify('ORD-#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_PENDING,
            'total_price' => 0,
        ];
    }
}
