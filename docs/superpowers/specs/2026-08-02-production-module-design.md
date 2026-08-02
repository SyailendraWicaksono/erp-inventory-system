# Production Module — Design Specification

Date: 2026-08-02
Status: Approved for implementation

## 1. Overview

The Production module manages production scheduling and raw-material consumption for confirmed orders in the make-to-order ERP. It implements business rules BR-007 through BR-013:

- BR-007: production can only start after the order is confirmed.
- BR-008: schedules are arranged around the order's pickup time.
- BR-009: each order has only one active (non-finished) production schedule.
- BR-010: the order status changes to **finished** once production completes.
- BR-011: raw-material availability is checked before production starts.
- BR-012: when stock is insufficient, the owner purchases raw materials first (supported by the Inventory module).
- BR-013: inventory is updated after production completes.

The module builds on the Product, Recipe, Raw Material, and Inventory modules. It uses **only existing tables**; no migration changes.

## 2. Scope

### In scope

- Production schedule CRUD (`/api/production-schedules`) with the BR-009 single-active-schedule guard.
- Lifecycle transitions via `start` and `finish` (BR-007, BR-010, BR-011, BR-013).
- Order-status coupling: constants `confirmed` / `finished` defined on the `Order` model (shared with the future Order module).
- Raw-material requirement computation from recipes × order items (latest recipe per product).
- Availability check at `start()` (advisory) and authoritative never-negative stock consumption at `finish()`.
- `ProductionScheduleFactory` and feature tests (request, resource, service, endpoints).

### Out of scope

- The Order module (orders/order_items/customers CRUD, confirm/complete, customer management) — not yet built; only the status constants this module needs are added.
- The Payment module.
- Changes to the Product, Recipe, Raw Material, or Inventory modules. Cross-module risks are documented as known limitations (Section 12).
- Authentication / authorization middleware.
- Any migration changes. Existing migrations must NOT be modified.

## 3. Database

Migrations already exist and must NOT be modified. No new migrations, tables, columns, or renames.

### Tables (read/referenced)

**production_schedules**

| Column           | Type          | Notes                                  |
| ---------------- | ------------- | -------------------------------------- |
| id               | bigint PK     |                                        |
| order_id         | bigint FK     | → orders.id, cascade delete            |
| start_time       | timestamp     | nullable                               |
| end_time         | timestamp     | nullable                               |
| production_status| string        | non-nullable                           |
| created_at       | timestamp     |                                        |
| updated_at       | timestamp     |                                        |

**orders** (read by this module; structurally untouched)

| Column          | Type          | Notes                        |
| --------------- | ------------- | ---------------------------- |
| id              | bigint PK     |                              |
| customer_id     | bigint FK     | → customers.id, cascade delete |
| order_number    | string        | unique                       |
| pickup_datetime | timestamp     | non-nullable                 |
| order_status    | string        | non-nullable                 |
| total_price     | decimal(12,2) |                              |

**order_items** (read by this module)

| Column             | Type          | Notes                          |
| ------------------ | ------------- | ------------------------------ |
| id                 | bigint PK     |                                |
| order_id           | bigint FK     | → orders.id, cascade delete    |
| product_id         | bigint FK     | → products.id, cascade delete  |
| quantity           | integer       |                                |
| customization_note | text          | nullable                       |
| subtotal           | decimal(12,2) |                                |

**recipes / recipe_details / raw_materials** (read by this module)

- `recipes`: product_id → products.id, recipe_name (multiple recipes per product allowed).
- `recipe_details`: recipe_id → recipes.id, raw_material_id → raw_materials.id, quantity decimal(12,2).
- `raw_materials`: name, stock_quantity decimal(12,2) default 0, unit, expiration_date.

### Model changes (no fillable/relation changes, constants only)

- `Order`: add class constants `ORDER_STATUS_CONFIRMED = 'confirmed'`, `ORDER_STATUS_FINISHED = 'finished'`. Single source of truth; the future Order/Payment modules must reuse them.
- `ProductionSchedule`: add class constants `STATUS_SCHEDULED = 'scheduled'`, `STATUS_IN_PROGRESS = 'in_progress'`, `STATUS_FINISHED = 'finished'`.

### Existing code

- `Order`, `OrderItem`, `ProductionSchedule`, `Recipe`, `RecipeDetail`, `RawMaterial`, `Product`, `Customer` models already exist with fillables and relations. `ProductionSchedule` has `$fillable = [order_id, start_time, end_time, production_status]` and an `order()` belongsTo.
- No `OrderFactory` and no `ProductFactory` exist. Tests create those models directly (see Section 11), and factories create dependencies inline (per the existing `RecipeFactory` pattern).

