<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionSchedule;
use App\Models\RawMaterial;
use App\Services\DashboardService;
use App\Services\InventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
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
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => $overrides['pickup_datetime'] ?? now()->addDay(),
            'order_status' => $overrides['order_status'] ?? Order::ORDER_STATUS_PENDING,
            'total_price' => $overrides['total_price'] ?? 100000,
        ]);

        if (array_key_exists('created_at', $overrides)) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $overrides['created_at']]);
        }

        return $order->refresh();
    }

    private function createSchedule(Order $order, array $overrides = []): ProductionSchedule
    {
        return ProductionSchedule::create(array_merge([
            'order_id' => $order->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ], $overrides));
    }

    private function createPayment(Order $order, array $overrides = []): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_status' => $overrides['payment_status'] ?? Payment::PAYMENT_STATUS_RECORDED,
            'payment_amount' => $overrides['payment_amount'] ?? 100000,
            'payment_date' => $overrides['payment_date'] ?? now(),
        ]);

        $timestamps = [];
        foreach (['created_at', 'updated_at'] as $timestamp) {
            if (array_key_exists($timestamp, $overrides)) {
                $timestamps[$timestamp] = $overrides[$timestamp];
            }
        }
        if ($timestamps) {
            DB::table('payments')->where('id', $payment->id)->update($timestamps);
        }

        return $payment->refresh();
    }

    public function test_orders_summary_counts_only_today_orders(): void
    {
        $this->createOrder();
        $this->createOrder(['created_at' => now()->subDay()]);

        $summary = $this->service->getOrdersSummary();

        $this->assertSame(1, $summary['total_today']);
    }

    public function test_orders_summary_breaks_down_by_status(): void
    {
        $this->createOrder(['order_status' => Order::ORDER_STATUS_PENDING]);
        $this->createOrder(['order_status' => Order::ORDER_STATUS_CONFIRMED]);
        $this->createOrder(['order_status' => Order::ORDER_STATUS_FINISHED]);
        $this->createOrder(['order_status' => Order::ORDER_STATUS_COMPLETED]);

        $summary = $this->service->getOrdersSummary();

        $this->assertSame(4, $summary['total_today']);
        $this->assertSame([
            Order::ORDER_STATUS_PENDING => 1,
            Order::ORDER_STATUS_CONFIRMED => 1,
            Order::ORDER_STATUS_FINISHED => 1,
            Order::ORDER_STATUS_COMPLETED => 1,
        ], $summary['by_status']);
    }

    public function test_orders_summary_active_orders_exclude_completed_newest_first(): void
    {
        $this->createOrder(['order_status' => Order::ORDER_STATUS_COMPLETED]);
        $second = $this->createOrder(['order_status' => Order::ORDER_STATUS_CONFIRMED]);

        $summary = $this->service->getOrdersSummary();

        $this->assertCount(1, $summary['active_orders']);
        $this->assertSame($second->id, $summary['active_orders']->first()->id);
    }

    public function test_orders_summary_empty_database_returns_defaults(): void
    {
        $summary = $this->service->getOrdersSummary();

        $this->assertSame(0, $summary['total_today']);
        $this->assertSame([
            Order::ORDER_STATUS_PENDING => 0,
            Order::ORDER_STATUS_CONFIRMED => 0,
            Order::ORDER_STATUS_FINISHED => 0,
            Order::ORDER_STATUS_COMPLETED => 0,
        ], $summary['by_status']);
        $this->assertTrue($summary['active_orders']->isEmpty());
    }

    public function test_production_summary_uses_order_pickup_datetime(): void
    {
        $todayOrder = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $tomorrowOrder = $this->createOrder(['pickup_datetime' => now()->addDay()]);
        $this->createSchedule($todayOrder);
        $this->createSchedule($tomorrowOrder);

        $summary = $this->service->getProductionSummary();

        $this->assertSame(1, $summary['total_today']);
    }

    public function test_production_summary_breaks_down_by_status(): void
    {
        $scheduled = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $inProgress = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $finished = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $this->createSchedule($scheduled, ['production_status' => ProductionSchedule::STATUS_SCHEDULED]);
        $this->createSchedule($inProgress, ['production_status' => ProductionSchedule::STATUS_IN_PROGRESS]);
        $this->createSchedule($finished, ['production_status' => ProductionSchedule::STATUS_FINISHED]);

        $summary = $this->service->getProductionSummary();

        $this->assertSame(3, $summary['total_today']);
        $this->assertSame([
            ProductionSchedule::STATUS_SCHEDULED => 1,
            ProductionSchedule::STATUS_IN_PROGRESS => 1,
            ProductionSchedule::STATUS_FINISHED => 1,
        ], $summary['by_status']);
    }

    public function test_production_summary_active_schedules_exclude_finished(): void
    {
        $scheduledOrder = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $finishedOrder = $this->createOrder(['pickup_datetime' => now()->addHours(2)]);
        $this->createSchedule($scheduledOrder, ['production_status' => ProductionSchedule::STATUS_SCHEDULED]);
        $this->createSchedule($finishedOrder, ['production_status' => ProductionSchedule::STATUS_FINISHED]);

        $summary = $this->service->getProductionSummary();

        $this->assertCount(1, $summary['active_schedules']);
        $this->assertSame($scheduledOrder->id, $summary['active_schedules']->first()->order_id);
    }

    public function test_inventory_summary_counts_availability_statuses(): void
    {
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Sugar', 'stock_quantity' => 5, 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Butter', 'stock_quantity' => 0, 'unit' => 'kg']);

        $summary = $this->service->getInventorySummary();

        $this->assertSame(3, $summary['total']);
        $this->assertSame([
            'available' => 1,
            'low' => 1,
            'out_of_stock' => 1,
        ], $summary['by_status']);
    }

    public function test_inventory_summary_at_risk_excludes_available(): void
    {
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Sugar', 'stock_quantity' => 5, 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Butter', 'stock_quantity' => 0, 'unit' => 'kg']);

        $summary = $this->service->getInventorySummary();

        $this->assertCount(2, $summary['at_risk']);
        $this->assertContains('Sugar', $summary['at_risk']->pluck('name')->all());
        $this->assertContains('Butter', $summary['at_risk']->pluck('name')->all());
    }

    public function test_payments_paid_total_today_uses_paid_on_date(): void
    {
        $order = $this->createOrder();
        $this->createPayment($order, [
            'payment_status' => Payment::PAYMENT_STATUS_PAID,
            'updated_at' => now(),
        ]);
        $this->createPayment($this->createOrder(), [
            'payment_status' => Payment::PAYMENT_STATUS_PAID,
            'updated_at' => now()->subDay(),
        ]);

        $summary = $this->service->getPaymentsSummary();

        $this->assertEquals(100000, $summary['paid_total_today']);
    }

    public function test_payments_outstanding_total_is_all_time(): void
    {
        $this->createPayment($this->createOrder(), ['payment_date' => now()]);
        $this->createPayment($this->createOrder(), ['payment_date' => now()->subDay()]);

        $summary = $this->service->getPaymentsSummary();

        $this->assertEquals(200000, $summary['outstanding_total']);
    }

    public function test_payments_recorded_today_lists_only_today_recorded(): void
    {
        $today = $this->createPayment($this->createOrder(), ['payment_date' => now()]);
        $this->createPayment($this->createOrder(), ['payment_date' => now()->subDay()]);

        $summary = $this->service->getPaymentsSummary();

        $this->assertCount(1, $summary['recorded_today']);
        $this->assertSame($today->id, $summary['recorded_today']->first()->id);
    }

    public function test_payments_by_status_counts_both(): void
    {
        $this->createPayment($this->createOrder(), ['payment_status' => Payment::PAYMENT_STATUS_RECORDED]);
        $this->createPayment($this->createOrder(), ['payment_status' => Payment::PAYMENT_STATUS_PAID]);

        $summary = $this->service->getPaymentsSummary();

        $this->assertSame([
            Payment::PAYMENT_STATUS_RECORDED => 1,
            Payment::PAYMENT_STATUS_PAID => 1,
        ], $summary['by_status']);
    }

    public function test_overview_composes_all_sections(): void
    {
        $this->createOrder();
        RawMaterial::create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'kg']);

        $overview = $this->service->getOverview();

        $this->assertArrayHasKey('orders', $overview);
        $this->assertArrayHasKey('production', $overview);
        $this->assertArrayHasKey('inventory', $overview);
        $this->assertArrayHasKey('payments', $overview);
        $this->assertSame(1, $overview['orders']['total_today']);
        $this->assertSame(1, $overview['inventory']['total']);
    }
}
