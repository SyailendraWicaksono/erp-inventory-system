# Inventory Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Inventory module — full CRUD for inventory purchases with automatic `raw_materials.stock_quantity` synchronization (transactional, guarded against negative stock), plus a read-only stock availability endpoint.

**Architecture:** Laravel API. Flat REST routes under `/api/inventory-purchases` and `/api/inventory/availability`. Thin controller → FormRequest → Service (with DB transactions + row locks) → Eloquent model → API Resource, following the existing Product / Raw Material module pattern.

**Tech Stack:** Laravel, PHP 8.3, PostgreSQL (prod) / SQLite in-memory (tests), PHPUnit, Eloquent ORM.

**Spec:** `docs/superpowers/specs/2026-08-02-inventory-module-design.md`

## Global Constraints

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use DB transactions for all write operations.
- Acquire `lockForUpdate()` on every affected `raw_materials` row before any stock read-modify-write (concurrency safety).
- Normalize every computed stock value with `round(..., 2)` **before** writing; the negative guard checks the rounded value.
- Use Form Requests, API Resources, Service classes, dependency injection.
- Follow the existing Product / Raw Material module pattern (`RawMaterialController` / `RawMaterialRequest` / `RawMaterialService` / `RawMaterialResource`).
- All responses use the `{ success, message, data }` envelope.
- Validation errors keep the standard Laravel shape (`{ message, errors }`) with 422; the envelope is NOT asserted on 422.
- Tests run against in-memory SQLite (`phpunit.xml`); use `RefreshDatabase`.
- PSR-12, snake_case DB columns, singular model names.
- No new dependencies.
- Commit after each completed task.
- The `InventoryPurchase` and `RawMaterial` models already exist — do NOT recreate them. `InventoryPurchase` has `$fillable = [raw_material_id, quantity, purchase_date]` and a `rawMaterial()` belongsTo relation. `RawMaterial` has `stock_quantity` and an `inventoryPurchases()` hasMany relation.
- No `InventoryPurchaseFactory` exists — Task 1 creates it.

---

### Task 1: InventoryPurchaseFactory + model test

**Files:**
- Create: `backend/database/factories/InventoryPurchaseFactory.php`
- Test: `backend/tests/Feature/InventoryPurchaseModelTest.php`

