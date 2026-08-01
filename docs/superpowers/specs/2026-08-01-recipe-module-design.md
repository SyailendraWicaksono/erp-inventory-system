# Recipe Module — Design Specification

Date: 2026-08-01
Status: Approved for implementation

## 1. Overview

The Recipe module manages the composition of raw materials needed to produce one product. It is a data-management module that supports the make-to-order business model. The module defines:

- **Recipe**: one composition tied to one product (a product may have multiple recipe variants — "Flexible Recipe" from Discovery 3.7).
- **Recipe Detail**: one line item within a recipe — a single raw material and the quantity used.

Recipe data feeds Production Planning (material requirement calculation), Inventory Management (stock consumption), and price recommendation. Those consumers are out of scope for this module. This module only manages recipe data itself.

## 2. Scope

### In scope

- CRUD API for recipes, nested under products.
- Recipe details managed as part of the recipe (nested, atomic).
- Completion of the currently-empty `RecipeDetail` model.
- Feature tests for all endpoints.

### Out of scope

- Authentication / authorization middleware.
- Fixing the broken `DatabaseSeeder` references (`ProductSeeder`, `RawMaterialSeeder`, `OrderSeeder` do not exist).
- Guarding against deleting raw materials still referenced by recipes.
- Price calculation or stock consumption derived from recipes.

## 3. Database

Migrations already exist and must NOT be modified. No new migrations, tables, columns, or renames.

### Tables

**recipes**

| Column       | Type             | Notes                            |
| ------------ | ---------------- | -------------------------------- |
| id           | bigint PK        |                                  |
| product_id   | bigint FK        | `constrained()`, cascade delete  |
| recipe_name  | varchar          |                                  |
| created_at   | timestamp        |                                  |
| updated_at   | timestamp        |                                  |

**recipe_details**

| Column          | Type             | Notes                            |
| --------------- | ---------------- | -------------------------------- |
| id              | bigint PK        |                                  |
| recipe_id       | bigint FK        | `constrained()`, cascade delete  |
| raw_material_id | bigint FK        | `constrained()`, cascade delete  |
| quantity        | decimal(12,2)    | must be > 0                      |
| created_at      | timestamp        |                                  |
| updated_at      | timestamp        |                                  |

### Relationships

- `Product` 1—< `Recipe` 1—< `RecipeDetail` >—1 `RawMaterial`
- Existing relations already present in `Product` (`recipes()`) and `RawMaterial` (`recipeDetails()`).
- `Recipe` model already has `product()` and `recipeDetails()` relations.
- `RecipeDetail` model is empty and must be completed with `$fillable`, `recipe()`, and `rawMaterial()` relations.

## 4. API Endpoints

REST, nested under products. Base path prefix is `/api`.

| Method | URI                                            | Purpose                          | Success |
| ------ | ---------------------------------------------- | -------------------------------- | ------- |
| GET    | /api/products/{productId}/recipes              | List recipes for a product       | 200     |
| POST   | /api/products/{productId}/recipes              | Create recipe with details       | 201     |
| GET    | /api/products/{productId}/recipes/{recipeId}   | Show one recipe with details     | 200     |
| PUT    | /api/products/{productId}/recipes/{recipeId}   | Replace recipe and details       | 200     |
| DELETE | /api/products/{productId}/recipes/{recipeId}   | Delete recipe (cascade details)  | 200     |

- `productId` is resolved from the URL; not sent in the request body.
- A recipe that does not belong to the given product returns 404.
- A non-existent product returns 404.

### Request body (POST and PUT)

```json
{
  "recipe_name": "Chocolate Cake A",
  "recipe_details": [
    { "raw_material_id": 1, "quantity": 500.00 },
    { "raw_material_id": 3, "quantity": 2.50 }
  ]
}
```

PUT uses whole-replacement semantics: details are deleted and re-created from the request body, atomically.

### Response envelope

All responses follow the standard shape:

```json
{
  "success": true,
  "message": "",
  "data": {}
}
```

## 5. Validation Rules

`RecipeRequest` (applies to both POST and PUT — both are full representations):

- `recipe_name`: required, string, max:255
- `recipe_details`: required, array, min:1
- `recipe_details.*.raw_material_id`: required, integer, exists:raw_materials,id
- `recipe_details.*.quantity`: required, numeric, gt:0, max:9999999999.99
- `recipe_details.*.raw_material_id`: must be distinct within the array (reject duplicate raw materials in one recipe)

No unique constraint on `recipe_name` per product — multiple recipe variants are allowed (Flexible Recipe).

## 6. Architecture & Components

Follows the existing Product module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `app/Http/Controllers/RecipeController.php` | Thin controller, DI of `RecipeService`, 5 REST actions |
| `app/Http/Requests/RecipeRequest.php` | Validation per Section 5 |
| `app/Services/RecipeService.php` | Business logic; DB transactions for writes |
| `app/Http/Resources/RecipeResource.php` | Serializes recipe + nested `recipeDetails` |
| `app/Http/Resources/RecipeDetailResource.php` | Serializes detail + raw material name |
| `database/factories/RecipeFactory.php` | Factory for `Recipe` |
| `database/factories/RecipeDetailFactory.php` | Factory for `RecipeDetail` |
| `tests/Feature/RecipeTest.php` | Feature tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `app/Models/RecipeDetail.php` | Add `$fillable`, `recipe()`, `rawMaterial()` relations |
| `routes/api.php` | Register nested recipe routes under products |

### Service behavior

- `index(productId)`: return recipes belonging to the product, with details.
- `store(productId, data)`: create recipe + details inside a DB transaction.
- `show(productId, recipeId)`: find recipe by id where `product_id = productId`, else 404.
- `update(productId, recipeId, data)`: transaction — update recipe row, delete existing details, re-create from payload.
- `destroy(productId, recipeId)`: delete recipe (details cascade at DB level).

All write operations use `DB::transaction()`.

## 7. Resources

`RecipeResource` returns:

```json
{
  "id": 1,
  "product_id": 1,
  "recipe_name": "Chocolate Cake A",
  "recipe_details": [
    {
      "id": 1,
      "raw_material_id": 1,
      "raw_material_name": "Flour",
      "quantity": 500.00
    }
  ],
  "created_at": "...",
  "updated_at": "..."
}
```

## 8. Error Handling

- 404: product not found; recipe not found; recipe not belonging to product.
- 422: validation failures (standard Laravel validation error shape with `errors`).
- 500: unexpected exceptions — Laravel default handler.

## 9. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`), using `RefreshDatabase`.

Cover:

- GET list returns all recipes for the product, 200.
- POST creates recipe with details, 201, details persisted.
- GET show returns recipe with details, 200.
- PUT replaces recipe and details, 200.
- DELETE removes recipe and its details (cascade), 200.
- Validation: missing recipe_name → 422; empty recipe_details → 422; missing raw_material_id → 422; quantity <= 0 → 422; duplicate raw material → 422.
- Ownership: recipe belonging to a different product → 404.
- Nonexistent product → 404.

Run with: `php artisan test --filter=RecipeTest` (or `composer test`).

## 10. Edge Cases

- Update is atomic — a failure mid-write leaves no partial detail rows.
- Duplicate raw materials within one recipe are rejected by validation.
- Deleting a recipe cascades to its details (DB FK).
- No duplicate recipe_name restriction, preserving Flexible Recipe variants.

## 11. Constraints & Rules Followed

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
