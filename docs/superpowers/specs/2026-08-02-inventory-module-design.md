# Inventory Module — Design Specification

Date: 2026-08-02
Status: Approved for implementation

## 1. Overview

The Inventory module manages raw material stock and purchase records for the make-to-order ERP business model. It builds on the completed Raw Material module by adding:

- Purchase record management (`inventory_purchases`), with automatic synchronization of `raw_materials.stock_quantity`.
- A read-only stock availability endpoint that classifies each raw material's current stock.

The module supports the Inventory Management business rules (BR-011, BR-012, BR-013): stock availability can be checked before production, purchases are recorded when stock is insufficient, and inventory data reflects purchases.

## 2. Scope

### In scope

- Full CRUD API for inventory purchases (`/api/inventory-purchases`).
- Automatic stock synchronization on `raw_materials.stock_quantity` for every purchase write, inside the same DB transaction, with a guard that stock never goes negative.
- A read-only stock availability endpoint (`/api/inventory/availability`).
- `InventoryPurchaseFactory` for tests.
- Feature tests for requests, resources, services, purchase endpoints, and the availability endpoint.
- Completion of the Inventory layer following the established Product / Raw Material module pattern.

### Out of scope

- Stock consumption / deduction during production (belongs to the Production module; BR-013's post-production update is handled there).
- Recipe-based availability checks (belongs to the Production module).
- Direct manual stock adjustment (already available via `PUT /raw-materials/{id}`).
- Authentication / authorization middleware.
- Any migration changes. Existing migrations must NOT be modified.

## 3. Database

Migrations already exist and must NOT be modified. No new migrations, tables, columns, or renames.

### Tables

**inventory_purchases**

| Column          | Type          | Notes                                  |
| --------------- | ------------- | -------------------------------------- |
| id              | bigint PK     |                                        |
| raw_material_id | bigint FK     | → raw_materials.id, cascade delete     |
| quantity        | decimal(12,2) | must be > 0                            |
| purchase_date   | timestamp     | non-nullable                           |
| created_at      | timestamp     |                                        |
| updated_at      | timestamp     |                                        |

**raw_materials** (read by this module; never modified structurally)

| Column         | Type          | Notes                        |
| -------------- | ------------- | ---------------------------- |
| id             | bigint PK     |                              |
| name           | varchar       |                              |
| stock_quantity | decimal(12,2) | default 0; must be >= 0      |
| unit           | varchar       |                              |
| expiration_date| date          | nullable                     |
| created_at     | timestamp     |                              |
| updated_at     | timestamp     |                              |

### Existing code

- `InventoryPurchase` model already exists with `$fillable = [raw_material_id, quantity, purchase_date]` and a `rawMaterial()` belongsTo relation. No model changes required.
- `RawMaterial` model already exists with `$fillable` and an `inventoryPurchases()` hasMany relation. No model changes required.
- `RawMaterialFactory` already exists.
- No `InventoryPurchaseFactory` exists — this module creates it (Section 11).

## 4. API Endpoints

REST, flat (not nested). Base path prefix is `/api`. All responses use the `{success, message, data}` envelope.

| Method | URI                          | Purpose                                   | Success |
| ------ | ---------------------------- | ----------------------------------------- | ------- |
| GET    | /api/inventory-purchases     | List all purchases, latest first          | 200     |
| POST   | /api/inventory-purchases     | Create a purchase (stock += quantity)     | 201     |
| GET    | /api/inventory-purchases/{id}| Show one purchase                          | 200     |
| PUT    | /api/inventory-purchases/{id}| Update a purchase (stock ± difference)    | 200     |
| DELETE | /api/inventory-purchases/{id}| Delete a purchase (stock -= quantity)     | 200     |
| GET    | /api/inventory/availability  | Stock status of all raw materials         | 200     |

### Response envelope

```json
{
  "success": true,
  "message": "",
  "data": {}
}
```

## 5. Validation Rules

`InventoryPurchaseRequest` (POST uses `required`; PUT uses `sometimes` partial semantics, matching `ProductRequest` / `RawMaterialRequest`):

- `raw_material_id`: required on POST, `sometimes` on PUT; `exists:raw_materials,id`.
- `quantity`: required on POST, `sometimes` on PUT; numeric, min:0.01, max:9999999999.99.
- `purchase_date`: on POST `nullable`, date (service defaults to `now()`); on PUT `sometimes`, date — a present `null` fails the `date` rule and returns 422 (never writes null to the non-nullable column).

## 6. Architecture & Components

Follows the existing module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `app/Http/Controllers/InventoryPurchaseController.php` | Thin controller, DI of `InventoryPurchaseService`, 5 REST actions |
| `app/Http/Controllers/InventoryAvailabilityController.php` | Thin controller, single `index` action |
| `app/Http/Requests/InventoryPurchaseRequest.php` | Validation per Section 5 |
| `app/Services/InventoryPurchaseService.php` | Purchase CRUD + stock sync + negative guard (Section 7) |
| `app/Services/InventoryAvailabilityService.php` | Availability classification (Section 8) |
| `app/Http/Resources/InventoryPurchaseResource.php` | Serializes a purchase + nested raw material |
| `app/Http/Resources/InventoryAvailabilityResource.php` | Serializes a raw material + status |
| `database/factories/InventoryPurchaseFactory.php` | Test factory per Section 11 |
| `tests/Feature/InventoryPurchaseRequestTest.php` | Validation tests |
| `tests/Feature/InventoryPurchaseResourceTest.php` | Resource shape tests |
| `tests/Feature/InventoryPurchaseServiceTest.php` | Service logic + stock sync + guard tests |
| `tests/Feature/InventoryPurchaseTest.php` | Purchase endpoint tests |
| `tests/Feature/InventoryAvailabilityTest.php` | Availability endpoint + service + resource tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `routes/api.php` | Register `Route::apiResource('inventory-purchases', InventoryPurchaseController::class)` and `Route::get('inventory/availability', [InventoryAvailabilityController::class, 'index'])` |

## 7. InventoryPurchaseService behavior

All write operations run inside a DB transaction and acquire `lockForUpdate()` on every affected `raw_materials` row **before** any read-modify-write, serializing concurrent purchases on the same material (no lost updates).

Every computed stock value is normalized with `round(..., 2)` **before** being written, and the negative guard checks the rounded value. Stored `stock_quantity` values therefore always remain clean 2-decimal values.

- `getAll()`: `latest()->get()` with `rawMaterial` eager-loaded (required by the resource; avoids N+1).
- `getById(int $id)`: `findOrFail` with `rawMaterial` eager-loaded.
- `create(array $data)`: lock the raw material row → create the purchase (`purchase_date` defaults to `now()` when absent) → `newStock = round(oldStock + quantity, 2)` → persist the incremented stock. Same transaction.
- `update(int $id, array $data)`: lock the affected rows → load the current purchase → compute the prospective stock for both the old and new raw material → guard **both** prospective values (must be >= 0) → only then persist. No write lands until every guard passes (no partial mutation).
  - Quantity change: adjust by the difference `round(oldStock + (newQuantity - oldQuantity), 2)`.
  - `raw_material_id` change: subtract `quantity` from the old material, add it to the new material. If the provided id equals the purchase's current id, movement is a no-op (quantity difference still applies).
- `delete(int $id)`: lock the raw material row → `newStock = round(oldStock - quantity, 2)` → guard (must be >= 0) → delete the purchase and persist the decremented stock. Same transaction.

### Negative guard

Any adjustment that would drive a `stock_quantity` below zero throws a `ValidationException` (422) and the entire transaction rolls back — the purchase is not created/updated/deleted and no stock is changed.

## 8. InventoryAvailabilityService behavior

- `getStatus()`: return every raw material classified by `stock_quantity` using a `LOW_STOCK_THRESHOLD = 10` constant:

  | Condition             | Status        |
  | --------------------- | ------------- |
  | stock_quantity <= 0   | out_of_stock  |
  | 0 < stock < 10        | low           |
  | stock >= 10           | available     |

Known limitation: the threshold is unit-insensitive (10 grams, 10 liters, and 10 pieces all classify the same). This is an accepted product decision.

## 9. Resources

`InventoryPurchaseResource`:

```json
{
  "id": 1,
  "raw_material_id": 1,
  "quantity": 500.00,
  "purchase_date": "2026-08-01 09:00:00",
  "raw_material": { "id": 1, "name": "Flour", "unit": "gram" },
  "created_at": "...",
  "updated_at": "..."
}
```

`InventoryAvailabilityResource`:

```json
{
  "id": 1,
  "name": "Flour",
  "unit": "gram",
  "stock_quantity": 500.00,
  "status": "available"
}
```

## 10. Error Handling

- 404: purchase or raw material not found (`findOrFail` → Laravel default handler).
- 422: validation failures (standard Laravel `{message, errors}` shape; the envelope is NOT asserted on 422).
- 422: negative stock guard (`ValidationException` thrown from the service).
- 500: unexpected exceptions — Laravel default handler.
- Deleting a raw material cascades to its `inventory_purchases` at the DB level — accepted.

## 11. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`), using `RefreshDatabase`.

`InventoryPurchaseFactory` definition: creates a `RawMaterial` on the fly, `quantity = 50`, `purchase_date = now()`.

Cover:

- **Request:** valid payload passes; `raw_material_id` required and must exist; `quantity` required and rejects 0/negative; `purchase_date` optional on POST (defaults to now) and rejects a present `null` on PUT; PUT partial (missing optional fields passes).
- **Service:**
  - create persists and increments stock; `purchase_date` defaults to `now()`.
  - update adjusts stock by the difference.
  - update changes `raw_material_id` and moves stock between materials (old -= , new +=).
  - update with the same `raw_material_id` does not double-adjust.
  - delete decrements stock.
  - negative guard: create/update/delete that would go below zero throws AND the purchase is not persisted AND stock is unchanged (asserts full transaction rollback).
  - stock values are clean 2-decimal after operations (rounding).
- **Resource:** shape assertions for both resources (including nested `raw_material` and status classification).
- **Endpoints:**
  - Purchase CRUD: GET list 200 (latest first, nested raw material present); POST creates 201 and increments stock; GET show 200; PUT updates 200 and adjusts stock; DELETE removes 200 and decrements stock; validation failure 422; nonexistent id 404; stock-would-go-negative 422.
  - Availability: 200 with every raw material classified; boundary values pinned (0 → out_of_stock, 9.99 → low, 10.00 → available, 10.01 → available).

Run with: `php artisan test --filter=Inventory` (or `composer test`), then the full suite and `vendor/bin/pint --test`.

## 12. Known Limitations (documented)

- **Historical purchase edits/deletes vs. BR-013:** reverse stock adjustment (update/delete) assumes no production consumption has occurred since the purchase was recorded. This is safe while the Production module does not exist. When the Production module introduces post-production stock consumption, this design must be revisited (e.g., tracking consumed quantities or restricting edits to unconsumed purchases).
- **Manual stock edits desync:** `PUT /raw-materials/{id}` can set `stock_quantity` directly, bypassing purchase history. Accepted as a deliberate owner-level manual adjustment; purchase-driven sync and manual edits can diverge.
- **Eager-loading requirement:** `InventoryPurchaseResource` requires the `rawMaterial` relation to be loaded; `getAll`/`getById` eager-load it, and tests exercise the endpoints to guard against N+1 regressions.

## 13. Constraints & Rules Followed

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Use DB transactions for all write operations.
- Acquire row locks before stock read-modify-write (concurrency safety).
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
- Validation error shape is the Laravel default `{message, errors}` (envelope NOT asserted on 422).
- No new dependencies.