**Interfaces:**
- Consumes: existing `InventoryPurchase` and `RawMaterial` models.
- Produces: `InventoryPurchaseFactory` — `raw_material_id => RawMaterial::factory()`, `quantity => 50`, `purchase_date => now()`. Used by later tasks' tests.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryPurchaseModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPurchaseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_inventory_purchase(): void
    {
        $purchase = InventoryPurchase::factory()->create();

        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
        $this->assertSame(50, (int) $purchase->quantity);
        $this->assertNotNull($purchase->purchase_date);
    }

    public function test_belongs_to_raw_material(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $purchase = InventoryPurchase::factory()->create(['raw_material_id' => $rawMaterial->id]);

        $this->assertTrue($purchase->rawMaterial->is($rawMaterial));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryPurchaseModelTest` (workdir `backend`)
Expected: FAIL — `InventoryPurchase::factory()` is undefined (no factory exists).

- [ ] **Step 3: Implement the factory**

Create `backend/database/factories/InventoryPurchaseFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raw_material_id' => RawMaterial::factory(),
            'quantity' => 50,
            'purchase_date' => now(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryPurchaseModelTest` (workdir `backend`)
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/database/factories/InventoryPurchaseFactory.php backend/tests/Feature/InventoryPurchaseModelTest.php
git commit -m "test: add inventory purchase factory and model tests"
```

---

### Task 2: InventoryPurchaseRequest validation

**Files:**
- Create: `backend/app/Http/Requests/InventoryPurchaseRequest.php`
- Test: `backend/tests/Feature/InventoryPurchaseRequestTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `InventoryPurchaseRequest` extending `Illuminate\Foundation\Http\FormRequest` with `authorize(): bool` and `rules(): array`. Used by the controller in Task 5.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryPurchaseRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Requests\InventoryPurchaseRequest;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InventoryPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new InventoryPurchaseRequest())->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50.00,
            'purchase_date' => '2026-08-01 09:00:00',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_raw_material_id_is_required(): void
    {
        $validator = Validator::make([
            'quantity' => 50,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raw_material_id', $validator->errors()->toArray());
    }

    public function test_raw_material_id_must_exist(): void
    {
        $validator = Validator::make([
            'raw_material_id' => 999999,
            'quantity' => 50,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raw_material_id', $validator->errors()->toArray());
    }

    public function test_quantity_is_required(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }

    public function test_quantity_must_be_positive(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 0,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = InventoryPurchaseRequest::create('/api/inventory-purchases/1', 'PUT', [
            'quantity' => 75,
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_put_rejects_null_purchase_date(): void
    {
        $request = InventoryPurchaseRequest::create('/api/inventory-purchases/1', 'PUT', [
            'purchase_date' => null,
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('purchase_date', $validator->errors()->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryPurchaseRequestTest` (workdir `backend`)
Expected: FAIL — class `InventoryPurchaseRequest` not found.

- [ ] **Step 3: Implement the request**

Create `backend/app/Http/Requests/InventoryPurchaseRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'raw_material_id' => ['required', 'exists:raw_materials,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'purchase_date' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['raw_material_id'] = ['sometimes', 'exists:raw_materials,id'];
            $rules['quantity'] = ['sometimes', 'numeric', 'min:0.01', 'max:9999999999.99'];
            $rules['purchase_date'] = ['sometimes', 'date'];
        }

        return $rules;
    }
}
```

Note: on PUT, `['sometimes', 'date']` rejects a present `null` (the `date` rule fails on null), so `purchase_date` can never be written as null to the non-nullable column.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryPurchaseRequestTest` (workdir `backend`)
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/InventoryPurchaseRequest.php backend/tests/Feature/InventoryPurchaseRequestTest.php
git commit -m "feat: add inventory purchase form request validation"
```

---

### Task 3: API resources

**Files:**
- Create: `backend/app/Http/Resources/InventoryPurchaseResource.php`
- Create: `backend/app/Http/Resources/InventoryAvailabilityResource.php`
- Test: `backend/tests/Feature/InventoryPurchaseResourceTest.php`

**Interfaces:**
- Consumes: `InventoryPurchase` model from Task 1 (via factory).
- Produces:
  - `InventoryPurchaseResource` (used by the controller in Task 5) — requires the `rawMaterial` relation to be loaded.
  - `InventoryAvailabilityResource` (used by the controller in Task 6) — expects a `status` attribute set on the raw material.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryPurchaseResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\InventoryPurchaseResource;
use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPurchaseResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_purchase_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram']);
        $purchase = InventoryPurchase::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 500,
            'purchase_date' => '2026-08-01 09:00:00',
        ]);
        $purchase->load('rawMaterial');

        $resource = (new InventoryPurchaseResource($purchase))->resolve();

        $this->assertEquals($purchase->id, $resource['id']);
        $this->assertEquals($rawMaterial->id, $resource['raw_material_id']);
        $this->assertEquals(500, $resource['quantity']);
        $this->assertEquals('2026-08-01 09:00:00', $resource['purchase_date']);
        $this->assertEquals(['id' => $rawMaterial->id, 'name' => 'Flour', 'unit' => 'gram'], $resource['raw_material']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryPurchaseResourceTest` (workdir `backend`)
Expected: FAIL — class `InventoryPurchaseResource` not found.

- [ ] **Step 3: Implement the resources**

Create `backend/app/Http/Resources/InventoryPurchaseResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'raw_material_id' => $this->raw_material_id,
            'quantity' => $this->quantity,
            'purchase_date' => $this->purchase_date,
            'raw_material' => [
                'id' => $this->rawMaterial->id,
                'name' => $this->rawMaterial->name,
                'unit' => $this->rawMaterial->unit,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

Create `backend/app/Http/Resources/InventoryAvailabilityResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->status,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryPurchaseResourceTest` (workdir `backend`)
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/InventoryPurchaseResource.php backend/app/Http/Resources/InventoryAvailabilityResource.php backend/tests/Feature/InventoryPurchaseResourceTest.php
git commit -m "feat: add inventory api resources"
```

---

### Task 4: InventoryPurchaseService with transactional stock sync

**Files:**
- Create: `backend/app/Services/InventoryPurchaseService.php`
- Test: `backend/tests/Feature/InventoryPurchaseServiceTest.php`

**Interfaces:**
- Consumes: `InventoryPurchase` and `RawMaterial` models; `InventoryPurchaseFactory` from Task 1.
- Produces: `InventoryPurchaseService` with methods:
  - `getAll(): Collection` (purchases, latest first, `rawMaterial` eager-loaded)
  - `getById(int $id): InventoryPurchase` (throws `ModelNotFoundException` when missing; `rawMaterial` eager-loaded)
  - `create(array $data): InventoryPurchase` (transaction; stock += quantity; `purchase_date` defaults to `now()`)
  - `update(int $id, array $data): InventoryPurchase` (transaction; stock ± difference; handles `raw_material_id` changes)
  - `delete(int $id): void` (transaction; stock −= quantity)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryPurchaseServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Services\InventoryPurchaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryPurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryPurchaseService();
    }

    public function test_get_all_returns_all_purchases(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $this->service->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);
        $this->service->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 30]);

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_persists_purchase_and_increments_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);

        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_create_defaults_purchase_date_to_now(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->assertNotNull($purchase->purchase_date);
    }

    public function test_update_adjusts_stock_by_difference(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['quantity' => 80]);

        $this->assertEquals(130, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_update_changes_raw_material_and_moves_stock(): void
    {
        $old = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $new = RawMaterial::factory()->create(['stock_quantity' => 200]);
        $purchase = $this->service->create([
            'raw_material_id' => $old->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['raw_material_id' => $new->id]);

        $this->assertEquals(100, (float) $old->refresh()->stock_quantity);
        $this->assertEquals(250, (float) $new->refresh()->stock_quantity);
    }

    public function test_update_with_same_raw_material_does_not_double_adjust(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->update($purchase->id, ['raw_material_id' => $rawMaterial->id]);

        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_delete_decrements_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $this->service->delete($purchase->id);

        $this->assertDatabaseMissing('inventory_purchases', ['id' => $purchase->id]);
        $this->assertEquals(50, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_update_guard_rolls_back_when_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 30]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $rawMaterial->update(['stock_quantity' => 10]);

        try {
            $this->service->update($purchase->id, ['quantity' => 60]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id, 'quantity' => 50]);
            $this->assertEquals(10, (float) $rawMaterial->refresh()->stock_quantity);
        }
    }

    public function test_delete_guard_rolls_back_when_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 30]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $rawMaterial->update(['stock_quantity' => 10]);

        try {
            $this->service->delete($purchase->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
            $this->assertEquals(10, (float) $rawMaterial->refresh()->stock_quantity);
        }
    }

    public function test_stock_values_are_rounded_to_two_decimals(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 0]);
        $purchase = $this->service->create([
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 0.1,
        ]);
        $this->service->update($purchase->id, ['quantity' => 0.2]);
        $this->service->update($purchase->id, ['quantity' => 0.3]);

        $this->assertEquals(0.3, (float) $rawMaterial->refresh()->stock_quantity);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryPurchaseServiceTest` (workdir `backend`)
Expected: FAIL — class `InventoryPurchaseService` not found.

- [ ] **Step 3: Implement the service**

Create `backend/app/Services/InventoryPurchaseService.php`:

```php
<?php

namespace App\Services;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPurchaseService
{
    public function getAll(): Collection
    {
        return InventoryPurchase::with('rawMaterial')->latest()->get();
    }

    public function getById(int $id): InventoryPurchase
    {
        return InventoryPurchase::with('rawMaterial')->findOrFail($id);
    }

    public function create(array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($data) {
            $data['purchase_date'] ??= now();

            $rawMaterial = $this->lockRawMaterial((int) $data['raw_material_id']);

            $purchase = InventoryPurchase::create($data);

            $this->setStock($rawMaterial, (float) $rawMaterial->stock_quantity + (float) $data['quantity']);

            return $purchase->load('rawMaterial');
        });
    }

    public function update(int $id, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($id, $data) {
            $purchase = $this->getById($id);

            $oldRawMaterial = $this->lockRawMaterial((int) $purchase->raw_material_id);

            $newRawMaterialId = (int) ($data['raw_material_id'] ?? $purchase->raw_material_id);
            $sameMaterial = $newRawMaterialId === $oldRawMaterial->id;
            $newRawMaterial = $sameMaterial
                ? $oldRawMaterial
                : $this->lockRawMaterial($newRawMaterialId);

            $newQuantity = (float) ($data['quantity'] ?? $purchase->quantity);
            $oldQuantity = (float) $purchase->quantity;

            if ($sameMaterial) {
                $oldProspective = round((float) $oldRawMaterial->stock_quantity + ($newQuantity - $oldQuantity), 2);
                $newProspective = $oldProspective;
            } else {
                $oldProspective = round((float) $oldRawMaterial->stock_quantity - $oldQuantity, 2);
                $newProspective = round((float) $newRawMaterial->stock_quantity + $newQuantity, 2);
            }

            if ($oldProspective < 0 || $newProspective < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot go below zero.'],
                ]);
            }

            $purchase->update($data);
            $this->setStock($oldRawMaterial, $oldProspective);

            if (! $sameMaterial) {
                $this->setStock($newRawMaterial, $newProspective);
            }

            return $this->getById($purchase->id);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $purchase = $this->getById($id);
            $rawMaterial = $this->lockRawMaterial((int) $purchase->raw_material_id);

            $prospective = round((float) $rawMaterial->stock_quantity - (float) $purchase->quantity, 2);

            if ($prospective < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot go below zero.'],
                ]);
            }

            $purchase->delete();
            $this->setStock($rawMaterial, $prospective);
        });
    }

    private function lockRawMaterial(int $id): RawMaterial
    {
        return RawMaterial::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function setStock(RawMaterial $rawMaterial, float $newStock): void
    {
        $rawMaterial->update(['stock_quantity' => round($newStock, 2)]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryPurchaseServiceTest` (workdir `backend`)
Expected: PASS (11 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/InventoryPurchaseService.php backend/tests/Feature/InventoryPurchaseServiceTest.php
git commit -m "feat: add inventory purchase service with transactional stock sync"
```

---

### Task 5: InventoryPurchaseController + routes + endpoint tests

**Files:**
- Create: `backend/app/Http/Controllers/InventoryPurchaseController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/InventoryPurchaseTest.php`

**Interfaces:**
- Consumes: `InventoryPurchaseService` (Task 4), `InventoryPurchaseRequest` (Task 2), `InventoryPurchaseResource` (Task 3).
- Produces: `InventoryPurchaseController` with `index/store/show/update/destroy`. Flat `apiResource` route registered in `routes/api.php`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryPurchaseTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_purchase_list(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram']);
        InventoryPurchase::factory()->count(2)->create(['raw_material_id' => $rawMaterial->id]);

        $response = $this->getJson('/api/inventory-purchases');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.raw_material.name', 'Flour');
    }

    public function test_store_creates_purchase_and_increments_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);

        $response = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 50);
        $this->assertDatabaseHas('inventory_purchases', ['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);
        $this->assertEquals(150, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_show_returns_purchase(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $purchase = InventoryPurchase::factory()->create(['raw_material_id' => $rawMaterial->id, 'quantity' => 50]);

        $response = $this->getJson("/api/inventory-purchases/{$purchase->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $purchase->id)
            ->assertJsonPath('data.quantity', 50);
    }

    public function test_update_updates_purchase_and_adjusts_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $response = $this->putJson("/api/inventory-purchases/{$purchase['id']}", [
            'quantity' => 80,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity', 80);
        $this->assertEquals(130, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_destroy_deletes_purchase_and_decrements_stock(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 100]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $response = $this->deleteJson("/api/inventory-purchases/{$purchase['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('inventory_purchases', ['id' => $purchase['id']]);
        $this->assertEquals(50, (float) $rawMaterial->refresh()->stock_quantity);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => 999999,
            'quantity' => 0,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_purchase_returns_404(): void
    {
        $response = $this->getJson('/api/inventory-purchases/999999');

        $response->assertNotFound();
    }

    public function test_delete_rejects_stock_would_go_negative(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['stock_quantity' => 10]);
        $purchase = $this->postJson('/api/inventory-purchases', [
            'raw_material_id' => $rawMaterial->id,
            'quantity' => 50,
        ])->json('data');

        $rawMaterial->update(['stock_quantity' => 0]);

        $response = $this->deleteJson("/api/inventory-purchases/{$purchase['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase['id']]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryPurchaseTest` (workdir `backend`)
Expected: FAIL — route `/api/inventory-purchases` not defined (404 responses), `InventoryPurchaseController` missing.

- [ ] **Step 3: Implement controller + routes**

Create `backend/app/Http/Controllers/InventoryPurchaseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryPurchaseRequest;
use App\Http\Resources\InventoryPurchaseResource;
use App\Services\InventoryPurchaseService;
use Illuminate\Http\JsonResponse;

class InventoryPurchaseController extends Controller
{
    public function __construct(private readonly InventoryPurchaseService $inventoryPurchaseService)
    {
    }

    public function index(): JsonResponse
    {
        $purchases = $this->inventoryPurchaseService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchases retrieved successfully',
            'data' => InventoryPurchaseResource::collection($purchases),
        ]);
    }

    public function store(InventoryPurchaseRequest $request): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase created successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase retrieved successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ]);
    }

    public function update(InventoryPurchaseRequest $request, int $id): JsonResponse
    {
        $purchase = $this->inventoryPurchaseService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase updated successfully',
            'data' => new InventoryPurchaseResource($purchase),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->inventoryPurchaseService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Inventory purchase deleted successfully',
            'data' => null,
        ]);
    }
}
```

Modify `backend/routes/api.php` to add the import and the `apiResource` route (keep all existing routes):

```php
<?php

use App\Http\Controllers\InventoryPurchaseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);
Route::apiResource('raw-materials', RawMaterialController::class);
Route::apiResource('inventory-purchases', InventoryPurchaseController::class);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryPurchaseTest` (workdir `backend`)
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/InventoryPurchaseController.php backend/routes/api.php backend/tests/Feature/InventoryPurchaseTest.php
git commit -m "feat: add inventory purchase controller and routes"
```

---

### Task 6: InventoryAvailabilityService + controller + routes + tests

**Files:**
- Create: `backend/app/Services/InventoryAvailabilityService.php`
- Create: `backend/app/Http/Controllers/InventoryAvailabilityController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/InventoryAvailabilityTest.php`

**Interfaces:**
- Consumes: `RawMaterial` model; `InventoryAvailabilityResource` from Task 3.
- Produces: `InventoryAvailabilityService` with `getStatus(): Collection` — a collection of `RawMaterial` models each carrying a dynamic `status` attribute (`available` | `low` | `out_of_stock`), ordered by name. Used by `InventoryAvailabilityController::index`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InventoryAvailabilityTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\InventoryAvailabilityResource;
use App\Models\RawMaterial;
use App\Services\InventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_returns_availability_for_all_raw_materials(): void
    {
        RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 20]);
        RawMaterial::factory()->create(['name' => 'Sugar', 'stock_quantity' => 5]);
        RawMaterial::factory()->create(['name' => 'Salt', 'stock_quantity' => 0]);

        $response = $this->getJson('/api/inventory/availability');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_endpoint_classifies_stock_status_at_boundaries(): void
    {
        RawMaterial::factory()->create(['name' => 'Avail', 'stock_quantity' => 10]);
        RawMaterial::factory()->create(['name' => 'Low', 'stock_quantity' => 9.99]);
        RawMaterial::factory()->create(['name' => 'Out', 'stock_quantity' => 0]);
        RawMaterial::factory()->create(['name' => 'Plenty', 'stock_quantity' => 10.01]);

        $response = $this->getJson('/api/inventory/availability');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.1.status', 'low')
            ->assertJsonPath('data.2.status', 'out_of_stock')
            ->assertJsonPath('data.3.status', 'available');
    }

    public function test_service_returns_classified_status(): void
    {
        RawMaterial::factory()->create(['stock_quantity' => 0]);

        $statuses = (new InventoryAvailabilityService())->getStatus();

        $this->assertCount(1, $statuses);
        $this->assertSame('out_of_stock', $statuses->first()->status);
    }

    public function test_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create(['name' => 'Flour', 'unit' => 'gram', 'stock_quantity' => 20]);
        $rawMaterial->status = 'available';

        $resource = (new InventoryAvailabilityResource($rawMaterial))->resolve();

        $this->assertEquals($rawMaterial->id, $resource['id']);
        $this->assertEquals('Flour', $resource['name']);
        $this->assertEquals('gram', $resource['unit']);
        $this->assertEquals(20, $resource['stock_quantity']);
        $this->assertEquals('available', $resource['status']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryAvailabilityTest` (workdir `backend`)
Expected: FAIL — class `InventoryAvailabilityService` not found / route `/api/inventory/availability` not defined.

- [ ] **Step 3: Implement the service and controller, add the route**

Create `backend/app/Services/InventoryAvailabilityService.php`:

```php
<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;

class InventoryAvailabilityService
{
    private const LOW_STOCK_THRESHOLD = 10;

    public function getStatus(): Collection
    {
        return RawMaterial::query()
            ->orderBy('name')
            ->get()
            ->each(function (RawMaterial $rawMaterial): void {
                $rawMaterial->status = $this->classify((float) $rawMaterial->stock_quantity);
            });
    }

    private function classify(float $stock): string
    {
        if ($stock <= 0) {
            return 'out_of_stock';
        }

        return $stock < self::LOW_STOCK_THRESHOLD ? 'low' : 'available';
    }
}
```

Create `backend/app/Http/Controllers/InventoryAvailabilityController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryAvailabilityResource;
use App\Services\InventoryAvailabilityService;
use Illuminate\Http\JsonResponse;

class InventoryAvailabilityController extends Controller
{
    public function __construct(private readonly InventoryAvailabilityService $inventoryAvailabilityService)
    {
    }

    public function index(): JsonResponse
    {
        $statuses = $this->inventoryAvailabilityService->getStatus();

        return response()->json([
            'success' => true,
            'message' => 'Inventory availability retrieved successfully',
            'data' => InventoryAvailabilityResource::collection($statuses),
        ]);
    }
}
```

Modify `backend/routes/api.php` to add the import and the route (keep all existing routes):

```php
<?php

use App\Http\Controllers\InventoryAvailabilityController;
use App\Http\Controllers\InventoryPurchaseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);
Route::apiResource('raw-materials', RawMaterialController::class);
Route::apiResource('inventory-purchases', InventoryPurchaseController::class);

Route::get('inventory/availability', [InventoryAvailabilityController::class, 'index']);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryAvailabilityTest` (workdir `backend`)
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/InventoryAvailabilityService.php backend/app/Http/Controllers/InventoryAvailabilityController.php backend/routes/api.php backend/tests/Feature/InventoryAvailabilityTest.php
git commit -m "feat: add inventory availability endpoint"
```

---

### Task 7: Full verification

**Files:**
- Test: entire backend test suite

**Interfaces:**
- Consumes: all completed tasks.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test` (workdir `backend`)
Expected: ALL PASS (InventoryPurchaseModelTest, InventoryPurchaseRequestTest, InventoryPurchaseResourceTest, InventoryPurchaseServiceTest, InventoryPurchaseTest, InventoryAvailabilityTest, plus the pre-existing Recipe, Raw Material, and Product tests and ExampleTest).

- [ ] **Step 2: Run the code style fixer (check mode)**

Run: `vendor/bin/pint --test` (workdir `backend`)
Expected: PASS (no style issues). If issues are reported, run `vendor/bin/pint` to fix and re-run `vendor/bin/pint --test`.

- [ ] **Step 3: Commit any style fixes**

Only if Step 2 required changes:

```bash
git add -A
git commit -m "style: apply pint formatting"
```
