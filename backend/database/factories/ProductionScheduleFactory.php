<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => fn () => Order::create([
                'customer_id' => Customer::factory()->create()->id,
                'order_number' => fake()->unique()->numerify('ORD-#####'),
                'pickup_datetime' => now()->addDay(),
                'order_status' => Order::ORDER_STATUS_CONFIRMED,
                'total_price' => 0,
            ])->id,
            'start_time' => null,
            'end_time' => null,
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ];
    }
}