## 4. Status Lifecycle

**Production schedule:** `scheduled → in_progress → finished`. Terminal = `finished`.

- POST creates a schedule with `production_status = scheduled`.
- `start()`: `scheduled → in_progress` (BR-007, BR-011).
- `finish()`: `in_progress → finished` (BR-010, BR-013).
- DELETE: only while `scheduled`.

**Order status coupling (BR-007 / BR-010):**

- `start()` requires the order's `order_status` to be exactly `confirmed`, else 422.
- `finish()` requires the order's `order_status` to be exactly `confirmed` (so `finished` orders cannot be finished again), then writes `finished`.
- Creating or reassigning a schedule requires the order to be `confirmed`, else 422 (a schedule for a non-confirmed order could never start).

## 5. API Endpoints

REST, flat (not nested). Base path `/api`. All 2xx responses use the `{success, message, data}` envelope; 404/422 use the standard Laravel shapes.

| Method | URI                              | Purpose                                        | Success |
| ------ | -------------------------------- | ---------------------------------------------- | ------- |
| GET    | /api/production-schedules        | List all schedules, latest first, order loaded | 200     |
| POST   | /api/production-schedules        | Create a schedule as `scheduled`               | 201     |
| GET    | /api/production-schedules/{id}   | Show one schedule                               | 200     |
| PUT    | /api/production-schedules/{id}   | Update times / order_id (guarded)               | 200     |
| DELETE | /api/production-schedules/{id}   | Delete a schedule (only while `scheduled`)      | 200     |
| PATCH  | /api/production-schedules/{id}/start  | Start production (check availability)      | 200     |
| PATCH  | /api/production-schedules/{id}/finish | Finish production (consume stock)          | 200     |

Route registration: `Route::apiResource('production-schedules', ProductionScheduleController::class)` plus explicit `start` / `finish` PATCH routes.

## 6. Validation Rules

`ProductionScheduleRequest` (POST uses `required`; PUT uses `sometimes` partial semantics, matching existing requests):

- `order_id`: required on POST, `sometimes` on PUT; `exists:orders,id`.
- `start_time`: `nullable` on POST, `sometimes` on PUT; `date`.
- `end_time`: `nullable` on POST, `sometimes` on PUT; `date`.
- `production_status`: never accepted — not in the request and not in `$fillable` (writes happen only via `start` / `finish`).

Cross-field and business-rule guards (start before pickup, end after start, confirmed-order, BR-009) live in the service and return 422, matching the Inventory module convention.

## 7. Architecture & Components

Follows the established module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `app/Http/Controllers/ProductionScheduleController.php` | Thin controller, DI of `ProductionScheduleService`, 7 actions (index, store, show, update, destroy, start, finish) |
| `app/Http/Requests/ProductionScheduleRequest.php` | Validation per Section 6 |
| `app/Services/ProductionScheduleService.php` | Schedule CRUD + requirements + availability + consumption (Sections 8-9) |
| `app/Http/Resources/ProductionScheduleResource.php` | Serializes a schedule + nested order |
| `database/factories/ProductionScheduleFactory.php` | Test factory per Section 11 |
| `tests/Feature/ProductionScheduleRequestTest.php` | Validation tests |
| `tests/Feature/ProductionScheduleResourceTest.php` | Resource shape tests |
| `tests/Feature/ProductionScheduleServiceTest.php` | Service logic + stock + guard tests |
| `tests/Feature/ProductionScheduleTest.php` | Schedule endpoint + lifecycle tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `app/Models/Order.php` | Add `ORDER_STATUS_CONFIRMED`, `ORDER_STATUS_FINISHED` constants |
| `app/Models/ProductionSchedule.php` | Add `STATUS_SCHEDULED`, `STATUS_IN_PROGRESS`, `STATUS_FINISHED` constants |
| `routes/api.php` | Register the production-schedules resource and start/finish routes |

## 8. Requirement Computation & Availability

### Requirement computation

For a given order, the required quantity of each raw material is the sum over order items of `item.quantity × recipe_detail.quantity`, using each item's product **latest recipe** (most recently created; `RecipeService::getAll` uses `latest()`), aggregated per `raw_material_id`. Each material total is rounded with `round(..., 2)` once.

- A product with **no recipe** → `ValidationException` (422) listing the product, from both `start()` and `finish()`.
- An order with **no items** → 422 ("nothing to produce"), from both `start()` and `finish()`.

### Availability check (BR-011)

`required[raw_material_id] > stock_quantity` for any material ⇒ 422 with a `{material, required, available, short_by}` detail per short material. The check runs on **rounded** requirement values.

