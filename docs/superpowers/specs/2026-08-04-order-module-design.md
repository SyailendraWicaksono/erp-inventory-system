# Order Module — Design Specification

Date: 2026-08-04
Status: Approved for implementation

## 1. Overview

The Order module manages guest orders for the make-to-order ERP: creating orders from guest ordering data, listing them, full-replace updates (pending only), deletion (pending only), and owner confirmation. It implements business rules BR-001 through BR-006:

- BR-001: a customer orders without registering an account (guest ordering).
- BR-002: the customer must provide name and phone number before the order is sent.
- BR-003: every order must have at least one product.
- BR-004: every order must have a pickup date/time.
- BR-005: changes to an order are only allowed before production starts.
- BR-006: the order must be confirmed by the owner before further processing.

The module builds on the Customer, Product, and Production modules. It introduces **two new migrations** (no modification of existing migrations, no renames): an `order_items.unit_price` column and an `order_number_sequences` table.

## 2. Scope

### In scope

- Order CRUD (`/api/orders`) — create, list, show, full-replace update, delete.
- Order confirm (`PATCH /api/orders/{id}/confirm`), the final checkpoint before the Production module becomes involved.
- Order status constants `pending`, `confirmed`, `finished`, `completed` (shared contract for Order, Production, Payment).
- Order-number generation `ORD-YYYYMMDD-XXXXXX` from a per-day sequence table.
- Server-authoritative pricing via `order_items.unit_price`.
- Find-or-create customer by phone number.
- Feature tests (request, resource, service, endpoints).
- Route registration and documentation close-out.

### Out of scope

- The `pending → confirmed` completed transition is the starting point here; the `finished → completed` transition (BR-016, `PATCH /orders/{id}/complete`) is deferred to the Payment module. The `ORDER_STATUS_COMPLETED` constant ships now; the endpoint ships later.
- Authentication / authorization middleware.
- Customer CRUD endpoints (customers are only created/looked up inside the order flow).
- Changes to the Product, Recipe, Raw Material, Inventory, or Production modules.
- Pagination.
- Modifying existing migrations: the two required schema additions are **new** migrations only.

## 3. Database

Two new migrations are added. Existing migrations are NOT modified. No renames.

### New migration 1: add `order_items.unit_price`

```php
Schema::table('order_items', function (Blueprint $table) {
    $table->decimal('unit_price', 12, 2)->after('quantity')->default(0);
});
```

- `unit_price` is the agreed price per unit (owner–customer negotiation). Client may supply it on create/update; when absent it defaults to `product.base_price`.
- `default(0)` on the column makes the ALTER TABLE safe for any existing rows; the service always sets a real value, and `confirm` rejects `unit_price <= 0`.
- Reversible (drop column on down).

### New migration `order_number_sequences`

```php
Schema::create('order_number_sequences', function (Blueprint $table) {
    $table->date('sequence_date')->primary();
    $table->unsignedBigInteger('last_value')->default(0);
    $table->timestamps();
});
```

One row per calendar date; `last_value` is the count of orders on that date. Used to build the suffix of `ORD-YYYYMMDD-XXXXXX`.

### Existing tables (structurally untouched)

**orders**

| Column            | Type          | Notes                                   |
| ----------------- | ------------- | --------------------------------------- |
| id                | bigint PK     |                                         |
| customer_id       | bigint FK     | → customers.id, cascade delete          |
| order_number      | string        | unique                                  |
| pickup_datetime   | timestamp     | non-nullable                            |
| order_status      | string        | non-nullable                            |
| total_price       | decimal(12,2) |                                         |

**order_items**

| Column             | Type          | Notes                           |
| ------------------ | ------------- | ------------------------------- |
| id                 | bigint PK     |                                 |
| order_id           | bigint FK     | → orders.id, cascade delete     |
| product_id         | bigint FK     | → products.id, cascade delete   |
| quantity           | integer       |                                 |
| customization_note | text          | nullable                        |
| subtotal           | decimal(12,2) |                                 |
| unit_price         | decimal(12,2) | new column, default 0           |

**customers** — `id`, `name`, `phone_number` (unique), timestamps.

