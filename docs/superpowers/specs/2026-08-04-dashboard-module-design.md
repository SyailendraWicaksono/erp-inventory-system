# Dashboard Module — Design Specification

**Date:** 2026-08-04
**Status:** Approved for implementation

## 1. Overview

The Dashboard module gives the owner a **daily operational summary** of the business across all completed modules: orders, production, inventory, and payments. It is the declared next module (per `docs/context.md`), satisfies requirements **FR-024 → FR-027**, and returns summary counts plus the actionable short lists behind them. The Payment module design explicitly deferred `GET /dashboard/payments` to this module.

It builds on the Order, Production, Inventory, and Payment modules. **No new tables, migrations, or dependencies.** All metrics are aggregates over the existing `orders`, `order_items`, `production_schedules`, `raw_materials`, and `payments` tables.

Reporting / Data Export / advanced analytics are **Phase 2** (per `docs/01_ProjectVision.md`) and are out of scope here.

Key decision points:
- **Time scope:** KPIs are "today" scoped except inventory (always live/current) and the outstanding-payments total (all-time unpaid, live).
- **Production "today"** is anchored to the order's `pickup_datetime` (not the schedule's `start_time`), because production is planned around pickup time (BR-008) and `pickup_datetime` is non-nullable and reliable.
- **Payment revenue** is attributed by the **paid-on date** (`updated_at` — the only moment a payment can become `paid` is `verify`, and only `verify` writes that transition), so "today's paid total" reflects payments verified today.

Business rules surfaced: BR-007/BR-010 (production statuses), BR-011/BR-013 (inventory availability), BR-015/BR-016 (payment recorded / order completed).

## 2. Scope

### In scope
- Five read-only aggregate endpoints (below).
- Daily operational summaries for orders, production, inventory, payments.
- Reuse of the existing `InventoryAvailabilityService` low/out-of-stock classification.

### Out of scope
- Authentication/authorization (endpoints remain unauthenticated, as all current endpoints are).
- Multi-day reporting, trends, charts, date-range filters, exporting (Phase 2).
- Any new database tables or migrations.
- A dashboard UI (backend API only, mirrors all prior modules).

## 3. Database

No changes. No new tables, columns, or migrations. Metrics are computed via `count()` / `sum()` aggregations over existing tables and the computed availability status from `InventoryAvailabilityService`.

## 4. API Endpoints

All routes are `GET`, unauthenticated, following the existing convention. Response envelope on success: `{ "success": true, "message": string, "data": object }`.

| Method | URI | Purpose | Success code |
|---|---|---|---|
| GET | `/api/dashboard` | Aggregate overview of all four sections | 200 |
| GET | `/api/dashboard/orders` | Today's order summary | 200 |
| GET | `/api/dashboard/production` | Today's production summary | 200 |
| GET | `/api/dashboard/inventory` | Live inventory availability summary | 200 |
| GET | `/api/dashboard/payments` | Today's payment summary + outstanding | 200 |

No request input (no Form Request, no validation). Only failure mode is an unexpected DB error → 500 via existing exception handling.

## 5. Response Payloads

### 5.1 `/api/dashboard/orders` — date basis `orders.created_at` is today

```json
{
  "success": true,
  "message": "Orders summary retrieved successfully",
  "data": {
    "total_today": 12,
    "by_status": { "pending": 2, "confirmed": 3, "finished": 1, "completed": 6 },
    "active_orders": [
      {
        "id": 5,
        "order_number": "ORD-20260804-000005",
        "pickup_datetime": "2026-08-04 15:00:00",
        "order_status": "confirmed",
        "total_price": 150000,
        "customer": { "name": "Budi" }
      }
    ]
  }
}
```

- `total_today` = count of orders with `created_at` today.
- `by_status` = counts of today's orders grouped by `order_status` (all four keys always present).
- `active_orders` = today's orders with `order_status !== completed`, ordered by id desc (newest first), limited to a sensible cap (e.g. latest 10).
- `total_price` returned as its numeric value (`cast`ed float).

