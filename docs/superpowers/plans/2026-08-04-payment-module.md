# Payment Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Payment module — payment CRUD (`recorded → paid`) plus the `finished → completed` order transition (BR-016) via payment verification — following the established `Controller → Request → Service → Resource` pattern.

**Architecture:** A single full-settlement payment record per order (no migration; the existing `payments` table is used as-is). `POST /api/payments` records a payment as `recorded`; `PATCH /api/payments/{id}/verify` transitions it to `paid` and, because verification requires the order to already be `finished`, transitions the order to `completed` in the same transaction. Verification is the **only** path from `finished` to `completed`; the Production module is untouched.

**Tech Stack:** PHP 8.3, Laravel (API-only), Eloquent ORM, Laravel Form Requests / API Resources / Dependency Injection, PHPUnit (in-memory SQLite via `RefreshDatabase`), Laravel Pint (PSR-12). No new third-party dependencies.

## Global Constraints

- Never modify existing migrations; never rename tables or columns. **This module adds no migrations.**
- Do not modify the Order, Production, Inventory, Recipe, Raw Material, or Product modules.
- Keep controllers thin (no business logic in controllers).
- All write operations run inside a single `DB::transaction`.
- Monetary values compared/stored with `round(..., 2)`; `payment_amount` must equal `orders.total_price`.
- Payment statuses: `Payment::PAYMENT_STATUS_RECORDED = 'recorded'`, `Payment::PAYMENT_STATUS_PAID = 'paid'`. Order statuses come from `Order::ORDER_STATUS_*` constants (`confirmed`, `finished`, `completed`).
- Lock-ordering invariant (deadlock-free): **order row before payment row** when both are locked.
- Business guards throw `Illuminate\Validation\ValidationException` (→ HTTP 422). 404 via `findOrFail`.
- All 2xx responses use the `{success, message, data}` envelope; 404/422 use standard Laravel shapes.
- PSR-12, PHP 8.3. Run `vendor/bin/pint --test` before finishing.
- PHP is **not on PATH** on this machine. Run tests from `backend/` using `C:\Users\Indra\Tools\PHP\php.exe`, e.g. `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=Payment`. Steps below write `php artisan ...`; substitute the full binary path or add it to PATH.
- Reference modules for pattern/style: Production (`ProductionScheduleController/Service/Resource/Factory` + tests) and Order (`OrderController/Service/Resource/Factory` + tests).

## File Structure

**Created:**
- `backend/database/factories/PaymentFactory.php` — factory with inline confirmed order
- `backend/app/Http/Requests/PaymentRequest.php` — validation
- `backend/app/Http/Resources/PaymentResource.php` — payment + nested order
- `backend/app/Services/PaymentService.php` — CRUD + verify lifecycle
- `backend/app/Http/Controllers/PaymentController.php` — thin controller
- `backend/tests/Feature/PaymentModelTest.php`
- `backend/tests/Feature/PaymentRequestTest.php`
- `backend/tests/Feature/PaymentResourceTest.php`
- `backend/tests/Feature/PaymentServiceTest.php`
- `backend/tests/Feature/PaymentTest.php` (endpoints)

**Modified:**
- `backend/app/Models/Payment.php` — add status constants
- `backend/routes/api.php` — payments resource + verify route
- `docs/context.md` — session context close-out (Task 7)

---

### Task 1: Payment model constants + PaymentFactory + model tests

**Files:**
- Modify: `backend/app/Models/Payment.php` (add two class constants)
- Create: `backend/database/factories/PaymentFactory.php`
- Test: `backend/tests/Feature/PaymentModelTest.php`

**Interfaces:**
- Produces: `Payment::PAYMENT_STATUS_RECORDED` (`'recorded'`), `Payment::PAYMENT_STATUS_PAID` (`'paid'`), `Payment::factory()` creating a payment whose `order` is a confirmed `Order` with `total_price = 100000`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/PaymentModelTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentModelTest`
Expected: FAIL — `Payment::PAYMENT_STATUS_RECORDED` undefined and `Payment::factory()` not registered.

- [ ] **Step 3: Add the status constants** to `backend/app/Models/Payment.php` — insert after `use HasFactory;`:

```php
    public const PAYMENT_STATUS_RECORDED = 'recorded';

    public const PAYMENT_STATUS_PAID = 'paid';
