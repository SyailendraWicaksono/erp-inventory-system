# Payment Module — Design Specification

Date: 2026-08-04
Status: Approved for implementation

## 1. Overview

The Payment module records the settlement of guest orders for the make-to-order ERP. It implements business rules BR-014 through BR-016:

- BR-014: the payment method follows the agreement between customer and owner (e.g. down payment before production, or payment after delivery).
- BR-015: the payment status is recorded for every order.
- BR-016: the order becomes **completed** only after production is finished and the payment has been recorded (verified).

The module builds on the Order module (which is complete: orders, `pending → confirmed` confirmation, `finished` written by Production). This module delivers the final order-lifecycle transition `finished → completed` (BR-016), which the Order module spec explicitly deferred here: `Order::ORDER_STATUS_COMPLETED` already ships but is currently unused.

The module uses a **single payment record per order** with a `recorded → paid` lifecycle. There is **no migration**: the existing `payments` table fully covers the schema. **Verification is the only path from `finished` to `completed`.**

## 2. Scope

### In scope

- Payment CRUD (`/api/payments`) — create, list, show, full-replace update, delete.
- Payment verify (`PATCH /api/payments/{id}/verify`) — `recorded → paid`, and when the order is `finished`, `finished → completed` (BR-016).
- Payment status constants `recorded`, `paid`.
- Single full-settlement payment per order; `payment_amount` must equal `orders.total_price`.
- Owner checkpoint via the verify endpoint.
- Feature tests (request, resource, service, endpoints).
- Route registration and documentation close-out.

### Out of scope

- Multi-installment / partial (DP + remainder) payment splits: the DP agreement is captured as a `payment_method` value, not as multiple payment rows.
- The `GET /dashboard/payments` dashboard endpoint (Dashboard module).
- Authentication / authorization middleware.
- Customer CRUD endpoints.
- Changes to the Order, Production, Inventory, Recipe, Raw Material, or Product modules. In particular, `ProductionScheduleService::finish()` is NOT modified to trigger completion.
- Pagination.
- Any migration (the existing `payments` table is used as-is; existing migrations are never modified).

## 3. Database

No new migrations. The existing `payments` table (migration `2026_08_01_072513_create_payments_table`) is used unchanged:

| Column          | Type          | Notes                          |
| --------------- | ------------- | ------------------------------ |
| id              | bigint PK     |                                |
| order_id        | bigint FK     | → orders.id, cascade delete    |
| payment_method  | string        | non-nullable (BR-014)          |
| payment_status  | string        | non-nullable                   |
| payment_amount  | decimal(12,2) | non-nullable                   |
| payment_date    | timestamp     | nullable                       |
| created_at      | timestamp     |                                |
| updated_at      | timestamp     |                                |

One payment row per order is enforced by the service (mirrors the "one active schedule" guard in Production); no unique constraint is added.

### Model changes

- `Payment`: add `PAYMENT_STATUS_RECORDED = 'recorded'` and `PAYMENT_STATUS_PAID = 'paid'` constants.
- `Order`: no change needed — `ORDER_STATUS_COMPLETED` already exists and `payment()` (`hasOne`) is already defined.

## 4. Status Lifecycle

Payment status: `recorded → paid`.

Order status: `pending → confirmed → finished → completed`.

- `POST /payments` creates the payment with `payment_status = recorded`.
- `PATCH /payments/{id}/verify` transitions `recorded → paid` and, because the order must already be `finished` to verify, transitions the order `finished → completed` in the same transaction (BR-016).
- Verification is the **only** path from `finished` to `completed`. Neither Order nor Production writes `completed`.

## 5. API Endpoints

REST, flat (not nested). Base path `/api`. All 2xx responses use the `{success, message, data}` envelope; 404/422 use the standard Laravel shapes.

| Method | URI                      | Purpose                                            | Success |
| ------ | ------------------------ | -------------------------------------------------- | ------- |
| GET    | /api/payments            | List payments, latest first, eager-loaded order    | 200     |
| POST   | /api/payments            | Record a payment as `recorded`                     | 201     |
| GET    | /api/payments/{id}       | Show one payment                                   | 200     |
| PUT    | /api/payments/{id}       | Full-replace update (only while `recorded`)        | 200     |
| DELETE | /api/payments/{id}       | Delete (only while `recorded`)                     | 200     |
| PATCH  | /api/payments/{id}/verify| `recorded → paid`; order `finished → completed`    | 200     |