### 5.2 `/api/dashboard/production` — date basis `order.pickup_datetime` is today

```json
{
  "success": true,
  "message": "Production summary retrieved successfully",
  "data": {
    "total_today": 5,
    "by_status": { "scheduled": 2, "in_progress": 2, "finished": 1 },
    "active_schedules": [
      {
        "id": 3,
        "order_number": "ORD-20260804-000003",
        "start_time": "2026-08-04 08:30:00",
        "end_time": "2026-08-04 10:00:00",
        "production_status": "in_progress",
        "pickup_datetime": "2026-08-04 15:00:00"
      }
    ]
  }
}
```

- `total_today` = count of schedules whose **order's** `pickup_datetime` is today.
- `by_status` = counts of today's schedules grouped by `production_status` (three keys always present).
- `active_schedules` = today's schedules with `production_status` in `scheduled` or `in_progress`, ordered by id desc, capped (latest 10).
- `start_time` / `end_time` may be `null` for not-yet-started schedules.

### 5.3 `/api/dashboard/inventory` — live (no date filter)

```json
{
  "success": true,
  "message": "Inventory summary retrieved successfully",
  "data": {
    "total": 20,
    "by_status": { "available": 15, "low": 3, "out_of_stock": 2 },
    "at_risk": [
      { "id": 2, "name": "Flour", "unit": "kg", "stock_quantity": 4, "status": "low" }
    ]
  }
}
```

- `total` = count of all raw materials.
- `by_status` = counts grouped by the computed availability status (`available` / `low` / `out_of_stock`), reusing `InventoryAvailabilityService::LOW_STOCK_THRESHOLD` (10).
- `at_risk` = materials with status `low` or `out_of_stock`, ordered by name, capped (latest 10).

### 5.4 `/api/dashboard/payments` — paid KPIs by `updated_at` today; outstanding is all-time

```json
{
  "success": true,
  "message": "Payments summary retrieved successfully",
  "data": {
    "paid_total_today": 1200000,
    "outstanding_total": 300000,
    "by_status": { "recorded": 2, "paid": 5 },
    "recorded_today": [
      {
        "id": 8,
        "order_number": "ORD-20260804-000008",
        "payment_method": "transfer",
        "payment_amount": 150000,
        "payment_date": "2026-08-04 09:00:00"
      }
    ]
  }
}
```

- `paid_total_today` = `sum(payment_amount)` where `payment_status = paid` **and `updated_at` is today** (paid-on date; only `verify` sets a payment to `paid`, so `updated_at` is the verify moment).
- `outstanding_total` = `sum(payment_amount)` where `payment_status = recorded` — **all-time, live** (money currently owed).
- `by_status` = counts of all payments grouped by `payment_status` (both keys always present).
- `recorded_today` = payments with `payment_status = recorded` **and `payment_date` is today**, ordered by id desc, capped (latest 10).

### 5.5 `/api/dashboard` — aggregate

```json
{
  "success": true,
  "message": "Dashboard retrieved successfully",
  "data": {
    "orders":  { /* section 5.1 */ },
    "production": { /* section 5.2 */ },
    "inventory": { /* section 5.3 */ },
    "payments": { /* section 5.4 */ }
  }
}
```

Composed from the four section methods in a single request.

## 6. Architecture & Components

```
DashboardController (5 thin actions: index, orders, production, inventory, payments)
        │
        ▼
DashboardService (getOverview, getOrdersSummary, getProductionSummary,
                  getInventorySummary, getPaymentsSummary)
        │
        ├── reuses InventoryAvailabilityService (availability classification)
        └── queries Order / ProductionSchedule / Payment / RawMaterial models
        │
        ▼
DashboardResource(s) shape each section payload
```

**Files to create:**
- `backend/routes/api.php` — **modify** (append 5 GET routes + import).
- `backend/app/Http/Controllers/DashboardController.php`
- `backend/app/Services/DashboardService.php`
- `backend/app/Http/Resources/DashboardOrderResource.php`
- `backend/app/Http/Resources/DashboardProductionResource.php`
- `backend/app/Http/Resources/DashboardInventoryResource.php`
- `backend/app/Http/Resources/DashboardPaymentResource.php`

