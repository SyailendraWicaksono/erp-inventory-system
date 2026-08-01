# Recipe Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement CRUD management for product recipes (composition of raw materials) with nested recipe details, following the existing Product module pattern.

**Architecture:** Laravel 12 API. Nested REST routes under products (`/api/products/{productId}/recipes`). Thin controller → FormRequest → Service (with DB transactions) → Eloquent models → API Resources. Recipes and their details are written atomically; PUT uses whole-replacement semantics for details.

**Tech Stack:** Laravel 12, PHP 8.3, PostgreSQL (prod) / SQLite in-memory (tests), PHPUnit, Eloquent ORM.

## Global Constraints

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use DB transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- Follow the existing Product module pattern (`ProductController` / `ProductRequest` / `ProductService` / `ProductResource`).
- All responses use the `{ success, message, data }` envelope.
- Validation errors keep the standard Laravel shape (`{ message, errors }`) with 422.
- Tests run against in-memory SQLite (`phpunit.xml`); use `RefreshDatabase`.
- PSR-12, snake_case DB columns, singular model names.
- No new dependencies.
- Commit after each completed task.
- Response envelope on validation failure is NOT asserted (Laravel default is `{message, errors}`).

---

### Task 1: Complete RecipeDetail model + recipe factories

**Files:**
- Modify: `backend/app/Models/RecipeDetail.php` (currently empty — add fillable + relations)
- Create: `backend/database/factories/RecipeFactory.php`
- Create: `backend/database/factories/RecipeDetailFactory.php`
- Test: `backend/tests/Feature/RecipeModelTest.php`

**Interfaces:**
- Consumes: existing `Recipe` model (`$fillable = [product_id, recipe_name]`, `product()`, `recipeDetails()`), existing `Product` / `RawMaterial` models.
- Produces: `RecipeDetail` with `$fillable = [recipe_id, raw_material_id, quantity]`, `recipe(): BelongsTo(Recipe)`, `rawMaterial(): BelongsTo(RawMaterial)`. `RecipeFactory` and `RecipeDetailFactory` classes usable in later tasks.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RecipeModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_factory_creates_recipe_with_product(): void
    {
        $recipe = Recipe::factory()->create();

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertNotNull($recipe->product);
    }

    public function test_recipe_detail_factory_creates_detail_with_relations(): void
    {
        $detail = RecipeDetail::factory()->create();

        $this->assertDatabaseHas('recipe_details', ['id' => $detail->id]);
        $this->assertNotNull($detail->recipe);
        $this->assertNotNull($detail->rawMaterial);
    }

    public function test_recipe_has_many_recipe_details(): void
    {
        $recipe = Recipe::factory()->create();
        RecipeDetail::factory()->count(2)->create(['recipe_id' => $recipe->id]);

        $this->assertCount(2, $recipe->recipeDetails);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipeModelTest` (workdir `backend`)
Expected: FAIL — `RecipeDetail::factory()` not found / `recipeDetails` relation missing on empty model.

- [ ] **Step 3: Implement model + factories**

Modify `backend/app/Models/RecipeDetail.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'raw_material_id',
        'quantity',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
```

Create `backend/database/factories/RecipeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => fn () => Product::create([
                'name' => fake()->unique()->word(),
                'base_price' => 10000,
                'is_active' => true,
            ])->id,
            'recipe_name' => fake()->words(3, true),
        ];
    }
}
```

Create `backend/database/factories/RecipeDetailFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'raw_material_id' => fn () => RawMaterial::create([
                'name' => fake()->unique()->word(),
                'stock_quantity' => 100,
                'unit' => 'gram',
            ])->id,
            'quantity' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
```

Note: `Product::factory()` and `RawMaterial::factory()` cannot be used because no factory classes exist for them; the closures create records inline. Tests may override `product_id` / `recipe_id` / `raw_material_id`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipeModelTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models/RecipeDetail.php backend/database/factories/RecipeFactory.php backend/database/factories/RecipeDetailFactory.php backend/tests/Feature/RecipeModelTest.php
git commit -m "feat: complete recipe detail model and add recipe factories"
```

---

### Task 2: RecipeRequest validation

**Files:**
- Create: `backend/app/Http/Requests/RecipeRequest.php`
- Test: `backend/tests/Feature/RecipeRequestTest.php`