**products** — `id`, `name`, `description` (nullable), `base_price` decimal(12,2), `is_active` boolean default true, timestamps.

### Model changes

- `Order`: add `ORDER_STATUS_PENDING = 'pending'`, `ORDER_STATUS_COMPLETED = 'completed'` (constants `CONFIRMED`, `FINISHED` already exist). Add an `order_number_sequences` note: `order_number` is immutable after creation (never written after create).
- `OrderItem`: add `unit_price` to `$fillable`.
- New model `OrderNumberSequence` (sequence_date, last_value).

## 4. Status Lifecycle

`pending → confirmed → finished → completed`

- `POST /orders` creates the order with `order_status = pending`.
- `PATCH /orders/{id}/confirm` transitions `pending → confirmed` (BR-006) — the final checkpoint.
- `finished` is written by the Production module (`finish()`), already implemented, using `Order::ORDER_STATUS_FINISHED`.
- `completed` is written by the future Payment module (BR-016). Constant exists; endpoint deferred.

## 5. API Endpoints

REST, flat (not nested). Base path `/api`. All 2xx responses use the `{success, message, data}` envelope; 404/422 use the standard Laravel shapes.

| Method | URI                        | Purpose                                    | Success |
| ------ | -------------------------- | ------------------------------------------ | ------- |
| GET    | /api/orders                | List orders, latest first, eager-loaded     | 200     |
| POST   | /api/orders                | Create an order as `pending`                | 201     |
| GET    | /api/orders/{id}           | Show one order                               | 200     |
| PUT    | /api/orders/{id}           | Full-replace (only while `pending`)         | 200     |
| DELETE | /api/orders/{id}           | Delete (only while `pending`)               | 200     |
| PATCH  | /api/orders/{id}/confirm   | `pending → confirmed` (final checkpoint)    | 200     |

Route registration: `Route::apiResource('orders', OrderController::class)` plus an explicit `PATCH orders/{order}/confirm` route. Existing `production-schedules`, `products`, `raw-materials`, `inventory-purchases` routes remain unchanged.

## 6. Request & Validation (`OrderRequest`)

POST uses `required`; PUT uses `sometimes` (partial payload but full-replace item semantics). `production_status`/`order_status` are never accepted.

**POST /orders / PUT /orders/{id}:**

- `customer_name`: required string.
- `phone_number`: required string.
- `pickup_datetime`: required date.
- `items`: required array, min:1 (BR-003).
  - `items.*.product_id`: required integer, `exists:products,id`.
  - `items.*.quantity`: required integer, min:1.
  - `items.*.customization_note`: nullable string.
  - `items.*.unit_price`: nullable numeric, min:0 (defaults to `product.base_price`).

**PUT /orders/{id}** uses `sometimes` for the outer fields (a caller may send only items, or only pickup time), but item semantics remain full-replace: when `items` is present the existing items are deleted and recreated.

**Business-rule guards (in the service, returning 422):**

- pickup_datetime must be in the future (create, update, confirm).
- update only while `pending`; delete only while `pending`.
- confirm only on a `pending` order (rejects duplicate confirm).
- ≥1 item, and every product `is_active` (orderable) at confirm.

## 7. Architecture & Components

Follows the established module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `database/migrations/<ts>_add_unit_price_to_order_items_table.php` | Add `order_items.unit_price` column |
| `database/migrations/<ts>_create_order_number_sequences_table.php` | Create `order_number_sequences` |
| `app/Http/Controllers/OrderController.php` | Thin controller: index, store, show, update, destroy, confirm |
| `app/Http/Requests/OrderRequest.php` | Validation per Section 6 |
| `app/Services/OrderService.php` | Order CRUD, order-number generation, customer find-or-create, pricing, confirm |
| `app/Http/Resources/OrderResource.php` | Serializes order + customer + items + nested products + production_schedule |
| `database/factories/OrderFactory.php` | Test factory creating Customer + order inline |
| `tests/Feature/OrderRequestTest.php` | Validation tests |
| `tests/Feature/OrderResourceTest.php` | Resource shape tests |
| `tests/Feature/OrderServiceTest.php` | Service logic tests |
| `tests/Feature/OrderTest.php` | Order endpoint + lifecycle tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `app/Models/Order.php` | Add `ORDER_STATUS_PENDING`, `ORDER_STATUS_COMPLETED` constants |
| `app/Models/OrderItem.php` | Add `unit_price` to `$fillable` |
| `routes/api.php` | Register the orders resource and confirm route |

