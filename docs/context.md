# ERP Inventory System – Session Context

## Completed modules

- Product
- Recipe
- Raw Material
- Inventory
- Production
- Order
- Payment
- Dashboard
- Authentication

## Session focus

- Android application

## Next modules

- Android application

## Current status

- Inventory module completed.
- Production module completed.
- Order module completed.
- Order CRUD implemented.
- Order confirmation workflow implemented.
- Order number generation implemented.
- Server-side pricing implemented.
- Payment module completed.
- Payment recording and verification implemented.
- Order completion (finished -> completed) implemented via payment verification.
- Dashboard module completed.
- Daily operational summaries implemented (orders, production, inventory, payments).
- Authentication module completed.
- Owner authentication via Laravel Sanctum personal access tokens implemented.
- Login, logout, and current-user endpoints implemented.
- Owner routes protected with auth:sanctum; guest ordering (products, create order) stays public.
- Login rate-limited (5 attempts/min); tokens long-lived and revoked on logout.
- Default owner seeded via OwnerSeeder (OWNER_EMAIL / OWNER_PASSWORD env).
- 275 tests passed.
- 688 assertions passed.
- Pint checks passed.

---

## Latest commit

- d2422e5 feat: add authentication module with Sanctum

---

## Architecture

- Backend: Laravel API
- Database: PostgreSQL
- Mobile application: Android Studio (Kotlin + Jetpack Compose)
- Deployment: VPS

---

## Project structure

ERP-INVENTORY-SYSTEM/
├── backend
├── docs
├── frontend
├── assets
└── .opencode

---

## Development rules

- Never modify migrations.
- Never rename tables.
- Never rename columns.
- Keep controllers thin.
- Always explain the implementation plan before modifying files.
- Always ask for approval before modifying files.

---

## Workflow

Brainstorming
→ Design
→ Planning
→ Implementation
→ Verification
→ Review

---

## Notes

- Use the Product module as a reference.
- Use the Recipe module as a reference.
- Use the Raw Material module as a reference.
- Use the Inventory module as a reference.
- Use the Production module as a reference.
- Use the Order module as a reference.
- Use the Payment module as a reference.
- Use the Dashboard module as a reference.
- Use the Authentication module as a reference.