# Raw Material Module — Design Specification

Date: 2026-08-02
Status: Approved for implementation

## 1. Overview

The Raw Material module manages the data for raw materials (bahan baku) used in production. It is a data-management module that supports the make-to-order business model. The module defines the master record for each raw material — its name, current stock, unit of measure, and optional expiration date.

Raw material data feeds Recipe Details (composition), Production Planning (material requirement calculation), and Inventory Management (stock consumption and purchases). Those consumers are out of scope for this module. This module only manages raw material data itself.

## 2. Scope

### In scope

- Full CRUD API for raw materials.
- Feature tests for all endpoints.
- Completion of the raw material layer following the existing Product module pattern.

### Out of scope

- Inventory purchases (`inventory_purchases`) — the next "Inventory" module.
- Stock consumption / deduction during production.
- Stock incrementing from purchases (belongs to the Inventory module).
- Guarding against deleting raw materials still referenced by recipes (matches the Recipe module scope decision; DB foreign key cascade applies).
- Authentication / authorization middleware.
- Fixing the broken `DatabaseSeeder` references (`ProductSeeder`, `RawMaterialSeeder`, `OrderSeeder` do not exist).

## 3. Database

Migrations already exist and must NOT be modified. No new migrations, tables, columns, or renames.

### Tables

**raw_materials**

| Column          | Type            | Notes                          |
| --------------- | --------------- | ------------------------------ |
| id              | bigint PK       |                                |
| name            | varchar         |                                |
| stock_quantity  | decimal(12,2)   | default 0; must be >= 0        |
| unit            | varchar         |                                |
| expiration_date | date            | nullable                       |
| created_at      | timestamp       |                                |
| updated_at      | timestamp       |                                |

### Existing code

- `RawMaterial` model already has `$fillable = [name, stock_quantity, unit, expiration_date]` plus `recipeDetails()` and `inventoryPurchases()` relations. No model changes required.
- `RawMaterialFactory` already exists (`name`, `stock_quantity = 100`, `unit = 'gram'`).

## 4. API Endpoints

REST, flat (not nested). Base path prefix is `/api`.

| Method | URI                          | Purpose                        | Success |
| ------ | ---------------------------- | ------------------------------ | ------- |
| GET    | /api/raw-materials           | List all raw materials         | 200     |
| POST   | /api/raw-materials           | Create a raw material          | 201     |
| GET    | /api/raw-materials/{id}      | Show one raw material          | 200     |
| PUT    | /api/raw-materials/{id}      | Update (partial) a raw material | 200    |
| DELETE | /api/raw-materials/{id}      | Delete a raw material          | 200     |

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

`RawMaterialRequest` (applies to both POST and PUT; PUT uses partial `sometimes` semantics, identical to `ProductRequest`):

- `name`: required, string, max:255
- `stock_quantity`: required on POST, `sometimes` on PUT; numeric, min:0, max:9999999999.99
- `unit`: required, string, max:255
- `expiration_date`: nullable, date

## 6. Architecture & Components

Follows the existing Product module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `app/Http/Controllers/RawMaterialController.php` | Thin controller, DI of `RawMaterialService`, 5 REST actions |
| `app/Http/Requests/RawMaterialRequest.php` | Validation per Section 5 |
| `app/Services/RawMaterialService.php` | `getAll`, `getById`, `create`, `update`, `delete` |
| `app/Http/Resources/RawMaterialResource.php` | Serializes a raw material |
| `tests/Feature/RawMaterialRequestTest.php` | Validation tests |
| `tests/Feature/RawMaterialResourceTest.php` | Resource shape test |
| `tests/Feature/RawMaterialServiceTest.php` | Service logic tests |
| `tests/Feature/RawMaterialTest.php` | Endpoint tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `routes/api.php` | Register `Route::apiResource('raw-materials', RawMaterialController::class)` |

### Service behavior

- `getAll()`: return all raw materials, latest first.
- `getById(int $id)`: `findOrFail`.
- `create(array $data)`: create inside a DB transaction.
- `update(int $id, array $data)`: find (404 if missing) and update inside a DB transaction.
- `delete(int $id)`: find (404 if missing) and delete inside a DB transaction.

## 7. Resources

`RawMaterialResource` returns:

```json
{
  "id": 1,
  "name": "Flour",
  "stock_quantity": 500.00,
  "unit": "gram",
  "expiration_date": "2026-08-30",
  "created_at": "...",
  "updated_at": "..."
}
```

## 8. Error Handling

- 404: raw material not found.
- 422: validation failures (standard Laravel validation error shape with `errors`).
- 500: unexpected exceptions — Laravel default handler.

## 9. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`), using `RefreshDatabase`.

Cover:

- Request: valid payload passes; name required; stock_quantity min 0 (rejects negative); unit required; expiration_date invalid date rejected; PUT partial (missing optional fields passes).
- Service: create persists; getById returns; getById throws for missing id; update changes fields; delete removes record.
- Resource: expected shape keys present.
- Endpoints: GET list 200; POST creates 201; GET show 200; PUT updates 200; DELETE removes 200; validation failure 422; nonexistent id 404.

Run with: `php artisan test --filter=RawMaterial` (or `composer test`), then the full suite and `vendor/bin/pint --test`.

## 10. Edge Cases

- `stock_quantity` is never negative (validated with `min:0`).
- `expiration_date` is optional; only the `date` format is enforced.
- PUT only updates the fields present in the request (partial).
- Deleting a raw material cascades to `recipe_details` and `inventory_purchases` at the DB level — accepted, per Recipe module scope decision.

## 11. Constraints & Rules Followed

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
- Validation error shape is the Laravel default `{message, errors}` (envelope NOT asserted on 422).