## 8. Order Number Generation

`ORD-YYYYMMDD-XXXXXX` where `XXXXXX` is a zero-padded 6-digit per-day sequence.

Generation logic isolates PostgreSQL vs SQLite differences via a dedicated repository/service method that:
- computes today's `sequence_date` (application timezone);
- on PostgreSQL: `upsert` the sequence row and `returning` the incremented value atomically (`INSERT … ON CONFLICT (sequence_date) DO UPDATE SET last_value = order_number_sequences.last_value + 1 RETURNING last_value`);
- on SQLite (`DB::getDriverName()`): the driver lacks the portable upsert-with-conflict-returning form used by the PostgreSQL path; instead it executes the same upsert via a separate path that reads the current row (or seeds a new one) and updates last_value, so the test suite under SQLite still produces sequential, deterministic suffixes.

The method returns the numeric `last_value` and the service builds the string `sprintf('ORD-%s-%06d', $date, $value)`.

The suffix counter is per calendar date, so suffixes reset daily.

## 9. Customer resolution

`POST /orders` / `PUT /orders/{id}` accept `customer_name` + `phone_number` (guest ordering). The system finds or creates the customer by `phone_number` (unique). On a concurrent-insert collision for a brand-new phone (race on the unique constraint), the service catches the unique-violation, re-fetches the existing customer, and proceeds (best-effort under SQLite; see Section 14).

## 10. Pricing (server-authoritative)

- `order_items.unit_price` holds the agreed unit price; client `unit_price` overrides it, else `product.base_price`.
- `subtotal = round(unit_price * quantity, 2)`; `orders.total_price = round(Σ subtotals, 2)`.
- Computing happens in the service; client never sets `subtotal`/`total_price`.
- `confirm` revalidates the final price: every item `unit_price > 0` and `quantity > 0`, recomputes subtotals and total, and persists them inside the confirm transaction.

## 11. OrderService behavior

**Lock-ordering invariant (deadlock-free within the module):** `customer → order → products (sorted by id)`. Sequence row upserted inside the create transaction. Cross-module lock ordering not affected (Production/Inventory untouched).

All write operations run inside a single `DB::transaction`. Every computed monetary value is `round(..., 2)`.

- `getAll()`: `latest('id')->get()` with `customer`, `items.product`, `productionSchedule` eager-loaded.
- `getById(int $id)`: `findOrFail` with the same eager loads.
- `create(array $data)`: generates `order_number` (Section 8), find-or-creates the customer, creates the order (`order_status = pending`), creates all items (unit_price defaults to product.base_price), computes subtotals + total.
- `update(int $id, array $data)`: `pending` only (422 otherwise); locates the order, resolves the customer (find-or-create on phone change), deletes existing items, recreates from `items[]`, recomputes prices, updates `pickup_datetime`.
- `delete(int $id)`: `pending` only (422 otherwise); deletes the order (cascade removes items + schedules).
- `confirm(int $id)`: `pending` only (reject duplicate confirm → 422); locks the order row; requires ≥1 item (BR-003); locks all affected product rows (sorted by id) and requires each `is_active`; pickup in future; revalidates unit_price > 0 for all items; recomputes subtotals + total; sets `order_status = confirmed`.

### Guard summary (single transaction each, `ValidationException` → 422)

| Operation | Guard |
| --------- | ----- |
| create | pickup in future; ≥1 item; products exist |
| update | only while `pending`; pickup in future |
| delete | only while `pending` |
| confirm | only `pending → confirmed`; ≥1 item (BR-003); products active; pickup in future; unit_price > 0 |

## 12. Resources & Error Handling

`OrderResource` (note: `production_schedule` is nullable when the order has no schedule):