```

- [ ] **Step 4: Create `database/factories/PaymentFactory.php`** (reuses `OrderFactory`):

```php
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
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentModelTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Models/Payment.php backend/database/factories/PaymentFactory.php backend/tests/Feature/PaymentModelTest.php
git commit -m "feat: add payment model constants and factory"
```

---

### Task 2: PaymentRequest validation

**Files:**
- Create: `backend/app/Http/Requests/PaymentRequest.php`
- Test: `backend/tests/Feature/PaymentRequestTest.php`

**Interfaces:**
- Produces: `PaymentRequest` form request with `rules()` — POST `required`, PUT `sometimes` for `order_id`, `payment_method`, `payment_amount`; `payment_date` always nullable.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/PaymentRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new PaymentRequest)->rules();
    }

    private function createOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 100000,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'order_id' => $this->createOrder()->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ];
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make($this->validPayload(), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_order_id_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['order_id']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_order_id_must_exist(): void
    {
        $data = $this->validPayload();
        $data['order_id'] = 999999;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_payment_method_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['payment_method']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_method', $validator->errors()->toArray());
    }

    public function test_payment_amount_is_required_and_positive(): void
    {
        $data = $this->validPayload();
        $data['payment_amount'] = 0;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_amount', $validator->errors()->toArray());
    }

    public function test_payment_date_is_nullable(): void
    {
        $data = $this->validPayload();
        $data['payment_date'] = null;

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_allows_partial_payload(): void
    {
        $request = PaymentRequest::create('/api/payments/1', 'PUT', [
            'payment_date' => now()->format('Y-m-d H:i:s'),
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentRequestTest`
Expected: FAIL — `App\Http\Requests\PaymentRequest` does not exist.

- [ ] **Step 3: Create `app/Http/Requests/PaymentRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $outer = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'order_id' => [$outer, 'integer', 'exists:orders,id'],
            'payment_method' => [$outer, 'string', 'max:255'],
            'payment_amount' => [$outer, 'numeric', 'gt:0'],
            'payment_date' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentRequestTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/PaymentRequest.php backend/tests/Feature/PaymentRequestTest.php
git commit -m "feat: add payment form request validation"
```

---

### Task 3: PaymentResource

**Files:**
- Create: `backend/app/Http/Resources/PaymentResource.php`
- Test: `backend/tests/Feature/PaymentResourceTest.php`

**Interfaces:**
- Consumes: `Payment` model with loaded `order` relation.
- Produces: `PaymentResource` — array keys `id`, `order_id`, `payment_method`, `payment_status`, `payment_amount`, `payment_date`, `order` (with `id`, `order_number`, `pickup_datetime`, `order_status`, `total_price`), `created_at`, `updated_at`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/PaymentResourceTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentResourceTest`
Expected: FAIL — `App\Http\Resources\PaymentResource` does not exist.

- [ ] **Step 3: Create `app/Http/Resources/PaymentResource.php`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_amount' => $this->payment_amount,
            'payment_date' => $this->payment_date,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'pickup_datetime' => $this->order->pickup_datetime,
                'order_status' => $this->order->order_status,
                'total_price' => $this->order->total_price,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentResourceTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/PaymentResource.php backend/tests/Feature/PaymentResourceTest.php
git commit -m "feat: add payment API resource"
```

---

### Task 4: PaymentService

**Files:**
- Create: `backend/app/Services/PaymentService.php`
- Test: `backend/tests/Feature/PaymentServiceTest.php`

