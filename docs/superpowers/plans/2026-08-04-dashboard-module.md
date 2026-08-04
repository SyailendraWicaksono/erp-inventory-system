# Dashboard Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Dashboard module — five read-only aggregate endpoints giving the owner a daily operational summary of orders, production, inventory, and payments — following the established `Controller → Service → Resource` pattern.

**Architecture:** A single `DashboardService` with one summary method per section (`getOrdersSummary`, `getProductionSummary`, `getInventorySummary`, `getPaymentsSummary`) plus `getOverview()` that composes them. A thin `DashboardController` exposes `GET /api/dashboard` (aggregate) and four section endpoints. Each section has its own API Resource shaping the payload. Inventory classification is reused from the existing `InventoryAvailabilityService` (single source of truth). No new tables, migrations, or dependencies.

**Tech Stack:** PHP 8.3, Laravel (API-only), Eloquent ORM, API Resources / Dependency Injection, PHPUnit (in-memory SQLite via `RefreshDatabase`), Laravel Pint (PSR-12). No new third-party dependencies.

## Global Constraints

- Never modify existing migrations; never rename tables or columns. **This module adds no migrations.**
- Do not modify the Order, Production, Inventory, Recipe, Raw Material, Product, or Payment modules except `routes/api.php` (append routes + import).
- Keep controllers thin (no business logic in controllers).
- All dashboard metrics are read-only aggregates — no `DB::transaction` needed (no writes).
- Date basis: orders by `orders.created_at` today; production by the order's `orders.pickup_datetime` today (NOT `start_time`); payments paid KPI by `payments.updated_at` today (paid-on date); `outstanding_total` is **all-time** (`recorded` payments, unscoped); inventory is **live** (no date).
- Inventory availability classification comes from `InventoryAvailabilityService::getStatus()` — never reimplement the threshold.
- Monetary values returned via `(float)` cast (`total_price`, `payment_amount`, `stock_quantity`).
- All 2xx responses use the `{success, message, data}` envelope.
- PSR-12, PHP 8.3. Run `vendor/bin/pint --test` before finishing.
- PHP is **not on PATH** on this machine. Run tests from `backend/` using `C:\Users\Indra\Tools\PHP\php.exe`, e.g. `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=Dashboard`. Steps below write `php artisan ...`; substitute the full binary path or add it to PATH.
- Reference modules for pattern/style: Payment (`PaymentController/Service/Resource` + tests) and Inventory (`InventoryAvailabilityService` + `InventoryAvailabilityResource` + `InventoryAvailabilityTest`).

## File Structure

**Created:**
- `backend/app/Services/DashboardService.php` — five summary methods
- `backend/app/Http/Controllers/DashboardController.php` — thin controller, 5 actions
- `backend/app/Http/Resources/DashboardOrderResource.php`
- `backend/app/Http/Resources/DashboardProductionResource.php`
- `backend/app/Http/Resources/DashboardInventoryResource.php`
- `backend/app/Http/Resources/DashboardPaymentResource.php`
- `backend/tests/Feature/DashboardServiceTest.php`
- `backend/tests/Feature/DashboardResourceTest.php`
- `backend/tests/Feature/DashboardTest.php` (endpoints)

**Modified:**
- `backend/routes/api.php` — 5 dashboard GET routes + import
- `docs/context.md` — session context close-out (Task 5)

---

### Task 1: DashboardService + service tests

**Files:**
- Create: `backend/app/Services/DashboardService.php`
- Test: `backend/tests/Feature/DashboardServiceTest.php`

