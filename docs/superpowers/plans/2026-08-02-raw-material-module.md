# Raw Material Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement full CRUD management for raw materials (bahan baku), following the existing Product module pattern.

**Architecture:** Laravel API. Flat REST routes under `/api/raw-materials`. Thin controller → FormRequest → Service (with DB transactions) → Eloquent model → API Resource. PUT uses partial-update semantics identical to `ProductRequest`.

**Tech Stack:** Laravel, PHP 8.3, PostgreSQL (prod) / SQLite in-memory (tests), PHPUnit, Eloquent ORM.

## Global Constraints

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use DB transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- Follow the existing Product module pattern (`ProductController` / `ProductRequest` / `ProductService` / `ProductResource`).
- All responses use the `{ success, message, data }` envelope.
- Validation errors keep the standard Laravel shape (`{ message, errors }`) with 422; the envelope is NOT asserted on 422.
- Tests run against in-memory SQLite (`phpunit.xml`); use `RefreshDatabase`.
- PSR-12, snake_case DB columns, singular model names.
- No new dependencies.
- Commit after each completed task.
- The `RawMaterial` model and `RawMaterialFactory` already exist — do NOT recreate them. The model has `$fillable = [name, stock_quantity, unit, expiration_date]` and relations `recipeDetails()` / `inventoryPurchases()`. The factory returns `name => fake()->unique()->word()`, `stock_quantity => 100`, `unit => 'gram'`.

---

### Task 1: RawMaterial model + factory test

**Files:**
- Create: `backend/tests/Feature/RawMaterialModelTest.php`

**Interfaces:**
- Consumes: existing `RawMaterial` model and `RawMaterialFactory`; existing `RecipeDetail` and `InventoryPurchase` models.
- Produces: verification that the existing model/factory/relations behave correctly so later tasks can rely on them.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RawMaterialModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_raw_material(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $this->assertDatabaseHas('raw_materials', ['id' => $rawMaterial->id]);
        $this->assertSame(100, (int) $rawMaterial->stock_quantity);
        $this->assertSame('gram', $rawMaterial->unit);
    }

    public function test_has_many_recipe_details(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        RecipeDetail::factory()->count(2)->create(['raw_material_id' => $rawMaterial->id]);

        $this->assertCount(2, $rawMaterial->recipeDetails);
    }

    public function test_has_many_inventory_purchases(): void
    {
        $rawMaterial = RawMaterial::factory()->create();
        $rawMaterial->inventoryPurchases()->create([
            'quantity' => 50,
            'purchase_date' => now(),
        ]);

        $this->assertCount(1, $rawMaterial->inventoryPurchases);
        $this->assertNotNull($rawMaterial->inventoryPurchases->first()->purchase_date);
    }
}
```

Note: `RecipeDetail::factory()` exists and creates a Recipe (and its Product) on the fly; passing `raw_material_id` overrides its default.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialModelTest` (workdir `backend`)
Expected: FAIL — `RecipeDetail::factory()` currently fails at the DB level (see the inline `RawMaterial::create` in `RecipeDetailFactory` when a `raw_material_id` is NOT overridden). If the pre-existing factory already works, the test may PASS immediately — in that case record that fact and proceed (the test then serves as a regression guard).

- [ ] **Step 3: If needed, make the test pass without touching migrations**

