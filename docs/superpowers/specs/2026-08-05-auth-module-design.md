# Authentication Module — Design Specification

**Date:** 2026-08-05
**Status:** Approved for implementation

## 1. Overview

The Authentication module gives the **owner** a secure way to authenticate against the Laravel API before accessing ERP features. It is the declared next module (per `docs/context.md`), satisfies requirement **NFR-004** ("Sistem menyediakan autentikasi bagi Owner sebelum mengakses fitur ERP"), and implements the endpoints already specified in `docs/09_API.md`: `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`.

The mechanism is **Laravel Sanctum personal access tokens**: long-lived, server-stored (hashed), bearer tokens issued on login and revoked on logout. This is the idiomatic Laravel choice for a first-party mobile (Android) client and requires no refresh-token flow for a single-owner system.

All decisions were confirmed during brainstorming:

- **Mechanism:** Sanctum personal access tokens (Approach A).
- **Scope:** Protect all owner routes; keep only guest-facing routes public (`GET /products`, `GET /products/{id}`, `POST /orders`) per NFR-002.
- **Owner provisioning:** Database seeder only — no public registration endpoint. Single owner per `docs/00_Discovery.md`.
- **Role column:** Not added. Single operator; everyone who can authenticate is the owner (YAGNI).
- **Token lifetime:** Long-lived (`expiration = null`), revocable on logout.
- **Login rate limiting:** `throttle:5,1` on `POST /auth/login`.

## 2. Scope

### In scope
- Sanctum installation and configuration.
- `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`.
- Protection of all owner routes via `auth:sanctum`.
- Default owner seeder.
- Tests and docs updates.

### Out of scope
- Public registration / account management endpoints.
- Password reset / change flows.
- Roles and authorization (single owner).
- Token refresh (tokens are long-lived).
- Android application (separate upcoming module).

## 3. Database

**No existing migrations are modified.** Sanctum publishes a **new** migration creating the `personal_access_tokens` table (`id`, `tokenable` morph, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, timestamps). The existing `users` table is reused as-is.

## 4. API Endpoints

| Method | URI | Auth | Purpose | Success code |
|---|---|---|---|---|
| POST | `/api/auth/login` | public + `throttle:5,1` | Validate credentials, issue token | 200 |
| POST | `/api/auth/logout` | `auth:sanctum` | Revoke current token | 200 |
| GET | `/api/auth/me` | `auth:sanctum` | Return authenticated user | 200 |

### 4.1 Login

**Request body:** `{ "email": string, "password": string }` (validated: email required|email, password required|string).

**Success `200`:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { "id": 1, "name": "Owner", "email": "owner@example.com", "created_at": "..." },
    "token": "<plain-text-bearer-token>"
  }
}
```

**Credentials mismatch:** `401` with `{ "message": "These credentials do not match our records." }` (no `success` key, matching Laravel's authentication failure response).
**Validation failure:** `422` with standard `{ message, errors }`.

### 4.2 Logout

Revokes the current token. **Success `200`:** `{ "success": true, "message": "Logout successful", "data": null }`.

### 4.3 Me

Returns the authenticated user. **Success `200`:** `{ "success": true, "message": "User retrieved successfully", "data": { "id", "name", "email", "created_at" } }`.

## 5. Route Protection Split

`routes/api.php` is restructured into two groups:

- **Public guest group** (unchanged behavior): `GET /products`, `GET /products/{id}`, `POST /orders`.
- **`auth:sanctum` owner group**: product create/update/delete, all recipe routes, all raw-material routes, all inventory-purchase routes, `GET /inventory/availability`, all production-schedule routes, order index/show/update/confirm, all payment routes, all dashboard routes.

`/auth/login` stays public (with throttle); `/auth/logout` and `/auth/me` are inside the `auth:sanctum` group.

## 6. Architecture & Components

```
AuthController (3 thin actions: login, logout, me)
        │
        ▼
AuthService (login, logout, me)
        │
        ├── User model (find by email, Hash::check, createToken / delete token)
        └── LoginRequest (validation)
        │
        ▼
UserResource (shapes the user payload)
```

**Files to create:**
- `backend/app/Services/AuthService.php`
- `backend/app/Http/Controllers/AuthController.php`
- `backend/app/Http/Requests/LoginRequest.php`
- `backend/app/Http/Resources/UserResource.php`
- `backend/database/seeders/OwnerSeeder.php`
- `backend/config/sanctum.php` (published)
- Sanctum migration for `personal_access_tokens` (published)
- Tests: `AuthServiceTest`, `AuthTest`, `LoginRequestTest`, `UserResourceTest`

**Files to modify:**
- `backend/composer.json` / `composer.lock` (add `laravel/sanctum`)
- `backend/app/Models/User.php` (add `HasApiTokens` trait)
- `backend/routes/api.php` (auth routes + protection groups)
- `backend/database/seeders/DatabaseSeeder.php` (call `OwnerSeeder`)
- `backend/.env.example` (`OWNER_EMAIL`, `OWNER_PASSWORD`)
- `docs/context.md` (session context at module end)

## 7. Service Behavior

- `login(array $credentials): array` — resolve `User` by `email`, verify with `Hash::check`; on success create a token via `$user->createToken('owner')` and return `['user' => $user, 'token' => $plainTextToken]`. On mismatch throw `ValidationException` with `email => "These credentials do not match our records."`.
- `logout(User $user): void` — `$user->currentAccessToken()->delete()`.
- `me(User $user): User` — returns the authenticated user.

No `DB::transaction` needed (single-row writes).

## 8. Error Handling

- Unauthenticated requests to protected routes return `401` JSON via Sanctum's `AuthenticationException`, rendered as JSON because `shouldRenderJsonWhen(api/*)` is already configured in `bootstrap/app.php`.
- Unexpected exceptions propagate to the existing 500 handler, consistent with the rest of the app.

## 9. Testing

In-memory SQLite + `RefreshDatabase`, consistent with all prior modules.

- `AuthServiceTest` — token issued on valid login; credentials mismatch throws; logout revokes the token.
- `AuthTest` (endpoints) — login returns 200 + token + user shape; invalid credentials → 401; missing fields → 422; `/auth/me` and `/auth/logout` without token → 401; `/auth/me` with token → 200; after logout the token is dead; protected endpoint (`GET /orders`) without token → 401; guest endpoints remain open (`GET /products` → 200, `POST /orders` → 201 without token).
- `LoginRequestTest` — validation rules.
- `UserResourceTest` — payload shape.

Run commands (PHP is not on PATH — invoke explicitly from `backend/`):
- `"C:\Users\Indra\Tools\PHP\php.exe" artisan test`
- `"C:\Users\Indra\Tools\PHP\php.exe" vendor/bin/pint --test`

## 10. Known Limitations (documented)

1. Single owner account; no multi-user or roles.
2. Tokens are long-lived by design; a lost device requires the owner to log in elsewhere or a DB cleanup of the revoked token.
3. Password reset/change is out of scope.
4. The seeder creates the default owner with a plaintext default password when env vars are absent — deployment must set `OWNER_EMAIL` / `OWNER_PASSWORD`.

## 11. Constraints & Rules Followed

- No existing migrations modified; only Sanctum's new `personal_access_tokens` migration added.
- No table/column renames.
- Controllers thin; business logic lives in `AuthService`.
- Response envelope `{success, message, data}` on 2xx.
- PSR-12 / PHP 8.3 / Laravel 13.
- Full test suite and `pint --test` pass before commit.