**Interfaces:**
- Consumes: nothing from Task 1 (uses `RawMaterial` in tests via factory).
- Produces: `RecipeRequest` extending `Illuminate\Foundation\Http\FormRequest` with `authorize(): bool` and `rules(): array` (nested details validation, `distinct` on raw_material_id).

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RecipeRequestTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Requests\RecipeRequest;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecipeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new RecipeRequest())->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 500.00],
            ],
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_recipe_name_is_required(): void
    {
        $validator = Validator::make([
            'recipe_details' => [
                ['raw_material_id' => 1, 'quantity' => 1],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_name', $validator->errors()->toArray());
    }

    public function test_recipe_details_requires_at_least_one_item(): void
    {
        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_details', $validator->errors()->toArray());
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 0],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_details.0.quantity', $validator->errors()->toArray());
    }

    public function test_duplicate_raw_material_is_rejected(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 100],
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 200],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipeRequestTest`
Expected: FAIL — class `RecipeRequest` not found.

- [ ] **Step 3: Implement the request**

Create `backend/app/Http/Requests/RecipeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipe_name' => ['required', 'string', 'max:255'],
            'recipe_details' => ['required', 'array', 'min:1'],
            'recipe_details.*.raw_material_id' => ['required', 'integer', 'distinct', 'exists:raw_materials,id'],
            'recipe_details.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipeRequestTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/RecipeRequest.php backend/tests/Feature/RecipeRequestTest.php
git commit -m "feat: add recipe form request validation"
```

---

### Task 3: API Resources

**Files:**
- Create: `backend/app/Http/Resources/RecipeDetailResource.php`
- Create: `backend/app/Http/Resources/RecipeResource.php`
- Test: `backend/tests/Feature/RecipeResourceTest.php`

**Interfaces:**
- Consumes: `RecipeDetail` (with `rawMaterial` relation), `Recipe` (with `recipeDetails` relation) from Task 1.
- Produces: `RecipeDetailResource::collection` and `RecipeResource` — the latter embeds `recipe_details` and is used by the controller in Task 5.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RecipeResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_resource_has_expected_shape(): void
    {
        $recipe = Recipe::factory()->create();
        RecipeDetail::factory()->create(['recipe_id' => $recipe->id]);

        $resource = (new RecipeResource($recipe->load('recipeDetails.rawMaterial')))->resolve();

        $this->assertEquals($recipe->id, $resource['id']);
        $this->assertEquals($recipe->product_id, $resource['product_id']);
        $this->assertEquals($recipe->recipe_name, $resource['recipe_name']);
        $this->assertCount(1, $resource['recipe_details']);
        $this->assertArrayHasKey('raw_material_id', $resource['recipe_details'][0]);
        $this->assertArrayHasKey('raw_material_name', $resource['recipe_details'][0]);
        $this->assertArrayHasKey('quantity', $resource['recipe_details'][0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipeResourceTest`
Expected: FAIL — class `RecipeResource` not found.

- [ ] **Step 3: Implement the resources**

Create `backend/app/Http/Resources/RecipeDetailResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'raw_material_id' => $this->raw_material_id,
            'raw_material_name' => $this->rawMaterial?->name,
            'quantity' => $this->quantity,
        ];
    }
}
```

Create `backend/app/Http/Resources/RecipeResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'recipe_name' => $this->recipe_name,
            'recipe_details' => RecipeDetailResource::collection($this->whenLoaded('recipeDetails')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipeResourceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Resources/RecipeResource.php backend/app/Http/Resources/RecipeDetailResource.php backend/tests/Feature/RecipeResourceTest.php
git commit -m "feat: add recipe api resources"
```

---

### Task 4: RecipeService

**Files:**
- Create: `backend/app/Services/RecipeService.php`
- Test: `backend/tests/Feature/RecipeServiceTest.php`

**Interfaces:**
- Consumes: `Recipe`, `RecipeDetail` (Task 1).
- Produces: `RecipeService` with methods:
  - `getAll(int $productId): Collection`
  - `getById(int $productId, int $recipeId): Recipe` (throws `ModelNotFoundException` if recipe does not belong to product)
  - `create(int $productId, array $data): Recipe` (transaction)
  - `update(int $productId, int $recipeId, array $data): Recipe` (transaction, replace details)
  - `delete(int $productId, int $recipeId): void` (transaction)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RecipeServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Services\RecipeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private RawMaterial $rawMaterial;
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $this->rawMaterial = RawMaterial::create([
            'name' => 'Flour',
            'stock_quantity' => 1000,
            'unit' => 'gram',
        ]);

        $this->service = new RecipeService();
    }

    public function test_create_recipe_with_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'Chocolate Cake A',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500],
            ],
        ]);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $this->rawMaterial->id,
        ]);
    }

    public function test_get_all_filters_by_product(): void
    {
        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);
        $this->service->create($otherProduct->id, [
            'recipe_name' => 'B',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $recipes = $this->service->getAll($this->product->id);

        $this->assertCount(1, $recipes);
        $this->assertEquals('A', $recipes->first()->recipe_name);
    }

    public function test_update_replaces_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'Old',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500],
            ],
        ]);

        $otherRawMaterial = RawMaterial::create([
            'name' => 'Sugar',
            'stock_quantity' => 100,
            'unit' => 'gram',
        ]);

        $updated = $this->service->update($this->product->id, $recipe->id, [
            'recipe_name' => 'New',
            'recipe_details' => [
                ['raw_material_id' => $otherRawMaterial->id, 'quantity' => 100],
            ],
        ]);

        $this->assertEquals('New', $updated->recipe_name);
        $this->assertDatabaseMissing('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $this->rawMaterial->id,
        ]);
        $this->assertDatabaseHas('recipe_details', [
            'recipe_id' => $recipe->id,
            'raw_material_id' => $otherRawMaterial->id,
        ]);
    }

    public function test_get_by_id_throws_when_recipe_not_in_product(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->getById($otherProduct->id, $recipe->id);
    }

    public function test_delete_removes_recipe_and_details(): void
    {
        $recipe = $this->service->create($this->product->id, [
            'recipe_name' => 'A',
            'recipe_details' => [['raw_material_id' => $this->rawMaterial->id, 'quantity' => 1]],
        ]);

        $this->service->delete($this->product->id, $recipe->id);

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_details', ['recipe_id' => $recipe->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipeServiceTest`
Expected: FAIL — class `RecipeService` not found.

- [ ] **Step 3: Implement the service**

Create `backend/app/Services/RecipeService.php`:

```php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function getAll(int $productId): Collection
    {
        return Recipe::with('recipeDetails.rawMaterial')
            ->where('product_id', $productId)
            ->latest()
            ->get();
    }

    public function getById(int $productId, int $recipeId): Recipe
    {
        return Recipe::with('recipeDetails.rawMaterial')
            ->where('id', $recipeId)
            ->where('product_id', $productId)
            ->firstOrFail();
    }

    public function create(int $productId, array $data): Recipe
    {
        return DB::transaction(function () use ($productId, $data) {
            $product = Product::findOrFail($productId);

            $recipe = $product->recipes()->create([
                'recipe_name' => $data['recipe_name'],
            ]);

            foreach ($data['recipe_details'] as $detail) {
                $recipe->recipeDetails()->create([
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                ]);
            }

            return $recipe->load('recipeDetails.rawMaterial');
        });
    }

    public function update(int $productId, int $recipeId, array $data): Recipe
    {
        return DB::transaction(function () use ($productId, $recipeId, $data) {
            $recipe = $this->getById($productId, $recipeId);

            $recipe->update([
                'recipe_name' => $data['recipe_name'],
            ]);

            $recipe->recipeDetails()->delete();

            foreach ($data['recipe_details'] as $detail) {
                $recipe->recipeDetails()->create([
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                ]);
            }

            return $recipe->load('recipeDetails.rawMaterial');
        });
    }

    public function delete(int $productId, int $recipeId): void
    {
        DB::transaction(function () use ($productId, $recipeId) {
            $this->getById($productId, $recipeId)->delete();
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipeServiceTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/RecipeService.php backend/tests/Feature/RecipeServiceTest.php
git commit -m "feat: add recipe service with transactional writes"
```

---

### Task 5: RecipeController + routes

**Files:**
- Create: `backend/app/Http/Controllers/RecipeController.php`
- Modify: `backend/routes/api.php` (add nested routes)
- Test: `backend/tests/Feature/RecipeTest.php`

**Interfaces:**
- Consumes: `RecipeService` (Task 4), `RecipeRequest` (Task 2), `RecipeResource` (Task 3).
- Produces: `RecipeController` with `index/store/show/update/destroy`. Nested routes registered in `routes/api.php`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/RecipeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private RawMaterial $rawMaterial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $this->rawMaterial = RawMaterial::create([
            'name' => 'Flour',
            'stock_quantity' => 1000,
            'unit' => 'gram',
        ]);
    }

    private function recipePayload(): array
    {
        return [
            'recipe_name' => 'Chocolate Cake A',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 500.00],
            ],
        ];
    }

    public function test_index_returns_recipe_list(): void
    {
        $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload());

        $response = $this->getJson("/api/products/{$this->product->id}/recipes");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_recipe(): void
    {
        $response = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake A')
            ->assertJsonCount(1, 'data.recipe_details')
            ->assertJsonPath('data.recipe_details.0.raw_material_id', $this->rawMaterial->id);
    }

    public function test_show_returns_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->getJson("/api/products/{$this->product->id}/recipes/{$created['id']}");

        $response->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake A');
    }

    public function test_update_replaces_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->putJson("/api/products/{$this->product->id}/recipes/{$created['id']}", [
            'recipe_name' => 'Chocolate Cake B',
            'recipe_details' => [
                ['raw_material_id' => $this->rawMaterial->id, 'quantity' => 300.00],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.recipe_name', 'Chocolate Cake B')
            ->assertJsonCount(1, 'data.recipe_details')
            ->assertJsonPath('data.recipe_details.0.raw_material_id', $this->rawMaterial->id);
    }

    public function test_destroy_deletes_recipe(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $response = $this->deleteJson("/api/products/{$this->product->id}/recipes/{$created['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('recipes', ['id' => $created['id']]);
        $this->assertDatabaseMissing('recipe_details', ['recipe_id' => $created['id']]);
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson("/api/products/{$this->product->id}/recipes", [
            'recipe_name' => '',
            'recipe_details' => [],
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_recipe_of_another_product_returns_404(): void
    {
        $created = $this->postJson("/api/products/{$this->product->id}/recipes", $this->recipePayload())->json('data');

        $otherProduct = Product::create([
            'name' => 'Other Cake',
            'base_price' => 30000,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/products/{$otherProduct->id}/recipes/{$created['id']}");

        $response->assertNotFound();
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $response = $this->postJson('/api/products/999999/recipes', $this->recipePayload());

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipeTest`
Expected: FAIL — routes not defined (404 responses), `RecipeController` missing.

- [ ] **Step 3: Implement controller + routes**

Create `backend/app/Http/Controllers/RecipeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $recipeService)
    {
    }

    public function index(int $productId): JsonResponse
    {
        $recipes = $this->recipeService->getAll($productId);

        return response()->json([
            'success' => true,
            'message' => 'Recipes retrieved successfully',
            'data' => RecipeResource::collection($recipes),
        ]);
    }

    public function store(RecipeRequest $request, int $productId): JsonResponse
    {
        $recipe = $this->recipeService->create($productId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recipe created successfully',
            'data' => new RecipeResource($recipe),
        ], 201);
    }

    public function show(int $productId, int $recipeId): JsonResponse
    {
        $recipe = $this->recipeService->getById($productId, $recipeId);

        return response()->json([
            'success' => true,
            'message' => 'Recipe retrieved successfully',
            'data' => new RecipeResource($recipe),
        ]);
    }

    public function update(RecipeRequest $request, int $productId, int $recipeId): JsonResponse
    {
        $recipe = $this->recipeService->update($productId, $recipeId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recipe updated successfully',
            'data' => new RecipeResource($recipe),
        ]);
    }

    public function destroy(int $productId, int $recipeId): JsonResponse
    {
        $this->recipeService->delete($productId, $recipeId);

        return response()->json([
            'success' => true,
            'message' => 'Recipe deleted successfully',
            'data' => null,
        ]);
    }
}
```

Modify `backend/routes/api.php` to append the nested routes (keep the existing product route):

```php
<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class);

Route::get('products/{productId}/recipes', [RecipeController::class, 'index']);
Route::post('products/{productId}/recipes', [RecipeController::class, 'store']);
Route::get('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'show']);
Route::put('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'update']);
Route::delete('products/{productId}/recipes/{recipeId}', [RecipeController::class, 'destroy']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipeTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/RecipeController.php backend/routes/api.php backend/tests/Feature/RecipeTest.php
git commit -m "feat: add recipe controller and nested routes"
```

---

### Task 6: Full verification

**Files:**
- Test: entire backend test suite

**Interfaces:**
- Consumes: all completed tasks.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test` (workdir `backend`)
Expected: ALL PASS (RecipeModelTest, RecipeRequestTest, RecipeResourceTest, RecipeServiceTest, RecipeTest, plus the pre-existing ExampleTest).

- [ ] **Step 2: Run the code style fixer (check mode)**

Run: `vendor/bin/pint --test`
Expected: PASS (no style issues). If issues are reported, run `vendor/bin/pint` to fix and re-run `vendor/bin/pint --test`.

- [ ] **Step 3: Commit any style fixes**

Only if Step 2 required changes:

```bash
git add -A
git commit -m "style: apply pint formatting"
```