**Interfaces:**
- Consumes: `InventoryAvailabilityService::getStatus(): Collection` (each raw material has a dynamic `->status` of `available`/`low`/`out_of_stock`), `Order::ORDER_STATUS_*`, `ProductionSchedule::STATUS_*`, `Payment::PAYMENT_STATUS_*` constants.
- Produces: `DashboardService::getOverview(): array`, `getOrdersSummary(): array`, `getProductionSummary(): array`, `getInventorySummary(): array`, `getPaymentsSummary(): array`. Each summary returns `['total_...' => int, 'by_status' => array<string,int>, 'active_...' => Collection|'at_risk' => Collection|'recorded_today' => Collection]` — the resource tasks read exactly these keys.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/DashboardServiceTest.php`:

```php
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
        $todayOrder = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
        $tomorrowOrder = $this->createOrder(['pickup_datetime' => now()->addDay()]);
        $this->createSchedule($todayOrder);
        $this->createSchedule($tomorrowOrder);

        $summary = $this->service->getProductionSummary();

        $this->assertSame(1, $summary['total_today']);
    }

    public function test_production_summary_breaks_down_by_status(): void
    {
        $scheduled = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
        $inProgress = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
        $finished = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
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
        $scheduledOrder = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
        $finishedOrder = $this->createOrder(['pickup_datetime' => today()->addHours(2)]);
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DashboardServiceTest`
Expected: FAIL — `App\Services\DashboardService` does not exist.

- [ ] **Step 3: Create `app/Services/DashboardService.php`**

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionSchedule;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        private readonly InventoryAvailabilityService $inventoryAvailabilityService,
    ) {}

    public function getOverview(): array
    {
        return [
            'orders' => $this->getOrdersSummary(),
            'production' => $this->getProductionSummary(),
            'inventory' => $this->getInventorySummary(),
            'payments' => $this->getPaymentsSummary(),
        ];
    }

    public function getOrdersSummary(): array
    {
        $todayOrders = Order::query()->whereDate('created_at', today());

        return [
            'total_today' => (clone $todayOrders)->count(),
            'by_status' => $this->statusCounts($todayOrders, 'order_status', [
                Order::ORDER_STATUS_PENDING => 0,
                Order::ORDER_STATUS_CONFIRMED => 0,
                Order::ORDER_STATUS_FINISHED => 0,
                Order::ORDER_STATUS_COMPLETED => 0,
            ]),
            'active_orders' => (clone $todayOrders)
                ->with('customer')
                ->where('order_status', '!=', Order::ORDER_STATUS_COMPLETED)
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    public function getProductionSummary(): array
    {
        $todaySchedules = ProductionSchedule::query()->whereHas('order', function (Builder $query): void {
            $query->whereDate('pickup_datetime', today());
        });

        return [
            'total_today' => (clone $todaySchedules)->count(),
            'by_status' => $this->statusCounts($todaySchedules, 'production_status', [
                ProductionSchedule::STATUS_SCHEDULED => 0,
                ProductionSchedule::STATUS_IN_PROGRESS => 0,
                ProductionSchedule::STATUS_FINISHED => 0,
            ]),
            'active_schedules' => (clone $todaySchedules)
                ->with('order')
                ->whereIn('production_status', [
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_IN_PROGRESS,
                ])
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    public function getInventorySummary(): array
    {
        $statuses = $this->inventoryAvailabilityService->getStatus();

        return [
            'total' => $statuses->count(),
            'by_status' => [
                'available' => $statuses->where('status', 'available')->count(),
                'low' => $statuses->where('status', 'low')->count(),
                'out_of_stock' => $statuses->where('status', 'out_of_stock')->count(),
            ],
            'at_risk' => $statuses
                ->reject(fn ($material) => $material->status === 'available')
                ->take(10)
                ->values(),
        ];
    }

    public function getPaymentsSummary(): array
    {
        return [
            'paid_total_today' => (float) Payment::query()
                ->where('payment_status', Payment::PAYMENT_STATUS_PAID)
                ->whereDate('updated_at', today())
                ->sum('payment_amount'),
            'outstanding_total' => (float) Payment::query()
                ->where('payment_status', Payment::PAYMENT_STATUS_RECORDED)
                ->sum('payment_amount'),
            'by_status' => $this->statusCounts(Payment::query(), 'payment_status', [
                Payment::PAYMENT_STATUS_RECORDED => 0,
                Payment::PAYMENT_STATUS_PAID => 0,
            ]),
            'recorded_today' => Payment::query()
                ->with('order')
                ->where('payment_status', Payment::PAYMENT_STATUS_RECORDED)
                ->whereDate('payment_date', today())
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    private function statusCounts(Builder $query, string $column, array $defaults): array
    {
        $counts = (clone $query)
            ->selectRaw("$column, COUNT(*) as total")
            ->groupBy($column)
            ->get()
            ->pluck('total', $column)
            ->map(fn ($count) => (int) $count)
            ->all();

        return array_replace($defaults, $counts);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=DashboardServiceTest`
Expected: PASS (14 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/DashboardService.php backend/tests/Feature/DashboardServiceTest.php
git commit -m "feat: add dashboard summary service"
```

---

### Task 2: Dashboard API resources + resource tests

**Files:**
- Create: `backend/app/Http/Resources/DashboardOrderResource.php`
- Create: `backend/app/Http/Resources/DashboardProductionResource.php`
- Create: `backend/app/Http/Resources/DashboardInventoryResource.php`
- Create: `backend/app/Http/Resources/DashboardPaymentResource.php`
- Test: `backend/tests/Feature/DashboardResourceTest.php`

**Interfaces:**
- Consumes: the four summary arrays from `DashboardService` (Task 1) — exact keys: `total_today`/`total`, `by_status`, `active_orders`, `active_schedules`, `at_risk`, `recorded_today`; collections contain `Order` (customer loaded), `ProductionSchedule` (order loaded), `RawMaterial` (status attribute set), `Payment` (order loaded).
- Produces: four `JsonResource` classes, each with a `toArray(Request $request): array` that shapes one section. The controller (Task 3) instantiates them with `new DashboardXResource($summary)`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/DashboardResourceTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DashboardResourceTest`
Expected: FAIL — `App\Http\Resources\DashboardOrderResource` does not exist.

- [ ] **Step 3: Create the four resources**

Create `app/Http/Resources/DashboardOrderResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_today' => $this->resource['total_today'],
            'by_status' => $this->resource['by_status'],
            'active_orders' => $this->resource['active_orders']->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'pickup_datetime' => $order->pickup_datetime,
                'order_status' => $order->order_status,
                'total_price' => (float) $order->total_price,
                'customer' => ['name' => $order->customer?->name],
            ])->all(),
        ];
    }
}
```

Create `app/Http/Resources/DashboardProductionResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\ProductionSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_today' => $this->resource['total_today'],
            'by_status' => $this->resource['by_status'],
            'active_schedules' => $this->resource['active_schedules']->map(fn (ProductionSchedule $schedule) => [
                'id' => $schedule->id,
                'order_number' => $schedule->order?->order_number,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'production_status' => $schedule->production_status,
                'pickup_datetime' => $schedule->order?->pickup_datetime,
            ])->all(),
        ];
    }
}
```

Create `app/Http/Resources/DashboardInventoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'],
            'by_status' => $this->resource['by_status'],
            'at_risk' => $this->resource['at_risk']->map(fn (RawMaterial $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'unit' => $material->unit,
                'stock_quantity' => (float) $material->stock_quantity,
                'status' => $material->status,
            ])->all(),
        ];
    }
}
```

Create `app/Http/Resources/DashboardPaymentResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'paid_total_today' => $this->resource['paid_total_today'],
            'outstanding_total' => $this->resource['outstanding_total'],
            'by_status' => $this->resource['by_status'],
            'recorded_today' => $this->resource['recorded_today']->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'order_number' => $payment->order?->order_number,
                'payment_method' => $payment->payment_method,
                'payment_amount' => (float) $payment->payment_amount,
                'payment_date' => $payment->payment_date,
            ])->all(),
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=DashboardResourceTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/DashboardOrderResource.php backend/app/Http/Resources/DashboardProductionResource.php backend/app/Http/Resources/DashboardInventoryResource.php backend/app/Http/Resources/DashboardPaymentResource.php backend/tests/Feature/DashboardResourceTest.php
git commit -m "feat: add dashboard API resources"
```

---

### Task 3: DashboardController + routes + endpoint tests

**Files:**
- Create: `backend/app/Http/Controllers/DashboardController.php`
- Modify: `backend/routes/api.php` (add import + 5 routes)
- Test: `backend/tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `DashboardService` (Task 1) and the four resources (Task 2). Each summary's `getOverview()` composes the four; section methods return one section.
- Produces: routes `GET /api/dashboard`, `/api/dashboard/orders`, `/api/dashboard/production`, `/api/dashboard/inventory`, `/api/dashboard/payments`. The aggregate `/dashboard` payload nests the four sections under keys `orders`/`production`/`inventory`/`payments`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/DashboardTest.php`:

```php
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
            ->assertJsonStructure(['data' => ['paid_total_today', 'outstanding_total', 'by_status', 'recorded_today']]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL — routes `/api/dashboard` not registered (404s).

- [ ] **Step 3: Create `app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardInventoryResource;
use App\Http\Resources\DashboardOrderResource;
use App\Http\Resources\DashboardPaymentResource;
use App\Http\Resources\DashboardProductionResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard retrieved successfully',
            'data' => [
                'orders' => (new DashboardOrderResource($this->dashboardService->getOrdersSummary()))->resolve(),
                'production' => (new DashboardProductionResource($this->dashboardService->getProductionSummary()))->resolve(),
                'inventory' => (new DashboardInventoryResource($this->dashboardService->getInventorySummary()))->resolve(),
                'payments' => (new DashboardPaymentResource($this->dashboardService->getPaymentsSummary()))->resolve(),
            ],
        ]);
    }

    public function orders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Orders summary retrieved successfully',
            'data' => new DashboardOrderResource($this->dashboardService->getOrdersSummary()),
        ]);
    }

    public function production(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Production summary retrieved successfully',
            'data' => new DashboardProductionResource($this->dashboardService->getProductionSummary()),
        ]);
    }

    public function inventory(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Inventory summary retrieved successfully',
            'data' => new DashboardInventoryResource($this->dashboardService->getInventorySummary()),
        ]);
    }

    public function payments(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payments summary retrieved successfully',
            'data' => new DashboardPaymentResource($this->dashboardService->getPaymentsSummary()),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes** in `backend/routes/api.php`. Add the import at the top of the import block, alphabetically first (before `use App\Http\Controllers\InventoryAvailabilityController;`):

```php
use App\Http\Controllers\DashboardController;
```

Then append at the end of the file (after the payments routes):

```php
Route::get('dashboard', [DashboardController::class, 'index']);
Route::get('dashboard/orders', [DashboardController::class, 'orders']);
Route::get('dashboard/production', [DashboardController::class, 'production']);
Route::get('dashboard/inventory', [DashboardController::class, 'inventory']);
Route::get('dashboard/payments', [DashboardController::class, 'payments']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Verify route registration**

Run: `php artisan route:list --path=api/dashboard`
Expected: 5 routes listed.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/DashboardController.php backend/routes/api.php backend/tests/Feature/DashboardTest.php
git commit -m "feat: add dashboard controller and routes"
```

---

### Task 4: Full verification

**Files:**
- None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: PASS (229 prior tests + 23 new Dashboard tests = **252 tests**; confirm the suite is fully green).

- [ ] **Step 2: Run Pint**

Run: `php vendor/bin/pint --test`
Expected: PASS. If it reports files, run `php vendor/bin/pint` to fix, then re-run `php vendor/bin/pint --test`.

- [ ] **Step 3: Re-run the full suite after any Pint fixes**

Run: `php artisan test`
Expected: PASS. Record the final test and assertion counts (needed in Task 5).

---

### Task 5: Documentation close-out

**Files:**
- Modify: `docs/context.md`

**Note:** `docs/context.md` currently reflects the Payment close-out (228 tests / 543 assertions). This task updates it to the Dashboard completion state.

- [ ] **Step 1: Update the session context** in `docs/context.md`:

- Add `- Dashboard` to the **Completed modules** list (after `- Payment`).
- In **Session focus**, replace `- Dashboard` with the next module: `- Authentication`.
- In **Next modules**, remove the `- Dashboard` line (leave `Authentication`, `Android application`).
- In **Current status**, add lines reflecting Dashboard completion, e.g.:
  ```
  - Dashboard module completed.
  - Daily operational summaries implemented (orders, production, inventory, payments).
  ```
- Replace the test/assertion count lines with the final counts recorded in Task 4.
- In **Latest commit**, replace the Payment commit line with the latest Dashboard commit hash + message.
- In **Notes**, add `- Use the Dashboard module as a reference.`

- [ ] **Step 2: Commit**

```bash
git add docs/context.md
git commit -m "docs: update session context after dashboard completion"
```

---

## Self-Review

Spec coverage check (spec: `docs/superpowers/specs/2026-08-04-dashboard-module-design.md`):

- **5 endpoints incl. aggregate `/dashboard`** → Task 3. ✔
- **Orders: today by created_at, total + by_status + active (non-completed) list** → Task 1 `getOrdersSummary`, Task 2 resource. ✔
- **Production: today by order pickup_datetime, by_status + active (scheduled+in_progress) list** → Task 1 `getProductionSummary`. ✔
- **Inventory: live, availability counts + at-risk (low+out_of_stock)** → Task 1 `getInventorySummary` via `InventoryAvailabilityService::getStatus()`. ✔
- **Payments: paid KPIs by paid-on updated_at today, outstanding all-time, recorded list by payment_date today** → Task 1 `getPaymentsSummary`. ✔
- **Aggregate composes four sections, no fifth resource** → Task 3 `index()`. ✔
- **Thin controller, service holds aggregation, envelope, no new tables/migrations/deps** → Global Constraints + Tasks 1/3. ✔
- **Testing: service/endpoint/resource suites, in-memory SQLite RefreshDatabase** → Tasks 1–3. ✔
- **Pint + full suite** → Task 4. ✔
- **Documentation close-out** → Task 5. ✔

Placeholder scan: all code blocks are complete; no TBD/TODO. Type/name consistency verified: `DashboardService` method names (`getOverview`/`getOrdersSummary`/`getProductionSummary`/`getInventorySummary`/`getPaymentsSummary`), summary array keys (`total_today`/`total`, `by_status`, `active_orders`, `active_schedules`, `at_risk`, `recorded_today`), resource class names (`DashboardOrderResource`/`DashboardProductionResource`/`DashboardInventoryResource`/`DashboardPaymentResource`), and route paths (`dashboard`, `dashboard/orders`, `dashboard/production`, `dashboard/inventory`, `dashboard/payments`) match across all tasks.
