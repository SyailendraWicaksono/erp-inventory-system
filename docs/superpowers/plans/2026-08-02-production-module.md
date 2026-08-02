# Production Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Production module — production schedule CRUD plus `start`/`finish` lifecycle actions that check raw-material availability at start and deduct stock at finish — for the Laravel ERP backend.

**Architecture:** Follows the established `Controller → Request → Service → Model → Resource` pattern (mirrors the Inventory module). A `ProductionScheduleService` owns all business rules (BR-007..BR-013): order-status coupling, the one-active-schedule rule (BR-009), requirement computation from the latest recipe per product, advisory availability check at `start()`, and authoritative never-negative stock consumption at `finish()`, all inside DB transactions with row locks in the order `schedule → order → materials (sorted by id)`.

**Tech Stack:** Laravel 12 (PHP 8.3), Eloquent, PostgreSQL (prod) / in-memory SQLite (tests), PHPUnit, Laravel Pint. No new dependencies.

## Global Constraints

- Never modify migrations, never rename tables, never rename columns.
- Do **not** modify the Product, Recipe, Raw Material, or Inventory modules. Only `Order` and `ProductionSchedule` models get status constants; only `routes/api.php` is touched in the existing codebase.
- Keep controllers thin (no business logic in controllers).
- All writes inside a single `DB::transaction`; acquire `lockForUpdate()` row locks before any stock read-modify-write, in the order `schedule → order → materials (sorted by id)`.
- Re-validate row status **after** acquiring a row lock (double-start / double-finish safety).
- Every computed stock value is normalized with `round(..., 2)`.
- 2xx responses use the `{success, message, data}` envelope; 404/422 use the Laravel default shapes (envelope NOT asserted on 422).
- PSR-12, PHP 8.3. No code comments.
- **Environment (Windows, PowerShell 5.1):** PHP is NOT on PATH — always run `& "C:\Users\Indra\Tools\PHP\php.exe" <args>` with `workdir = backend`. Never use bash. Use `git` from the repo root.

---

### Task 1: Model constants, ProductionScheduleFactory, and model tests

**Files:**
- Modify: `backend/app/Models/Order.php`
- Modify: `backend/app/Models/ProductionSchedule.php`
- Create: `backend/database/factories/ProductionScheduleFactory.php`
- Create: `backend/tests/Feature/ProductionScheduleModelTest.php`

**Interfaces:**
- Produces: `Order::ORDER_STATUS_CONFIRMED = 'confirmed'`, `Order::ORDER_STATUS_FINISHED = 'finished'`; `ProductionSchedule::STATUS_SCHEDULED = 'scheduled'`, `STATUS_IN_PROGRESS = 'in_progress'`, `STATUS_FINISHED = 'finished'`. `ProductionSchedule::factory()` usable (creates a `Customer` + confirmed `Order` inline). Later tasks reference these constants and the factory.
- Consumes: existing `CustomerFactory` (`backend/database/factories/CustomerFactory.php`) and `RawMaterialFactory`.

- [ ] **Step 1: Add the constants to `Order`**

In `backend/app/Models/Order.php`, add these class constants at the top of the class body (before `$fillable`):

```php
    public const ORDER_STATUS_CONFIRMED = 'confirmed';
    public const ORDER_STATUS_FINISHED = 'finished';
```

- [ ] **Step 2: Add the constants to `ProductionSchedule`**

In `backend/app/Models/ProductionSchedule.php`, add these class constants at the top of the class body (before `$fillable`):

```php
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
```

- [ ] **Step 3: Write the failing model/factory test**

Create `backend/tests/Feature/ProductionScheduleModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_production_schedule(): void
    {
        $schedule = ProductionSchedule::factory()->create();

        $this->assertDatabaseHas('production_schedules', ['id' => $schedule->id]);
        $this->assertSame(ProductionSchedule::STATUS_SCHEDULED, $schedule->production_status);
        $this->assertNull($schedule->start_time);
        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $schedule->order->order_status);
    }

    public function test_belongs_to_order(): void
    {
        $schedule = ProductionSchedule::factory()->create();

        $this->assertTrue($schedule->order->is(Order::find($schedule->order_id)));
    }
}
```