The aggregate `/dashboard` endpoint composes the four section payloads by collecting the four resources' output keyed as `orders` / `production` / `inventory` / `payments`. No fifth resource is needed: the controller builds the aggregate array on `index()` by calling the four `get*Summary()` methods and nesting the four resources' resolved output under their keys. This keeps the controller thin and avoids duplication.

## 7. Service Behavior

- `getOrdersSummary(): array` — scope orders to today via `whereDate('created_at', today)`, compute total + per-status counts, load active list with `customer` relation.
- `getProductionSummary(): array` — join/scope schedules via order `whereDate('pickup_datetime', today)`, compute total + per-status counts, load active list with `order` relation.
- `getInventorySummary(): array` — load all raw materials, classify each with `InventoryAvailabilityService` logic, compute totals + at-risk list. Reuses the same threshold constant so the dashboard and the availability endpoint never disagree.
- `getPaymentsSummary(): array` — paid KPIs scoped to today via `whereDate('updated_at', today)` on paid payments; outstanding is a separate unscoped sum of recorded; list recorded-today via `whereDate('payment_date', today)` with `order` relation.
- `getOverview(): array` — composes the four above.

All methods are read-only aggregates; no `DB::transaction` needed (no writes). Empty data yields natural zeros / empty lists, never exceptions.

## 8. Resources & Error Handling

Each resource is a thin `toArray` shaping the JSON above. Monetary values (`total_price`, `payment_amount`, `stock_quantity`) returned via numeric casts. Success responses use the `{success, message, data}` envelope. No 404/422 paths (no input). Unexpected exceptions propagate to Laravel's default 500 handler, consistent with the rest of the app.

## 9. Testing

- In-memory SQLite + `RefreshDatabase`, consistent with all prior modules.
- `DashboardServiceTest` — verifies each summary method: date-basis correctness (e.g. a payment recorded yesterday but verified today counts toward `paid_total_today`, not yesterday; an order created today vs older), status groupings, live inventory classification reusing the threshold, and the all-time nature of `outstanding_total`.
- `DashboardTest` (endpoint) — asserts each route returns 200 with the envelope and expected keys; aggregate composes all four sections.
- `DashboardResourceTest` — asserts each section payload shape matches §5.
- Uses existing factories (`Order`, `Payment`, `ProductionSchedule`, `RawMaterial`, `Customer`), with manual `Product::create` where items are needed.

Run commands (PHP is not on PATH — invoke explicitly from `backend/`):
- `"C:\Users\Indra\Tools\PHP\php.exe" artisan test --filter=Dashboard...`
- `"C:\Users\Indra\Tools\PHP\php.exe" artisan test`
- `"C:\Users\Indra\Tools\PHP\php.exe" vendor/bin/pint --test`

## 10. Known Limitations (documented)

1. "Today" boundaries use the app server's local date; no timezone param yet.
2. `paid_total_today` uses `updated_at`, which also changes if a *paid* payment were ever edited — currently edits only apply to `recorded` payments and `verify` is the sole recorded→paid path, so `updated_at` reliably marks the paid moment today.
3. `outstanding_total` is all-time unpaid and grows with historical recorded payments; it is intentionally not date-scoped (money owed "now").
4. Lists are capped (latest 10) to keep payloads small; full lists remain available via the module REST endpoints.
5. Inventory `by_status` is a point-in-time snapshot computed at request time.

## 11. Constraints & Rules Followed

- No migrations added or modified; no table/column renames.
- Keep controllers thin; business/aggregation logic lives in `DashboardService`.
- Response envelope `{success, message, data}` on 2xx.
- PSR-12 / PHP 8.3 / Laravel 12; no new third-party dependencies.
- Reuse existing services/factories and mirror prior module file/naming/commit conventions.
- `pint --test` clean before completion.