**Interfaces:**
- Consumes: `Payment` model constants (Task 1), `Order::ORDER_STATUS_*` constants.
- Produces: `PaymentService` with `getAll(): Collection`, `getById(int $id): Payment`, `create(array $data): Payment`, `update(int $id, array $data): Payment`, `delete(int $id): void`, `verify(int $id): Payment`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/PaymentServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService;
    }

    private function createOrder(string $status = Order::ORDER_STATUS_CONFIRMED, float $total = 100000): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => $status,
            'total_price' => $total,
        ]);
    }

    private function payload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => (float) $order->total_price,
        ], $overrides);
    }

    public function test_get_all_returns_all_payments(): void
    {
        $orderA = $this->createOrder();
        $orderB = $this->createOrder();
        $this->service->create($this->payload($orderA));
        $this->service->create($this->payload($orderB));

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_creates_payment_as_recorded(): void
    {
        $order = $this->createOrder();

        $payment = $this->service->create($this->payload($order));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'order_id' => $order->id,
            'payment_status' => Payment::PAYMENT_STATUS_RECORDED,
        ]);
        $this->assertEquals(100000, (float) $payment->payment_amount);
    }

    public function test_create_defaults_payment_date_to_now(): void
    {
        $order = $this->createOrder();

        $payment = $this->service->create($this->payload($order));

        $this->assertNotNull($payment->payment_date);
    }

    public function test_create_rejects_pending_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_PENDING);

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_completed_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_COMPLETED);

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_second_payment_for_same_order(): void
    {
        $order = $this->createOrder();
        $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order));
    }

    public function test_create_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder();

        $this->expectException(ValidationException::class);

        $this->service->create($this->payload($order, ['payment_amount' => 50000]));
    }

    public function test_create_throws_when_order_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create(['order_id' => 999999, 'payment_method' => 'cash', 'payment_amount' => 100000]);
    }

    public function test_update_changes_method_amount_date_while_recorded(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $updated = $this->service->update($payment->id, [
            'payment_method' => 'transfer',
            'payment_amount' => 100000,
            'payment_date' => '2026-08-06 09:00:00',
        ]);

        $this->assertSame('transfer', $updated->payment_method);
        $this->assertEquals(100000, (float) $updated->payment_amount);
        $this->assertEquals('2026-08-06 09:00:00', $updated->payment_date);
    }

    public function test_update_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->update($payment->id, ['payment_method' => 'transfer']);
    }

    public function test_update_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->update($payment->id, ['payment_amount' => 1]);
    }

    public function test_delete_removes_recorded_payment(): void
    {
        $order = $this->createOrder();
        $payment = $this->service->create($this->payload($order));

        $this->service->delete($payment->id);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_delete_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($payment->id);
    }

    public function test_verify_marks_paid_and_completes_finished_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));

        $verified = $this->service->verify($payment->id);

        $this->assertSame(Payment::PAYMENT_STATUS_PAID, $verified->payment_status);
        $this->assertSame(Order::ORDER_STATUS_COMPLETED, $order->refresh()->order_status);
    }

    public function test_verify_rejects_duplicate_verify(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $this->service->verify($payment->id);

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }

    public function test_verify_rejects_order_not_finished(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_CONFIRMED);
        $payment = $this->service->create($this->payload($order));

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }

    public function test_verify_rejects_amount_mismatch(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->service->create($this->payload($order));
        $payment->update(['payment_amount' => 1]);

        $this->expectException(ValidationException::class);

        $this->service->verify($payment->id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentServiceTest`
Expected: FAIL — `App\Services\PaymentService` does not exist.

- [ ] **Step 3: Create `app/Services/PaymentService.php`**

```php
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentServiceTest`
Expected: PASS (17 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/PaymentService.php backend/tests/Feature/PaymentServiceTest.php
git commit -m "feat: add payment service with verify and completion lifecycle"
```

---

### Task 5: PaymentController + routes + endpoint tests

**Files:**
- Create: `backend/app/Http/Controllers/PaymentController.php`
- Modify: `backend/routes/api.php` (add import + two routes)
- Test: `backend/tests/Feature/PaymentTest.php`

**Interfaces:**
- Consumes: `PaymentService` (Task 4), `PaymentRequest` (Task 2), `PaymentResource` (Task 3).
- Produces: HTTP routes `GET|POST /api/payments`, `GET|PUT|DELETE /api/payments/{payment}`, `PATCH /api/payments/{payment}/verify`.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/PaymentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(string $status = Order::ORDER_STATUS_CONFIRMED, float $total = 100000): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => $status,
            'total_price' => $total,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $order = $this->createOrder();

        return array_merge([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ], $overrides);
    }

    public function test_index_returns_payments_latest_first_with_nested_order(): void
    {
        $this->postJson('/api/payments', $this->validPayload())->json('data');
        $second = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->getJson('/api/payments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second['id'])
            ->assertJsonPath('data.0.order.order_status', 'confirmed');
        $this->assertArrayHasKey('order', $response->json('data.0'));
    }

    public function test_store_creates_recorded_payment(): void
    {
        $response = $this->postJson('/api/payments', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'recorded')
            ->assertJsonPath('data.payment_method', 'cash');
        $this->assertDatabaseHas('payments', ['id' => $response->json('data.id')]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/payments', ['payment_method' => 'cash']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_rejects_pending_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_PENDING);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_payment(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->getJson("/api/payments/{$payment['id']}");

        $response->assertOk()->assertJsonPath('data.id', $payment['id']);
    }

    public function test_show_missing_payment_returns_404(): void
    {
        $this->getJson('/api/payments/999999')->assertNotFound();
    }

    public function test_update_changes_payment_while_recorded(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->putJson("/api/payments/{$payment['id']}", [
            'payment_method' => 'transfer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'transfer')
            ->assertJsonPath('data.payment_status', 'recorded');
    }

    public function test_update_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->putJson("/api/payments/{$payment['id']}", ['payment_method' => 'transfer']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_destroy_deletes_recorded_payment(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->deleteJson("/api/payments/{$payment['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('payments', ['id' => $payment['id']]);
    }

    public function test_destroy_rejects_paid_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->deleteJson("/api/payments/{$payment['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('payments', ['id' => $payment['id']]);
    }

    public function test_verify_marks_paid_and_completes_finished_order(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.order.order_status', 'completed');
        $this->assertSame('completed', Order::find($order->id)->order_status);
    }

    public function test_verify_rejects_order_not_finished(): void
    {
        $payment = $this->postJson('/api/payments', $this->validPayload())->json('data');

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_verify_rejects_duplicate_verify(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_FINISHED);
        $payment = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ])->json('data');
        $this->patchJson("/api/payments/{$payment['id']}/verify")->assertOk();

        $response = $this->patchJson("/api/payments/{$payment['id']}/verify");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_completed_order_rejects_new_payment(): void
    {
        $order = $this->createOrder(Order::ORDER_STATUS_COMPLETED);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'payment_amount' => 100000,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentTest`
Expected: FAIL — routes `/api/payments` not registered (404s).

- [ ] **Step 3: Create `app/Http/Controllers/PaymentController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(): JsonResponse
    {
        $payments = $this->paymentService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => PaymentResource::collection($payments),
        ]);
    }

    public function store(PaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(PaymentRequest $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->paymentService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully',
            'data' => null,
        ]);
    }

    public function verify(int $id): JsonResponse
    {
        $payment = $this->paymentService->verify($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully',
            'data' => new PaymentResource($payment),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes** in `backend/routes/api.php`. Add the import after the existing `use App\Http\Controllers\OrderController;` line:

```php
use App\Http\Controllers\PaymentController;
```

Then append after the existing orders routes (after line `Route::patch('orders/{order}/confirm', [OrderController::class, 'confirm']);`):

```php
Route::apiResource('payments', PaymentController::class);
Route::patch('payments/{payment}/verify', [PaymentController::class, 'verify']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentTest`
Expected: PASS (13 tests).

- [ ] **Step 6: Verify route registration**

Run: `php artisan route:list --path=api/payments`
Expected: 6 routes listed, including `PATCH api/payments/{payment}/verify`.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/PaymentController.php backend/routes/api.php backend/tests/Feature/PaymentTest.php
git commit -m "feat: add payment controller and routes"
```

---

### Task 6: Full verification

**Files:**
- None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: PASS (185 prior tests + 41 new Payment tests = **226 tests**; confirm the suite is fully green).

- [ ] **Step 2: Run Pint**

Run: `php vendor/bin/pint --test`
Expected: PASS. If it reports files, run `php vendor/bin/pint` to fix, then re-run `php vendor/bin/pint --test`.

- [ ] **Step 3: Re-run the full suite after any Pint fixes**

Run: `php artisan test`
Expected: PASS. Record the final test and assertion counts (needed in Task 7).

---

### Task 7: Documentation close-out

**Files:**
- Modify: `docs/context.md`

**Note:** `docs/context.md` currently carries uncommitted edits from the Order module session (the "Order module completed / 185 tests" block). This task's commit includes the whole file.

- [ ] **Step 1: Update the session context** in `docs/context.md`:

- Add `- Payment` to the **Completed modules** list (after `- Order`).
- In **Session focus**, replace `- Payment` with the next module: `- Dashboard`.
- In **Next modules**, remove the `- Payment` line (leave `Dashboard`, `Authentication`, `Android application`).
- In **Current status**, add lines reflecting Payment completion, e.g.:
  ```
  - Payment module completed.
  - Payment recording and verification implemented.
  - Order completion (finished -> completed) implemented via payment verification.
  ```
- Replace the `- 185 tests passed.` / `- 442 assertions passed.` lines with the final counts recorded in Task 6.
- In **Latest commit**, replace the order commit line with the latest Payment commit hash + message.
- In **Notes**, add `- Use the Payment module as a reference.`

- [ ] **Step 2: Commit**

```bash
git add docs/context.md
git commit -m "docs: update session context after payment completion"
```

---

## Self-Review

Spec coverage check (spec: `docs/superpowers/specs/2026-08-04-payment-module-design.md`):

- **Status lifecycle `recorded → paid`** → Tasks 1, 4, 5. ✔
- **CRUD endpoints + verify** → Task 5. ✔
- **BR-016 completion only via verify, Production untouched** → Task 4 `verify()` (order must be `finished`), no Production edits. ✔
- **Amount must equal order total** → Task 4 `assertAmountMatchesTotal` (create/update/verify). ✔
- **Payment status constants** → Task 1. ✔
- **Request validation (POST required / PUT sometimes, `gt:0`, nullable date)** → Task 2. ✔
- **Resource shape with nested order** → Task 3. ✔
- **No migration, no Production changes** → Global Constraints. ✔
- **Tests (model/request/resource/service/endpoints)** → Tasks 1–5. ✔
- **Pint + full suite** → Task 6. ✔
- **Documentation close-out** → Task 7. ✔

Placeholder scan: all code blocks are complete; no TBD/TODO. Type/name consistency verified: `Payment::PAYMENT_STATUS_RECORDED`/`PAYMENT_STATUS_PAID`, `PaymentService::create/update/delete/verify`, `PaymentResource` keys, and route params (`payments/{payment}/verify`) match across tasks.