Route registration: `Route::apiResource('payments', PaymentController::class)` plus an explicit `PATCH payments/{payment}/verify` route. Existing routes remain unchanged.

## 6. Request & Validation (`PaymentRequest`)

POST uses `required`; PUT uses `sometimes`.

**POST /payments:**

- `order_id`: required integer, `exists:orders,id`.
- `payment_method`: required string.
- `payment_amount`: required numeric, `gt:0`.
- `payment_date`: nullable date.

**PUT /payments/{id}:** same fields with `sometimes` for the outer fields; `order_id` is immutable after create and is not updated (an update payload may omit it).

**Business-rule guards (in the service, returning 422):**

- create: order must be `confirmed` or `finished` (reject `pending` and `completed`); no payment already exists for the order; `payment_amount` must equal `orders.total_price`.
- update: only while `recorded`; revalidate `payment_amount` equals the order total.
- delete: only while `recorded`.
- verify: only `recorded → paid` (reject duplicate verify); order must be `finished`; revalidate `payment_amount` equals the order total.

## 7. Architecture & Components

Follows the established module pattern exactly:

```
Controller → Request → Service → Model → Resource
```

### Files to create

| File | Responsibility |
| ---- | -------------- |
| `app/Http/Controllers/PaymentController.php` | Thin controller: index, store, show, update, destroy, verify |
| `app/Http/Requests/PaymentRequest.php` | Validation per Section 6 |
| `app/Services/PaymentService.php` | Payment CRUD, verify lifecycle, order completion |
| `app/Http/Resources/PaymentResource.php` | Serializes payment + nested order |
| `database/factories/PaymentFactory.php` | Test factory creating an order inline + payment |
| `tests/Feature/PaymentRequestTest.php` | Validation tests |
| `tests/Feature/PaymentResourceTest.php` | Resource shape tests |
| `tests/Feature/PaymentServiceTest.php` | Service logic tests |
| `tests/Feature/PaymentTest.php` | Payment endpoint + lifecycle tests |

### Files to modify

| File | Change |
| ---- | ------ |
| `app/Models/Payment.php` | Add `PAYMENT_STATUS_RECORDED`, `PAYMENT_STATUS_PAID` constants |
| `routes/api.php` | Register the payments resource and verify route |

## 8. PaymentService behavior

All write operations run inside a single `DB::transaction`. Monetary values are compared and stored with `round(..., 2)`.

**Lock-ordering invariant (deadlock-free within the module):** `order → payment`. The order row is locked before the payment row. No other module's lock ordering is affected.

- `getAll()`: `latest('id')->get()` with `order` eager-loaded.
- `getById(int $id)`: `findOrFail` with the `order` eager load.
- `create(array $data)`: locks the order; asserts order status is `confirmed` or `finished` (rejects `pending`/`completed`); asserts no payment exists for the order; asserts `payment_amount` equals `orders.total_price`; defaults `payment_date` to `now()` when absent; creates the payment with `payment_status = recorded`.
- `update(int $id, array $data)`: `recorded` only (422 otherwise); locks the payment then the order; revalidates `payment_amount` equals the order total; updates editable fields (`payment_method`, `payment_amount`, `payment_date`); `order_id` is never updated.
- `delete(int $id)`: `recorded` only (422 otherwise); deletes the payment (order untouched).
- `verify(int $id)`: `recorded` only (reject duplicate verify → 422); locks the payment and the order; requires the order to be `finished`; revalidates `payment_amount` equals the order total; sets `payment_status = paid` and `payment_date` (defaults to `now()` when absent); sets the order `order_status = completed` (BR-016).

### Guard summary (single transaction each, `ValidationException` → 422)

| Operation | Guard |
| --------- | ----- |
| create    | order `confirmed`/`finished`; no existing payment; amount == total |
| update    | `recorded` only; amount == total |
| delete    | `recorded` only |
| verify    | `recorded` only (no double verify); order `finished`; amount == total |

