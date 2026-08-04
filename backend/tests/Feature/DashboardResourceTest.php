<?php

namespace Tests\Feature;

use App\Http\Resources\DashboardInventoryResource;
use App\Http\Resources\DashboardOrderResource;
use App\Http\Resources\DashboardPaymentResource;
use App\Http\Resources\DashboardProductionResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RawMaterial;
use App\Services\DashboardService;
use App\Services\InventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResourceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService(new InventoryAvailabilityService);
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => today()->addHours(2),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 100000,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_order_resource_has_expected_shape(): void
    {
        $this->createOrder();

        $resource = (new DashboardOrderResource($this->service->getOrdersSummary()))->resolve();

        $this->assertArrayHasKey('total_today', $resource);
        $this->assertArrayHasKey('by_status', $resource);
        $this->assertArrayHasKey('active_orders', $resource);
        $this->assertSame(1, $resource['total_today']);
        $this->assertCount(1, $resource['active_orders']);
        $this->assertArrayHasKey('order_number', $resource['active_orders'][0]);
        $this->assertArrayHasKey('customer', $resource['active_orders'][0]);
        $this->assertArrayHasKey('name', $resource['active_orders'][0]['customer']);
    }

    public function test_production_resource_has_expected_shape(): void
    {
        $order = $this->createOrder();
        $order->productionSchedule()->create([
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'production_status' => 'scheduled',
        ]);

        $resource = (new DashboardProductionResource($this->service->getProductionSummary()))->resolve();

        $this->assertArrayHasKey('total_today', $resource);
        $this->assertArrayHasKey('by_status', $resource);
        $this->assertArrayHasKey('active_schedules', $resource);
        $this->assertSame(1, $resource['total_today']);
        $this->assertCount(1, $resource['active_schedules']);
        $this->assertArrayHasKey('order_number', $resource['active_schedules'][0]);
        $this->assertArrayHasKey('production_status', $resource['active_schedules'][0]);
        $this->assertArrayHasKey('pickup_datetime', $resource['active_schedules'][0]);
    }

    public function test_inventory_resource_has_expected_shape(): void
    {
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 5, 'unit' => 'kg']);

        $resource = (new DashboardInventoryResource($this->service->getInventorySummary()))->resolve();

        $this->assertArrayHasKey('total', $resource);
        $this->assertArrayHasKey('by_status', $resource);
        $this->assertArrayHasKey('at_risk', $resource);
        $this->assertSame(1, $resource['total']);
        $this->assertCount(1, $resource['at_risk']);
        $this->assertArrayHasKey('name', $resource['at_risk'][0]);
        $this->assertArrayHasKey('status', $resource['at_risk'][0]);
    }

    public function test_payment_resource_has_expected_shape(): void
    {
        $order = $this->createOrder();
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
            'payment_amount' => 100000,
            'payment_date' => now(),
        ]);

        $resource = (new DashboardPaymentResource($this->service->getPaymentsSummary()))->resolve();

        $this->assertArrayHasKey('paid_total_today', $resource);
        $this->assertArrayHasKey('outstanding_total', $resource);
        $this->assertArrayHasKey('by_status', $resource);
        $this->assertArrayHasKey('recorded_today', $resource);
        $this->assertCount(1, $resource['recorded_today']);
        $this->assertArrayHasKey('order_number', $resource['recorded_today'][0]);
        $this->assertArrayHasKey('payment_amount', $resource['recorded_today'][0]);
    }
}
