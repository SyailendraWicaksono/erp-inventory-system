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

## Session focus

- Authentication

## Next modules

- Authentication
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
- 252 tests passed.
- 643 assertions passed.
- Pint checks passed.

---

## Latest commit

- b41c1c2 docs: use today for pickup_datetime fixtures in dashboard plan

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