```json
{
  "id": 1,
  "order_number": "ORD-20260804-000001",
  "pickup_datetime": "2026-08-04 10:00:00",
  "order_status": "pending",
  "total_price": "100000.00",
  "customer": { "id": 1, "name": "Budi", "phone_number": "081234567890" },
  "items": [
    {
      "id": 1,
      "product_id": 3,
      "quantity": 2,
      "customization_note": null,
      "unit_price": "50000.00",
      "subtotal": "100000.00",
      "product": { "id": 3, "name": "Chocolate Cake", "base_price": "50000.00", "is_active": true }
    }
  ],
  "production_schedule": { "id": 1, "production_status": "scheduled", "start_time": null, "end_time": null },
  "created_at": "...",
  "updated_at": "..."
}
```

- 404: order not found (`findOrFail` → Laravel default handler).
- 422: validation failures (standard `{message, errors}` shape; envelope NOT asserted on 422).
- 422: business guards from the service (`ValidationException`): order not pending (update/delete/confirm), duplicate confirm, zero-item order, inactive product, pickup not in future, `unit_price <= 0`.
- 500: unexpected exceptions — Laravel default handler.

## 13. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`) using `RefreshDatabase`. Tests create models directly where there is no factory, and the `OrderFactory` creates its dependencies inline (per the `RecipeFactory` pattern): `units` CustomerFactory → order with a real `order_number`, `pickup_datetime`, `order_status = pending`, `total_price = 0`.

Cover:

- **Factory/model:** `OrderFactory` creates an order with required fields; `customer()`/`items()`/`productionSchedule()` relations; `OrderItem.unit_price` fillable.
- **Request:** customer_name/phone_number/pickup_datetime required; items array; items.*.product_id exists; quantity min:1; unit_price nullable numeric; PUT uses `sometimes`.
- **Service — create:** creates order as `pending`, order_number format `ORD-YYYYMMDD-XXXXXX` unique, customer find-or-create (existing phone reused, new phone created), unit_price defaulting to base_price, subtotal/total computed.
- **Service — update:** full-replace (items recreated); rejects non-`pending`; recomputes prices; changing phone re-resolves customer.
- **Service — delete:** deletes while `pending`; rejects non-`pending`.
- **Service — confirm:** pending → confirmed; rejects duplicate confirm; requires ≥1 item; rejects inactive product; validates unit_price/quantities; recomputes subtotal/total.
- **Endpoints (HTTP through routes, envelope asserted on 2xx):** GET list 200 (latest first, nested customer/items/schedule); POST 201; PUT 200 (pending); PUT 422 (non-pending); DELETE 200 (pending) and 422 (non-pending); PATCH confirm 200; 404 missing; 422 validation.
- **Known test limitation:** `lockForUpdate()` is a no-op under SQLite; lock/race behavior is reasoned through and validated only on PostgreSQL (as in Production).

Run with `php artisan test --filter=Order` then the full suite and `vendor/bin/pint --test`.

## 14. Known Limitations (documented)

1. **Completed deferred:** the `finished → completed` transition (BR-016) ships in the Payment module; `Order::ORDER_STATUS_COMPLETED` is defined now but unused.
2. **Concurrent first-order race:** find-or-create-by-phone on a brand-new phone has a narrow race on the unique constraint; under SQLite the test path is best-effort and re-fetches on the collision.
3. **Sequence portability:** the order-number upsert uses a PostgreSQL-specific `INSERT … ON CONFLICT … RETURNING` path and a separate SQLite path; both must be tested.
4. **No pagination** (consistent with existing modules).
5. **No auth** (all modules unauthenticated so far).
6. **No product_customizations pricing effect:** `product_customizations`/`customization_note` do not alter unit_price or subtotal (customization is free text).

## 15. Constraints & Rules Followed

- Do not modify existing migrations; add only the two new migrations.
- Do not rename tables or columns.
- Keep controllers thin (no business logic in controllers).
- Do not modify the Product, Recipe, Raw Material, Inventory, or Production modules.
- Use DB transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
- Validation error shape is the Laravel default `{message, errors}`.
- No new third-party dependencies.