- At `start()` the check is **advisory** (no reservation): stock is only read under lock, never mutated.
- At `finish()` the same check is the **authoritative never-negative guard**: no stock write lands until every guard passes (full transaction rollback on failure).

## 9. ProductionScheduleService behavior

**Lock-ordering invariant (deadlock-free within the module):** every operation that takes multiple locks acquires them in the order `schedule → order → materials (sorted by id)`. Materials are always locked sorted by id. Cross-module lock ordering with the Inventory module is a documented limitation (Section 12).

All write operations run inside a single `DB::transaction`. Every computed stock value is `round(..., 2)`.

- `getAll()`: `latest()->get()` with `order` eager-loaded.
- `getById(int $id)`: `findOrFail` with `order` eager-loaded.
- `create(array $data)`: transaction; `lockForUpdate` the **order row**; re-verify the order exists and `order_status === confirmed` (422); **BR-009**: reject if the order already has a non-`finished` schedule (checked after the lock); create as `scheduled`.
- `update(int $id, array $data)`: transaction; lock the **schedule row**; if `order_id` is present: require status `scheduled` (an already-started schedule cannot be moved to another order → 422), lock the target order row, require `confirmed` (422) and re-apply **BR-009**; time-only updates are allowed on `in_progress` schedules; `start_time` (when set) must be `< order.pickup_datetime` (422); when both set, `end_time` must be `> start_time` (422). `production_status` is never writable.
- `delete(int $id)`: transaction; lock the schedule row; only while `scheduled` (422 otherwise); delete.
- `start(int $id)`: transaction; **lock the schedule row**, then re-read and re-validate `production_status === scheduled` (prevents double-start); lock the order row, require `confirmed` (BR-007); load `order.items.product.recipes.recipeDetails`; 422 if the order has no items or any product has no recipe; compute requirements; **lock all affected `raw_materials` rows (sorted by id) BEFORE reading stock**; run the advisory availability check → 422 on shortage (rollback); set `start_time = now()` when null; set `production_status = in_progress`.
- `finish(int $id)`: transaction; **lock the schedule row**, re-validate `production_status === in_progress` (prevents double-finish / double deduction); lock the order row, require `confirmed`; load items/recipes/details; 422 if no items or missing recipe; compute requirements; **lock all affected `raw_materials` rows (sorted by id) BEFORE reading stock**; run the never-negative check → 422 on shortage (rollback, nothing written); for each material: `newStock = round(stock_quantity − required, 2)` and persist; set `end_time = now()` when null; set `production_status = finished`; set `order.order_status = finished` (BR-010). All in the same transaction.

### Never-negative guard

Any shortage at `finish()` throws a `ValidationException` (422) and the entire transaction rolls back — schedule status, order status, and all stock remain unchanged.

## 10. Resources & Error Handling

`ProductionScheduleResource`:

```json
{
  "id": 1,
  "order_id": 1,
  "start_time": "2026-08-02 09:00:00",
  "end_time": null,
  "production_status": "scheduled",
  "order": {
    "id": 1,
    "order_number": "ORD-0001",
    "pickup_datetime": "2026-08-02 12:00:00",
    "order_status": "confirmed"
  },
  "created_at": "...",
  "updated_at": "..."
}
```

- 404: schedule or order not found (`findOrFail` → Laravel default handler).
- 422: validation failures (standard `{message, errors}` shape; envelope NOT asserted on 422).
- 422: business guards from the service (`ValidationException`): order not confirmed, invalid status transition, BR-009 duplicate active schedule, zero-item order, missing recipe, shortage (with per-material details), time-ordering violations, delete of a non-scheduled schedule, `order_id` change after start.
- 500: unexpected exceptions — Laravel default handler.
- Cascade deletes (order → schedule, order → items, raw_material → recipe_details, product → recipes) occur at the DB level; their production-module consequences are documented in Section 12.

## 11. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`) using `RefreshDatabase`. Tests create models directly where no factory exists (per the existing `RecipeServiceTest` pattern), and the `ProductionScheduleFactory` creates its dependencies inline (per the `RecipeFactory` pattern): a `Customer`, then an `Order` (`order_number` unique, `pickup_datetime`, `order_status = confirmed`, `total_price = 0`), schedule `start_time`/`end_time` nullable, `production_status = scheduled`.

Cover:

- **Factory/model:** `ProductionScheduleFactory` creates a schedule with a confirmed order; `order()` relation returns the order; fillables writable.
- **Request:** `order_id` required on POST + `exists:orders,id`; `start_time`/`end_time` nullable on POST; PUT partial (missing optional fields passes); `production_status` rejected.
- **Service — create:** creates as `scheduled`; 422 when order not `confirmed`; 422 (BR-009) when the order already has a non-`finished` schedule; 404 when the order is missing.
- **Service — update:** updates times; 422 changing `order_id` once started; 422 (BR-009) on `order_id` change to an order with an active schedule; 422 `start_time` at/after `pickup_datetime`; 422 `end_time` before `start_time`.
- **Service — delete:** deletes while `scheduled`; 422 for `in_progress` and `finished` schedules.
- **Service — requirement computation:** aggregates across multiple order items and quantities; uses the **latest** recipe when a product has multiple recipes; rounds each material total to 2 decimals.
- **Service — start:** success (status → `in_progress`, `start_time` defaulted when null); 422 on shortage (rollback: schedule status and stock unchanged); 422 zero-item order; 422 product without recipe; 422 order not `confirmed`; 422 not `scheduled`; double-start sequential → 422.
- **Service — finish:** success (stock deducted per material, schedule → `finished`, order → `finished`, `end_time` defaulted); 422 on shortage with **full rollback** (stock, schedule status, and order status unchanged); rounding; sequential double-finish → 422; 422 not `in_progress`; 422 order already `finished`.
- **Service — concurrency (best-effort on SQLite):** two schedules finishing on the same material serialize; second one 422s when stock is exhausted.
- **Endpoints (HTTP through routes, `{success, message, data}` envelope asserted on 2xx):**
  - GET list 200 (latest first, nested `order`); POST 201 (creates `scheduled`); POST 422 (BR-009, order not confirmed); GET show 200; PUT 200; DELETE 200 (scheduled) and 422 (in_progress); PATCH start 200 and 422 (shortage, not confirmed); PATCH finish 200 (stock deducted, order `finished`) and 422 (shortage, double-finish); 404 for missing ids; validation failure 422.
  - Cascade: deleting an order removes its schedules (documented consequence).
- **Known test limitation:** `lockForUpdate()` is a no-op under SQLite; lock/race behavior is reasoned through and validated only on PostgreSQL.

Run with `php artisan test --filter=ProductionSchedule` (or `composer test`), then the full suite and `vendor/bin/pint --test`.

## 12. Known Limitations (documented)

1. **Latest-recipe snapshot:** `production_schedules` has no `recipe_id` column, so requirements are computed from each product's latest recipe at both `start()` and `finish()`. Future schema revisions may introduce explicit recipe versioning.
2. **Recipe module is unchanged:** recipes and recipe details can be edited or deleted while a schedule is `in_progress`. An edit silently changes the finish-time requirement; a deletion makes `finish()` 422 after goods are produced, stranding the schedule in `in_progress` (mitigation: the never-negative guard plus operator re-purchase/re-schedule). An immutability guard in `RecipeService` would eliminate this but is out of scope.
3. **Inventory module is unchanged:** `InventoryPurchaseService::update` locks material rows unsorted (old-then-new) while Production locks them sorted by id; a cross-module deadlock cycle is theoretically possible. PostgreSQL's deadlock detector aborts one request with a 500 (the client retries). Fixing requires modifying the Inventory module (out of scope).
4. **Raw Material module is unchanged:** deleting a raw material referenced by an in-production product cascades away its `recipe_details`, silently removing the requirement, so `finish()` under-deducts already-consumed stock. Documented risk; out of scope.
5. **Order module not built:** deleting an order cascades its schedule and items (no guard); the BR-005 contract (order items immutable once production starts) is a responsibility of the future Order module. The `confirmed` / `finished` constants defined here are the shared contract that Order/Payment must reuse.
6. **Advisory availability check:** stock is not reserved at `start()`; two orders can both pass `start()` on the same stock. An at-`finish()` 422 is a designed re-order trigger per BR-012, not a bug.
7. **SQLite lock no-op:** `lockForUpdate()` is a no-op on the SQLite test DB; locking correctness is validated only on PostgreSQL. Concurrency tests are best-effort.
8. **Customization notes:** `order_items.customization_note` is free text and is not factored into raw-material consumption; a customized product consumes the standard recipe quantities.

## 13. Constraints & Rules Followed

- Do not modify migrations.
- Do not rename tables.
- Do not rename columns.
- Keep controllers thin (no business logic in controllers).
- Do not modify the Product, Recipe, Raw Material, or Inventory modules.
- Use DB transactions for all write operations.
- Acquire row locks before any stock read-modify-write, in the order `schedule → order → materials (sorted by id)`.
- Re-validate row status after acquiring a row lock (double-start / double-finish safety).
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
- Validation error shape is the Laravel default `{message, errors}` (envelope NOT asserted on 422).
- No new dependencies.
