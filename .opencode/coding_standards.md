# Coding Standards

## General

- Follow Laravel 12 conventions.
- Use PHP 8.3 features when appropriate.
- Use PostgreSQL as the database engine.
- Use PSR-12 coding standards.
- Write clean, readable, and maintainable code.

---

## Naming conventions

### Classes

Use PascalCase.

Examples:

- ProductController
- ProductService
- ProductRequest
- ProductResource

---

### Methods and variables

Use camelCase.

Examples:

- getProductById()
- createOrder()
- totalPrice

---

### Database

Use snake_case.

Examples:

- customer_id
- order_status
- created_at

---

### Tables

Use plural names.

Examples:

- customers
- products
- order_items

---

### Models

Use singular names.

Examples:

- Customer
- Product
- Order

---

## Architecture

Use the following structure:

Controller
    ↓
Request
    ↓
Service
    ↓
Model
    ↓
Resource

---

## Controllers

- Keep controllers thin.
- Do not write business logic inside controllers.
- Use dependency injection.
- Use Form Requests.

---

## Validation

- Validate all input data.
- Return appropriate HTTP status codes.

---

## API

Use REST conventions.

GET     /api/products
GET     /api/products/{id}
POST    /api/products
PUT     /api/products/{id}
DELETE  /api/products/{id}

---

## Responses

All responses must follow this structure:

{
    "success": true,
    "message": "",
    "data": {}
}

---

## Git

Commit after completing a feature.

Examples:

feat: create product controller

feat: create order module

fix: correct payment validation