## 9. Resources & Error Handling

`PaymentResource` (mirrors `ProductionScheduleResource` — payment with a nested order):

```json
{
  "id": 1,
  "order_id": 3,
  "payment_method": "cash",
  "payment_status": "paid",
  "payment_amount": "100000.00",
  "payment_date": "2026-08-05 10:00:00",
  "order": {
    "id": 3,
    "order_number": "ORD-20260805-000001",
    "pickup_datetime": "2026-08-05 10:00:00",
    "order_status": "completed",
    "total_price": "100000.00"
  },
  "created_at": "...",
  "updated_at": "..."
}
```

- 404: payment not found (`findOrFail` → Laravel default handler).
- 422: validation failures (standard `{message, errors}` shape; envelope NOT asserted on 422).
- 422: business guards from the service (`ValidationException`): order not `confirmed`/`finished`, existing payment, duplicate verify, order not `finished`, `payment_amount` mismatch.
- 500: unexpected exceptions — Laravel default handler.

## 10. Testing

Feature tests against in-memory SQLite (per `phpunit.xml`) using `RefreshDatabase`. Tests create models directly where there is no factory, and the `PaymentFactory` creates its dependencies inline (per the `OrderFactory`/`ProductionScheduleFactory` pattern): a `Customer` via `CustomerFactory`, an `Order` (confirmed), and the payment with a real method/status/amount/date.

Cover:

- **Factory/model:** `PaymentFactory` creates a payment with required fields; `belongsTo(Order)`; `Payment` status constants.
- **Request:** `order_id` required + exists; `payment_method` required; `payment_amount` required `gt:0`; `payment_date` nullable; PUT uses `sometimes`.
- **Service — create:** creates payment as `recorded`; defaults `payment_date`; rejects `pending` and `completed` orders; rejects a second payment for the same order; rejects amount != total.
- **Service — update:** updates while `recorded`; rejects non-`recorded`; revalidates amount.
- **Service — delete:** deletes while `recorded`; rejects non-`recorded`.
- **Service — verify:** `recorded → paid` and order `finished → completed`; rejects duplicate verify; rejects order not `finished`; revalidates amount.
- **Endpoints (HTTP through routes, envelope asserted on 2xx):** GET list 200 (latest first, nested order); POST 201; PUT 200 (recorded) and 422 (non-recorded); DELETE 200 (recorded) and 422 (non-recorded); PATCH verify 200 (payment paid + order completed); 404 missing; 422 validation.
- **Cross-module contract:** a verified payment completes a finished order; a completed order rejects a new payment (422).
- **Known test limitation:** `lockForUpdate()` is a no-op under SQLite; lock/race behavior is reasoned through and validated only on PostgreSQL (as in Production and Order).

Run with `php artisan test --filter=Payment` then the full suite and `vendor/bin/pint --test`.

## 11. Known Limitations (documented)

1. **No installments:** a down payment and remainder cannot be split across rows; the DP agreement is represented as a `payment_method` value. Payment amount always equals the order total.
2. **Verify requires finished:** a payment can be *recorded* while the order is `confirmed` (capturing an early DP), but *verified* only once production has finished. This keeps `finished → completed` atomic with verification and avoids a stuck order (verified payment, later-finished order) without touching the Production module.
3. **One payment per order** is enforced by the service, not by a unique constraint.
4. **No pagination** (consistent with existing modules).
5. **No auth** (all modules unauthenticated so far).
6. **payment_method** is free-form text (BR-014 agreement), not an enum.

## 12. Constraints & Rules Followed

- No migrations (existing `payments` table used as-is; existing migrations never modified).
- Do not rename tables or columns.
- Keep controllers thin (no business logic in controllers).
- Do not modify the Order, Product, Recipe, Raw Material, Inventory, or Production modules. Verification is the only `finished → completed` path.
- Use DB transactions for all write operations.
- Use Form Requests, API Resources, Service classes, dependency injection.
- PSR-12, PHP 8.3.
- Response envelope `{success, message, data}`.
- Validation error shape is the Laravel default `{message, errors}`.
- No new third-party dependencies.