- [ ] **Step 4: Create the factory**

Create `backend/database/factories/ProductionScheduleFactory.php`:

```php
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
```

Note: there is no `OrderFactory`; the closure creates an `Order` directly (per the existing `RecipeFactory` pattern) and `Customer::factory()` is resolved inside the closure.

- [ ] **Step 5: Run the model test**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleModelTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Models/Order.php backend/app/Models/ProductionSchedule.php backend/database/factories/ProductionScheduleFactory.php backend/tests/Feature/ProductionScheduleModelTest.php
git commit -m "feat: add production schedule factory and model tests"
```

---

### Task 2: ProductionScheduleRequest and request tests

**Files:**
- Create: `backend/app/Http/Requests/ProductionScheduleRequest.php`
- Create: `backend/tests/Feature/ProductionScheduleRequestTest.php`

**Interfaces:**
- Produces: `ProductionScheduleRequest` with `rules()` returning `['order_id' => required|sometimes + exists:orders,id, 'start_time' => nullable|sometimes + date, 'end_time' => nullable|sometimes + date]`. Used by the controller in Task 5.
- Consumes: nothing beyond Laravel `FormRequest`.

- [ ] **Step 1: Write the failing request test**

Create `backend/tests/Feature/ProductionScheduleRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Requests\ProductionScheduleRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductionScheduleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new ProductionScheduleRequest)->rules();
    }

    private function createOrder(): Order
    {
        return Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
    }

    public function test_valid_payload_passes(): void
    {
        $order = $this->createOrder();

        $validator = Validator::make([
            'order_id' => $order->id,
            'start_time' => now()->format('Y-m-d H:i:s'),
            'end_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_order_id_is_required(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_order_id_must_exist(): void
    {
        $validator = Validator::make(['order_id' => 999999], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_times_are_nullable_on_store(): void
    {
        $order = $this->createOrder();

        $validator = Validator::make(['order_id' => $order->id], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = ProductionScheduleRequest::create('/api/production-schedules/1', 'PUT', [
            'start_time' => now()->format('Y-m-d H:i:s'),
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleRequestTest`
Expected: FAIL with `Class "App\Http\Requests\ProductionScheduleRequest" not found`.

- [ ] **Step 3: Create the request**

Create `backend/app/Http/Requests/ProductionScheduleRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'order_id' => ['required', 'exists:orders,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['order_id'] = ['sometimes', 'exists:orders,id'];
            $rules['start_time'] = ['sometimes', 'date'];
            $rules['end_time'] = ['sometimes', 'date'];
        }

        return $rules;
    }
}
```

- [ ] **Step 4: Run the request test**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleRequestTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/ProductionScheduleRequest.php backend/tests/Feature/ProductionScheduleRequestTest.php
git commit -m "feat: add production schedule form request validation"
```

---

### Task 3: ProductionScheduleResource and resource test

**Files:**
- Create: `backend/app/Http/Resources/ProductionScheduleResource.php`
- Create: `backend/tests/Feature/ProductionScheduleResourceTest.php`

**Interfaces:**
- Produces: `ProductionScheduleResource` (a `JsonResource`) whose `toArray` yields `['id', 'order_id', 'start_time', 'end_time', 'production_status', 'order' => ['id', 'order_number', 'pickup_datetime', 'order_status'], 'created_at', 'updated_at']`. Requires the `order` relation loaded. Consumed by the controller in Task 5.
- Consumes: `ProductionSchedule` and its `order()` relation.

- [ ] **Step 1: Write the failing resource test**

Create `backend/tests/Feature/ProductionScheduleResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\ProductionScheduleResource;
use App\Models\ProductionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_schedule_resource_has_expected_shape(): void
    {
        $schedule = ProductionSchedule::factory()->create([
            'start_time' => '2026-08-02 09:00:00',
            'end_time' => null,
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ]);
        $schedule->load('order');

        $resource = (new ProductionScheduleResource($schedule))->resolve();

        $this->assertEquals($schedule->id, $resource['id']);
        $this->assertEquals($schedule->order_id, $resource['order_id']);
        $this->assertEquals('2026-08-02 09:00:00', $resource['start_time']);
        $this->assertNull($resource['end_time']);
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $resource['production_status']);
        $this->assertEquals($schedule->order->order_number, $resource['order']['order_number']);
        $this->assertEquals($schedule->order->order_status, $resource['order']['order_status']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleResourceTest`
Expected: FAIL with `Class "App\Http\Resources\ProductionScheduleResource" not found`.

- [ ] **Step 3: Create the resource**

Create `backend/app/Http/Resources/ProductionScheduleResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'production_status' => $this->production_status,
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'pickup_datetime' => $this->order->pickup_datetime,
                'order_status' => $this->order->order_status,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Run the resource test**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleResourceTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/ProductionScheduleResource.php backend/tests/Feature/ProductionScheduleResourceTest.php
git commit -m "feat: add production schedule api resource"
```

---

### Task 4: ProductionScheduleService and service tests

**Files:**
- Create: `backend/app/Services/ProductionScheduleService.php`
- Create: `backend/tests/Feature/ProductionScheduleServiceTest.php`

**Interfaces:**
- Consumes: `Order::ORDER_STATUS_CONFIRMED` / `ORDER_STATUS_FINISHED`, `ProductionSchedule::STATUS_SCHEDULED` / `STATUS_IN_PROGRESS` / `STATUS_FINISHED` (Task 1); `RawMaterialFactory`, `CustomerFactory`; existing `Order`, `OrderItem`, `Product`, `Recipe`, `RecipeDetail`, `RawMaterial` models.
- Produces: `ProductionScheduleService` with methods:
  - `getAll(): Collection` — latest first, `order` eager-loaded.
  - `getById(int $id): ProductionSchedule` — `findOrFail`, `order` eager-loaded.
  - `create(array $data): ProductionSchedule` — returns the new schedule with `order` loaded.
  - `update(int $id, array $data): ProductionSchedule` — returns the updated schedule with `order` loaded.
  - `delete(int $id): void`.
  - `start(int $id): ProductionSchedule` — returns the schedule with `order` loaded.
  - `finish(int $id): ProductionSchedule` — returns the schedule with `order` loaded.

**Design notes:** The spec (Section 6) lists "start before pickup, end after start, confirmed-order, BR-009" as service guards generally, so `create()` applies the same time-ordering guards as `update()` when times are provided. Guard failures throw `ValidationException` (422). `lockForUpdate()` is a no-op under SQLite (documented limitation). Availability shortages produce one human-readable message per short material under the `stock` key: `Insufficient stock for {name} (required {required}, available {available}, short by {short_by}).`

- [ ] **Step 1: Write the failing service tests**

Create `backend/tests/Feature/ProductionScheduleServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionSchedule;
use App\Models\RawMaterial;
use App\Services\ProductionScheduleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductionScheduleService;
    }

    private function createOrderWithRecipe(int $itemQuantity = 1, float $materialStock = 100, float $recipeQuantity = 1): array
    {
        $material = RawMaterial::factory()->create([
            'name' => 'Flour',
            'stock_quantity' => $materialStock,
            'unit' => 'gram',
        ]);

        $product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $recipe = $product->recipes()->create(['recipe_name' => 'Cake A']);
        $recipe->recipeDetails()->create([
            'raw_material_id' => $material->id,
            'quantity' => $recipeQuantity,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $itemQuantity,
            'customization_note' => null,
            'subtotal' => 0,
        ]);

        return compact('order', 'material');
    }

    public function test_get_all_returns_all_schedules(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        ['order' => $orderB] = $this->createOrderWithRecipe();
        $this->service->create(['order_id' => $orderA->id]);
        $this->service->create(['order_id' => $orderB->id]);

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_creates_schedule_as_scheduled(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();

        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->assertDatabaseHas('production_schedules', [
            'id' => $schedule->id,
            'order_id' => $order->id,
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ]);
        $this->assertNull($schedule->start_time);
    }

    public function test_create_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id]);
    }

    public function test_create_rejects_second_active_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id]);
    }

    public function test_create_throws_when_order_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create(['order_id' => 999999]);
    }

    public function test_create_validates_start_before_pickup(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $afterPickup = Carbon::parse($order->pickup_datetime)->addHour()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id, 'start_time' => $afterPickup]);
    }

    public function test_update_changes_times(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $start = now()->format('Y-m-d H:i:s');
        $end = now()->addHours(2)->format('Y-m-d H:i:s');

        $updated = $this->service->update($schedule->id, [
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $this->assertEquals($start, $updated->start_time);
        $this->assertEquals($end, $updated->end_time);
    }

    public function test_update_rejects_order_id_change_after_start(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $other = $this->createOrderWithRecipe()['order'];

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['order_id' => $other->id]);
    }

    public function test_update_rejects_order_id_change_to_occupied_order(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        ['order' => $orderB] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $orderA->id]);
        $this->service->create(['order_id' => $orderB->id]);

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['order_id' => $orderB->id]);
    }

    public function test_update_rejects_start_at_or_after_pickup(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $afterPickup = Carbon::parse($order->pickup_datetime)->addHour()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['start_time' => $afterPickup]);
    }

    public function test_update_rejects_end_before_start(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $start = now()->addHours(2)->format('Y-m-d H:i:s');
        $end = now()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['start_time' => $start, 'end_time' => $end]);
    }

    public function test_delete_removes_scheduled_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->service->delete($schedule->id);

        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule->id]);
    }

    public function test_delete_rejects_in_progress_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($schedule->id);
    }

    public function test_delete_rejects_finished_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);
        $this->service->finish($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($schedule->id);
    }

    public function test_start_marks_schedule_in_progress(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);

        $started = $this->service->start($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_IN_PROGRESS, $started->production_status);
        $this->assertNotNull($started->start_time);
    }

    public function test_start_rejects_when_not_scheduled(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_zero_item_order(): void
    {
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_product_without_recipe(): void
    {
        $product = Product::create([
            'name' => 'Plain Cake',
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'customization_note' => null,
            'subtotal' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_shortage_and_rolls_back(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 10, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);

        try {
            $this->service->start($schedule->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('production_schedules', [
                'id' => $schedule->id,
                'production_status' => ProductionSchedule::STATUS_SCHEDULED,
            ]);
            $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
        }
    }

    public function test_start_uses_latest_recipe(): void
    {
        $material = RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 30, 'unit' => 'gram']);
        $product = Product::create(['name' => 'Chocolate Cake', 'base_price' => 50000, 'is_active' => true]);

        $recipeA = $product->recipes()->create(['recipe_name' => 'Cake A']);
        $recipeA->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 1]);

        $recipeB = $product->recipes()->create(['recipe_name' => 'Cake B']);
        $recipeB->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 50]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'customization_note' => null,
            'subtotal' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_aggregates_item_quantities(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe(2, 60, 30);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $started = $this->service->start($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_IN_PROGRESS, $started->production_status);
    }

    public function test_finish_consumes_stock_and_marks_finished(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $finished = $this->service->finish($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_FINISHED, $finished->production_status);
        $this->assertNotNull($finished->end_time);
        $this->assertSame(Order::ORDER_STATUS_FINISHED, $order->refresh()->order_status);
        $this->assertEquals(50, (float) $material->refresh()->stock_quantity);
    }

    public function test_finish_rejects_when_not_in_progress(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);
        $this->service->finish($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedule->id);
    }

    public function test_finish_rejects_when_order_not_confirmed(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedule->id);
    }

    public function test_finish_rejects_shortage_and_rolls_back(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $material->update(['stock_quantity' => 10]);

        try {
            $this->service->finish($schedule->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
            $this->assertDatabaseHas('production_schedules', [
                'id' => $schedule->id,
                'production_status' => ProductionSchedule::STATUS_IN_PROGRESS,
            ]);
            $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $order->refresh()->order_status);
        }
    }

    public function test_finish_rounds_stock_to_two_decimals(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(3, 10, 0.33);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->service->finish($schedule->id);

        $this->assertEquals(9.01, (float) $material->refresh()->stock_quantity);
    }

    public function test_finish_serializes_orders_on_same_material(): void
    {
        $material = RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'gram']);

        $schedules = [];
        foreach (['Cake A', 'Cake B'] as $name) {
            $product = Product::create(['name' => $name, 'base_price' => 50000, 'is_active' => true]);
            $recipe = $product->recipes()->create(['recipe_name' => $name . ' recipe']);
            $recipe->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 60]);

            $order = Order::create([
                'customer_id' => Customer::factory()->create()->id,
                'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
                'pickup_datetime' => now()->addDay(),
                'order_status' => Order::ORDER_STATUS_CONFIRMED,
                'total_price' => 0,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'customization_note' => null,
                'subtotal' => 0,
            ]);

            $schedules[] = $this->service->create(['order_id' => $order->id]);
        }

        $this->service->start($schedules[0]->id);
        $this->service->start($schedules[1]->id);

        $this->service->finish($schedules[0]->id);
        $this->assertEquals(40, (float) $material->refresh()->stock_quantity);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedules[1]->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleServiceTest`
Expected: FAIL with `Class "App\Services\ProductionScheduleService" not found`.

- [ ] **Step 3: Create the service**

Create `backend/app/Services/ProductionScheduleService.php`:

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductionSchedule;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionScheduleService
{
    public function getAll(): Collection
    {
        return ProductionSchedule::with('order')->latest()->get();
    }

    public function getById(int $id): ProductionSchedule
    {
        return ProductionSchedule::with('order')->findOrFail($id);
    }

    public function create(array $data): ProductionSchedule
    {
        return DB::transaction(function () use ($data) {
            $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);
            $this->assertNoActiveSchedule($order);

            if (isset($data['start_time'])) {
                $this->assertStartBeforePickup($data['start_time'], (int) $order->id);
            }
            if (isset($data['start_time'], $data['end_time'])) {
                $this->assertEndAfterStart($data['start_time'], $data['end_time']);
            }

            $schedule = $order->productionSchedule()->create([
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'production_status' => ProductionSchedule::STATUS_SCHEDULED,
            ]);

            return $schedule->load('order');
        });
    }

    public function update(int $id, array $data): ProductionSchedule
    {
        return DB::transaction(function () use ($id, $data) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            $newOrderId = (int) $schedule->order_id;
            $newStartTime = $schedule->start_time;
            $newEndTime = $schedule->end_time;

            if (array_key_exists('order_id', $data)) {
                if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                    throw ValidationException::withMessages([
                        'order_id' => ['A started production schedule cannot be moved to another order.'],
                    ]);
                }

                $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
                $this->assertOrderConfirmed($order);
                $this->assertNoActiveSchedule($order, $schedule->id);

                $newOrderId = (int) $data['order_id'];
            }

            if (array_key_exists('start_time', $data)) {
                $newStartTime = $data['start_time'];
            }
            if (array_key_exists('end_time', $data)) {
                $newEndTime = $data['end_time'];
            }

            if ($newStartTime !== null) {
                $this->assertStartBeforePickup($newStartTime, $newOrderId);
            }
            if ($newStartTime !== null && $newEndTime !== null) {
                $this->assertEndAfterStart($newStartTime, $newEndTime);
            }

            $schedule->update([
                'order_id' => $newOrderId,
                'start_time' => $newStartTime,
                'end_time' => $newEndTime,
            ]);

            return $schedule->refresh()->load('order');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only a scheduled production can be deleted.'],
                ]);
            }

            $schedule->delete();
        });
    }

    public function start(int $id): ProductionSchedule
    {
        return DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only a scheduled production can be started.'],
                ]);
            }

            $order = Order::whereKey($schedule->order_id)->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);

            $order->load('items.product.recipes.recipeDetails.rawMaterial');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['The order has no items to produce.'],
                ]);
            }

            [$required, $missingRecipes] = $this->buildRequirements($order);

            if ($missingRecipes !== []) {
                throw ValidationException::withMessages([
                    'order_id' => ['Products have no recipe: ' . implode(', ', $missingRecipes)],
                ]);
            }

            $materials = $this->lockMaterials(array_keys($required));
            $this->assertAvailability($required, $materials);

            $schedule->update([
                'start_time' => $schedule->start_time ?? now(),
                'production_status' => ProductionSchedule::STATUS_IN_PROGRESS,
            ]);

            return $schedule->refresh()->load('order');
        });
    }

    public function finish(int $id): ProductionSchedule
    {
        return DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only an in-progress production can be finished.'],
                ]);
            }

            $order = Order::whereKey($schedule->order_id)->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);

            $order->load('items.product.recipes.recipeDetails.rawMaterial');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['The order has no items to produce.'],
                ]);
            }

            [$required, $missingRecipes] = $this->buildRequirements($order);

            if ($missingRecipes !== []) {
                throw ValidationException::withMessages([
                    'order_id' => ['Products have no recipe: ' . implode(', ', $missingRecipes)],
                ]);
            }

            $materials = $this->lockMaterials(array_keys($required));
            $this->assertAvailability($required, $materials);

            foreach ($materials as $material) {
                $needed = $required[(int) $material->id];
                $material->update([
                    'stock_quantity' => round((float) $material->stock_quantity - $needed, 2),
                ]);
            }

            $schedule->update([
                'end_time' => $schedule->end_time ?? now(),
                'production_status' => ProductionSchedule::STATUS_FINISHED,
            ]);
            $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

            return $schedule->refresh()->load('order');
        });
    }

    private function buildRequirements(Order $order): array
    {
        $required = [];
        $missingRecipes = [];

        foreach ($order->items as $item) {
            $recipe = $item->product->recipes->sortByDesc('id')->first();

            if ($recipe === null) {
                $missingRecipes[] = $item->product->name;
                continue;
            }

            foreach ($recipe->recipeDetails as $detail) {
                $materialId = (int) $detail->raw_material_id;
                $required[$materialId] = ($required[$materialId] ?? 0)
                    + ((float) $item->quantity * (float) $detail->quantity);
            }
        }

        foreach ($required as $materialId => $total) {
            $required[$materialId] = round($total, 2);
        }

        return [$required, $missingRecipes];
    }

    private function lockMaterials(array $materialIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $materialIds)));
        sort($ids);

        return RawMaterial::whereKey($ids)->lockForUpdate()->get();
    }

    private function assertAvailability(array $required, Collection $materials): void
    {
        $byId = $materials->keyBy('id');
        $shortages = [];

        foreach ($required as $materialId => $needed) {
            $material = $byId->get((int) $materialId);
            $available = (float) ($material->stock_quantity ?? 0);

            if ($needed > $available) {
                $shortages[] = sprintf(
                    'Insufficient stock for %s (required %.2f, available %.2f, short by %.2f).',
                    $material->name,
                    $needed,
                    $available,
                    $needed - $available
                );
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages(['stock' => $shortages]);
        }
    }

    private function assertOrderConfirmed(Order $order): void
    {
        if ($order->order_status !== Order::ORDER_STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'order_id' => ['The order must be confirmed.'],
            ]);
        }
    }

    private function assertNoActiveSchedule(Order $order, ?int $excludeScheduleId = null): void
    {
        $exists = $order->productionSchedule()
            ->where('production_status', '!=', ProductionSchedule::STATUS_FINISHED)
            ->when($excludeScheduleId !== null, fn ($query) => $query->where('id', '!=', $excludeScheduleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'order_id' => ['The order already has an active production schedule.'],
            ]);
        }
    }

    private function assertStartBeforePickup(mixed $startTime, int $orderId): void
    {
        $pickup = Order::whereKey($orderId)->value('pickup_datetime');

        if ($pickup === null || strtotime($startTime) >= strtotime($pickup)) {
            throw ValidationException::withMessages([
                'start_time' => ['Production must start before the order pickup time.'],
            ]);
        }
    }

    private function assertEndAfterStart(mixed $startTime, mixed $endTime): void
    {
        if (strtotime($endTime) <= strtotime($startTime)) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }
    }
}
```

- [ ] **Step 4: Run the service tests**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleServiceTest`
Expected: PASS (all service tests, ~28 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/ProductionScheduleService.php backend/tests/Feature/ProductionScheduleServiceTest.php
git commit -m "feat: add production schedule service with stock consumption"
```

---

### Task 5: ProductionScheduleController, routes, and endpoint tests

**Files:**
- Create: `backend/app/Http/Controllers/ProductionScheduleController.php`
- Create: `backend/tests/Feature/ProductionScheduleTest.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `ProductionScheduleService` (Task 4), `ProductionScheduleRequest` (Task 2), `ProductionScheduleResource` (Task 3), `Order::ORDER_STATUS_FINISHED` and `ProductionSchedule::STATUS_*` (Task 1, used in tests).
- Produces: `ProductionScheduleController` with `index`, `store`, `show`, `update`, `destroy`, `start`, `finish` actions; routes `GET/POST /api/production-schedules`, `GET/PUT/DELETE /api/production-schedules/{production_schedule}`, `PATCH /api/production-schedules/{production_schedule}/start`, `PATCH /api/production-schedules/{production_schedule}/finish`.

- [ ] **Step 1: Write the failing endpoint tests**

Create `backend/tests/Feature/ProductionScheduleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithRecipe(int $itemQuantity = 1, float $materialStock = 100, float $recipeQuantity = 1): array
    {
        $material = RawMaterial::factory()->create([
            'name' => 'Flour',
            'stock_quantity' => $materialStock,
            'unit' => 'gram',
        ]);

        $product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $recipe = $product->recipes()->create(['recipe_name' => 'Cake A']);
        $recipe->recipeDetails()->create([
            'raw_material_id' => $material->id,
            'quantity' => $recipeQuantity,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $itemQuantity,
            'customization_note' => null,
            'subtotal' => 0,
        ]);

        return compact('order', 'material');
    }

    public function test_index_returns_schedule_list(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        $scheduleA = $this->postJson('/api/production-schedules', ['order_id' => $orderA->id])->json('data');

        ['order' => $orderB] = $this->createOrderWithRecipe();
        $scheduleB = $this->postJson('/api/production-schedules', ['order_id' => $orderB->id])->json('data');

        $response = $this->getJson('/api/production-schedules');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $scheduleB['id'])
            ->assertJsonPath('data.0.order.order_number', $orderB->order_number);
    }

    public function test_store_creates_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.production_status', 'scheduled')
            ->assertJsonPath('data.order.order_status', 'confirmed');
        $this->assertDatabaseHas('production_schedules', ['order_id' => $order->id, 'production_status' => 'scheduled']);
    }

    public function test_store_rejects_second_active_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->getJson("/api/production-schedules/{$schedule['id']}");

        $response->assertOk()->assertJsonPath('data.id', $schedule['id']);
    }

    public function test_update_updates_times(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $start = now()->format('Y-m-d H:i:s');
        $end = now()->addHours(2)->format('Y-m-d H:i:s');

        $response = $this->putJson("/api/production-schedules/{$schedule['id']}", [
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.start_time', $start)
            ->assertJsonPath('data.end_time', $end);
    }

    public function test_destroy_removes_scheduled_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->deleteJson("/api/production-schedules/{$schedule['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule['id']]);
    }

    public function test_destroy_rejects_in_progress_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response = $this->deleteJson("/api/production-schedules/{$schedule['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('production_schedules', ['id' => $schedule['id']]);
    }

    public function test_start_starts_production(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.production_status', 'in_progress');
    }

    public function test_start_rejects_shortage(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe(1, 10, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_finish_consumes_stock_and_marks_order_finished(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertOk()
            ->assertJsonPath('data.production_status', 'finished')
            ->assertJsonPath('data.order.order_status', 'finished');
        $this->assertEquals(50, (float) $material->refresh()->stock_quantity);
        $this->assertSame('finished', $order->refresh()->order_status);
    }

    public function test_finish_rejects_shortage(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $material->update(['stock_quantity' => 10]);

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
        $this->assertSame('in_progress', $order->productionSchedule->refresh()->production_status);
    }

    public function test_finish_rejects_double_finish(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");
        $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_schedule_returns_404(): void
    {
        $this->getJson('/api/production-schedules/999999')->assertNotFound();
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/production-schedules', ['order_id' => 999999]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_cascade_order_deletion_removes_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $order->delete();

        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule['id']]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleTest`
Expected: FAIL (route/controller missing — `production-schedules` returns 404).

- [ ] **Step 3: Create the controller**

Create `backend/app/Http/Controllers/ProductionScheduleController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionScheduleRequest;
use App\Http\Resources\ProductionScheduleResource;
use App\Services\ProductionScheduleService;
use Illuminate\Http\JsonResponse;

class ProductionScheduleController extends Controller
{
    public function __construct(private readonly ProductionScheduleService $productionScheduleService) {}

    public function index(): JsonResponse
    {
        $schedules = $this->productionScheduleService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Production schedules retrieved successfully',
            'data' => ProductionScheduleResource::collection($schedules),
        ]);
    }

    public function store(ProductionScheduleRequest $request): JsonResponse
    {
        $schedule = $this->productionScheduleService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Production schedule created successfully',
            'data' => new ProductionScheduleResource($schedule),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Production schedule retrieved successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function update(ProductionScheduleRequest $request, int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Production schedule updated successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productionScheduleService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Production schedule deleted successfully',
            'data' => null,
        ]);
    }

    public function start(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->start($id);

        return response()->json([
            'success' => true,
            'message' => 'Production started successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }

    public function finish(int $id): JsonResponse
    {
        $schedule = $this->productionScheduleService->finish($id);

        return response()->json([
            'success' => true,
            'message' => 'Production finished successfully',
            'data' => new ProductionScheduleResource($schedule),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `backend/routes/api.php`, add the import and the routes. The file currently imports the Inventory/Product/Recipe controllers and registers resources. Add after the existing `use` statements:

```php
use App\Http\Controllers\ProductionScheduleController;
```

And at the end of the file:

```php
Route::apiResource('production-schedules', ProductionScheduleController::class);
Route::patch('production-schedules/{production_schedule}/start', [ProductionScheduleController::class, 'start']);
Route::patch('production-schedules/{production_schedule}/finish', [ProductionScheduleController::class, 'finish']);
```

Note: the controller methods type-hint `int $id` (scalar), so no implicit route-model binding occurs and the route parameter name is arbitrary — this matches the existing `InventoryPurchaseController` pattern.

- [ ] **Step 5: Run the endpoint tests**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionScheduleTest`
Expected: PASS (all endpoint tests, ~17 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/ProductionScheduleController.php backend/tests/Feature/ProductionScheduleTest.php backend/routes/api.php
git commit -m "feat: add production schedule controller and routes"
```

---

### Task 6: Full verification and formatting

**Files:** none new — verification of the whole module plus Pint.

**Interfaces:** none — closes out the module.

- [ ] **Step 1: Run the module tests**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=ProductionSchedule`
Expected: PASS (model 2, request 5, resource 1, service ~28, endpoint ~17 — all green).

- [ ] **Step 2: Run the full suite**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" artisan test`
Expected: PASS — prior baseline was 82/82; the Production module adds the above; nothing regresses (Recipe, Raw Material, Inventory, Product tests all still green).

- [ ] **Step 3: Run Pint check**

Run: `& "C:\Users\Indra\Tools\PHP\php.exe" vendor/bin/pint --test`
Expected: PASS. If it reports issues only in files you created, run `& "C:\Users\Indra\Tools\PHP\php.exe" vendor/bin/pint` to auto-fix, re-run the full suite, and re-check. If it reports issues in unrelated pre-existing files, run the full `vendor/bin/pint` (matches the Inventory module's precedent), re-run the suite, and commit the formatting.

- [ ] **Step 4: Commit any formatting changes**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

(If the Pint check passed with no changes, skip this commit.)

- [ ] **Step 5: Final self-check**

Verify with `git status` that the only changes on the branch are: the two model constant additions, `routes/api.php`, and the nine new Production module files (factory, request, resource, service, controller, four test files). Confirm `git log --oneline` shows the Task 1-6 commits in order.
