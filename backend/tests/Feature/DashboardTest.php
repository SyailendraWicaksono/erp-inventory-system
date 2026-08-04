<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => today()->addHours(2),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 100000,
            'created_at' => now(),
        ]);
    }

    private function seedData(): void
    {
        $order = $this->createOrder();
        $order->productionSchedule()->create([
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'production_status' => 'scheduled',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
            'payment_amount' => 100000,
            'payment_date' => now(),
        ]);
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 5, 'unit' => 'kg']);
    }

    public function test_index_returns_all_sections(): void
    {
        $this->seedData();

        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['orders', 'production', 'inventory', 'payments']]);
    }

    public function test_orders_endpoint_returns_summary(): void
    {
        $this->createOrder();

        $this->getJson('/api/dashboard/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_today', 'by_status', 'active_orders']])
            ->assertJsonPath('data.total_today', 1);
    }

    public function test_production_endpoint_returns_summary(): void
    {
        $order = $this->createOrder();
        $order->productionSchedule()->create([
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'production_status' => 'scheduled',
        ]);

        $this->getJson('/api/dashboard/production')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_today', 'by_status', 'active_schedules']])
            ->assertJsonPath('data.total_today', 1);
    }

    public function test_inventory_endpoint_returns_summary(): void
    {
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'kg']);

        $this->getJson('/api/dashboard/inventory')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'by_status', 'at_risk']])
            ->assertJsonPath('data.total', 1);
    }

    public function test_payments_endpoint_returns_summary(): void
    {
        $order = $this->createOrder();
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
            'payment_amount' => 100000,
            'payment_date' => now(),
        ]);

        $this->getJson('/api/dashboard/payments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['paid_total_today', 'outstanding_total', 'by_status', 'recorded_today']])
            ->assertJsonPath('data.outstanding_total', 100000);
    }
}