If the run in Step 2 failed, inspect the failure. The failure must be resolved by writing correct tests only — do NOT modify the `RawMaterial` model, the `RawMaterialFactory`, or any migration. If the failure is caused by the pre-existing `RecipeDetailFactory`'s inline `RawMaterial::create`, fix the factory usage inside the test (override `raw_material_id` in Step 1, which is already done) rather than editing the factory.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RawMaterialModelTest` (workdir `backend`)
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/RawMaterialModelTest.php
git commit -m "test: add raw material model and factory tests"
```

---

### Task 2: RawMaterialRequest validation

**Files:**
- Create: `backend/app/Http/Requests/RawMaterialRequest.php`
- Test: `backend/tests/Feature/RawMaterialRequestTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `RawMaterialRequest` extending `Illuminate\Foundation\Http\FormRequest` with `authorize(): bool` and `rules(): array`. Used by the controller in Task 5.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RawMaterialRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Requests\RawMaterialRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RawMaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new RawMaterialRequest())->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 500.00,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_name_is_required(): void
    {
        $validator = Validator::make([
            'stock_quantity' => 100,
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_stock_quantity_is_required(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stock_quantity', $validator->errors()->toArray());
    }

    public function test_stock_quantity_must_not_be_negative(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => -1,
            'unit' => 'gram',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stock_quantity', $validator->errors()->toArray());
    }

    public function test_unit_is_required(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 100,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('unit', $validator->errors()->toArray());
    }

    public function test_expiration_date_must_be_a_valid_date(): void
    {
        $validator = Validator::make([
            'name' => 'Flour',
            'stock_quantity' => 100,
            'unit' => 'gram',
            'expiration_date' => 'not-a-date',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expiration_date', $validator->errors()->toArray());
    }

    public function test_put_allows_partial_update(): void
    {
        $request = RawMaterialRequest::create('/api/raw-materials/1', 'PUT', [
            'name' => 'Whole Wheat Flour',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialRequestTest` (workdir `backend`)
Expected: FAIL — class `RawMaterialRequest` not found.

- [ ] **Step 3: Implement the request**

Create `backend/app/Http/Requests/RawMaterialRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'unit' => ['required', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['stock_quantity'] = ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'];
            $rules['unit'] = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RawMaterialRequestTest` (workdir `backend`)
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/RawMaterialRequest.php backend/tests/Feature/RawMaterialRequestTest.php
git commit -m "feat: add raw material form request validation"
```

---

### Task 3: RawMaterialResource

**Files:**
- Create: `backend/app/Http/Resources/RawMaterialResource.php`
- Test: `backend/tests/Feature/RawMaterialResourceTest.php`

**Interfaces:**
- Consumes: `RawMaterial` model from Task 1.
- Produces: `RawMaterialResource` used by the controller in Task 5.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RawMaterialResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\RawMaterialResource;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_material_resource_has_expected_shape(): void
    {
        $rawMaterial = RawMaterial::factory()->create([
            'name' => 'Flour',
            'stock_quantity' => 500,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ]);

        $resource = (new RawMaterialResource($rawMaterial))->resolve();

        $this->assertEquals($rawMaterial->id, $resource['id']);
        $this->assertEquals('Flour', $resource['name']);
        $this->assertEquals(500, $resource['stock_quantity']);
        $this->assertEquals('gram', $resource['unit']);
        $this->assertEquals('2026-08-30', $resource['expiration_date']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialResourceTest` (workdir `backend`)
Expected: FAIL — class `RawMaterialResource` not found.

- [ ] **Step 3: Implement the resource**

Create `backend/app/Http/Resources/RawMaterialResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock_quantity' => $this->stock_quantity,
            'unit' => $this->unit,
            'expiration_date' => $this->expiration_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RawMaterialResourceTest` (workdir `backend`)
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/RawMaterialResource.php backend/tests/Feature/RawMaterialResourceTest.php
git commit -m "feat: add raw material api resource"
```

---

### Task 4: RawMaterialService

**Files:**
- Create: `backend/app/Services/RawMaterialService.php`
- Test: `backend/tests/Feature/RawMaterialServiceTest.php`

**Interfaces:**
- Consumes: `RawMaterial` model from Task 1.
- Produces: `RawMaterialService` with methods:
  - `getAll(): Collection`
  - `getById(int $id): RawMaterial` (throws `ModelNotFoundException` when missing)
  - `create(array $data): RawMaterial` (transaction)
  - `update(int $id, array $data): RawMaterial` (transaction)
  - `delete(int $id): void` (transaction)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RawMaterialServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\RawMaterialService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialServiceTest extends TestCase
{
    use RefreshDatabase;

    private RawMaterialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RawMaterialService();
    }

    public function test_create_persists_raw_material(): void
    {
        $rawMaterial = $this->service->create([
            'name' => 'Flour',
            'stock_quantity' => 500,
            'unit' => 'gram',
        ]);

        $this->assertDatabaseHas('raw_materials', [
            'id' => $rawMaterial->id,
            'name' => 'Flour',
            'unit' => 'gram',
        ]);
    }

    public function test_get_all_returns_all_raw_materials(): void
    {
        $this->service->create(['name' => 'Sugar', 'stock_quantity' => 100, 'unit' => 'gram']);
        $this->service->create(['name' => 'Flour', 'stock_quantity' => 200, 'unit' => 'gram']);

        $rawMaterials = $this->service->getAll();

        $this->assertCount(2, $rawMaterials);
    }

    public function test_get_by_id_returns_raw_material(): void
    {
        $created = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $found = $this->service->getById($created->id);

        $this->assertEquals($created->id, $found->id);
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_update_changes_fields(): void
    {
        $rawMaterial = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $updated = $this->service->update($rawMaterial->id, [
            'stock_quantity' => 800,
            'expiration_date' => '2026-08-30',
        ]);

        $this->assertEquals(800, $updated->stock_quantity);
        $this->assertEquals('2026-08-30', $updated->expiration_date);
        $this->assertDatabaseHas('raw_materials', ['id' => $rawMaterial->id, 'stock_quantity' => 800]);
    }

    public function test_delete_removes_raw_material(): void
    {
        $rawMaterial = $this->service->create(['name' => 'Flour', 'stock_quantity' => 500, 'unit' => 'gram']);

        $this->service->delete($rawMaterial->id);

        $this->assertDatabaseMissing('raw_materials', ['id' => $rawMaterial->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialServiceTest` (workdir `backend`)
Expected: FAIL — class `RawMaterialService` not found.

- [ ] **Step 3: Implement the service**

Create `backend/app/Services/RawMaterialService.php`:

```php
<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RawMaterialService
{
    public function getAll(): Collection
    {
        return RawMaterial::latest()->get();
    }

    public function getById(int $id): RawMaterial
    {
        return RawMaterial::findOrFail($id);
    }

    public function create(array $data): RawMaterial
    {
        return DB::transaction(fn () => RawMaterial::create($data));
    }

    public function update(int $id, array $data): RawMaterial
    {
        return DB::transaction(function () use ($id, $data) {
            $rawMaterial = $this->getById($id);
            $rawMaterial->update($data);

            return $rawMaterial;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $this->getById($id)->delete();
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RawMaterialServiceTest` (workdir `backend`)
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/RawMaterialService.php backend/tests/Feature/RawMaterialServiceTest.php
git commit -m "feat: add raw material service with transactional writes"
```

---

### Task 5: RawMaterialController + routes

**Files:**
- Create: `backend/app/Http/Controllers/RawMaterialController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/RawMaterialTest.php`

**Interfaces:**
- Consumes: `RawMaterialService` (Task 4), `RawMaterialRequest` (Task 2), `RawMaterialResource` (Task 3).
- Produces: `RawMaterialController` with `index/store/show/update/destroy`. Flat `apiResource` route registered in `routes/api.php`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RawMaterialTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function rawMaterialPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Flour',
            'stock_quantity' => 500.00,
            'unit' => 'gram',
            'expiration_date' => '2026-08-30',
        ], $overrides);
    }

    public function test_index_returns_raw_material_list(): void
    {
        RawMaterial::factory()->count(2)->create();

        $response = $this->getJson('/api/raw-materials');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_raw_material(): void
    {
        $response = $this->postJson('/api/raw-materials', $this->rawMaterialPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Flour')
            ->assertJsonPath('data.unit', 'gram');
        $this->assertDatabaseHas('raw_materials', ['name' => 'Flour', 'unit' => 'gram']);
    }

    public function test_show_returns_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->getJson("/api/raw-materials/{$created['id']}");

        $response->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.name', 'Flour');
    }

    public function test_update_updates_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->putJson("/api/raw-materials/{$created['id']}", [
            'name' => 'Whole Wheat Flour',
            'stock_quantity' => 300.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Whole Wheat Flour')
            ->assertJsonPath('data.stock_quantity', 300.00);
        $this->assertDatabaseHas('raw_materials', ['id' => $created['id'], 'name' => 'Whole Wheat Flour']);
    }

    public function test_destroy_deletes_raw_material(): void
    {
        $created = $this->postJson('/api/raw-materials', $this->rawMaterialPayload())->json('data');

        $response = $this->deleteJson("/api/raw-materials/{$created['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('raw_materials', ['id' => $created['id']]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/raw-materials', [
            'name' => '',
            'stock_quantity' => -5,
            'unit' => '',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_raw_material_returns_404(): void
    {
        $response = $this->getJson('/api/raw-materials/999999');

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialTest` (workdir `backend`)
Expected: FAIL — route `/api/raw-materials` not defined (404 responses), `RawMaterialController` missing.

- [ ] **Step 3: Implement controller + routes**

Create `backend/app/Http/Controllers/RawMaterialController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawMaterialRequest;
use App\Http\Resources\RawMaterialResource;
use App\Services\RawMaterialService;
use Illuminate\Http\JsonResponse;

class RawMaterialController extends Controller
{
    public function __construct(private readonly RawMaterialService $rawMaterialService)
    {
    }

    public function index(): JsonResponse
    {
        $rawMaterials = $this->rawMaterialService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Raw materials retrieved successfully',
            'data' => RawMaterialResource::collection($rawMaterials),
        ]);
    }

    public function store(RawMaterialRequest $request): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Raw material created successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Raw material retrieved successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ]);
    }

    public function update(RawMaterialRequest $request, int $id): JsonResponse
    {
        $rawMaterial = $this->rawMaterialService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Raw material updated successfully',
            'data' => new RawMaterialResource($rawMaterial),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->rawMaterialService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Raw material deleted successfully',
            'data' => null,
        ]);
    }
}
```

Modify `backend/routes/api.php` to add the import and the `apiResource` route (keep all existing routes):

```php
<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);
Route::apiResource('raw-materials', RawMaterialController::class);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RawMaterialTest` (workdir `backend`)
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/RawMaterialController.php backend/routes/api.php backend/tests/Feature/RawMaterialTest.php
git commit -m "feat: add raw material controller and routes"
```

---

### Task 6: Full verification

**Files:**
- Test: entire backend test suite

**Interfaces:**
- Consumes: all completed tasks.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test` (workdir `backend`)
Expected: ALL PASS (RawMaterialModelTest, RawMaterialRequestTest, RawMaterialResourceTest, RawMaterialServiceTest, RawMaterialTest, plus the pre-existing Recipe and Product tests and ExampleTest).

- [ ] **Step 2: Run the code style fixer (check mode)**

Run: `vendor/bin/pint --test` (workdir `backend`)
Expected: PASS (no style issues). If issues are reported, run `vendor/bin/pint` to fix and re-run `vendor/bin/pint --test`.

- [ ] **Step 3: Commit any style fixes**

Only if Step 2 required changes:

```bash
git add -A
git commit -m "style: apply pint formatting"
```
