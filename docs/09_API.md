# API Specification

## Overview

### Purpose

Dokumen ini menjelaskan spesifikasi Application Programming Interface (API) yang digunakan dalam Hybrid ERP Mobile untuk UMKM makanan dengan model bisnis Make-to-Order (MTO).

API bertindak sebagai penghubung antara Guest Ordering, aplikasi Android, dan basis data terpusat. Seluruh komunikasi data dilakukan menggunakan arsitektur REST API dengan format JSON.

### Scope

Dokumen ini mencakup beberapa modul berikut:

- Authentication
- Order Management
- Production Planning
- Inventory Management
- Payment Management
- Operational Dashboard

---

## API Architecture

### Architecture Style

Sistem menggunakan pendekatan REST API.

```text
Guest Ordering
        │
        ▼
Laravel REST API
        │
        ▼
PostgreSQL
        │
        ▼
Android Application
```

---

### Base URL

#### Development Environment

```text
http://localhost:8000/api
```

#### Production Environment

```text
https://api.example.com/api
```

---

### Request Format

```json
{
    "name": "John Doe",
    "phone_number": "081234567890"
}
```

---

### Response Format

```json
{
    "success": true,
    "message": "Data saved successfully",
    "data": {}
}
```

---

## Authentication

### Login

**Endpoint**

```text
POST /auth/login
```

**Request Body**

```json
{
    "email": "owner@example.com",
    "password": "password"
}
```

---

### Logout

**Endpoint**

```text
POST /auth/logout
```

---

### Current User

**Endpoint**

```text
GET /auth/me
```

---

## Order Management API

### Get Products

**Endpoint**

```text
GET /products
```

---

### Get Product Detail

**Endpoint**

```text
GET /products/{id}
```

---

### Create Order

**Endpoint**

```text
POST /orders
```

**Request Body**

```json
{
    "customer_name": "Budi",
    "phone_number": "081234567890",
    "pickup_datetime": "2026-08-01 10:00:00",
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        }
    ]
}
```

---

### Get Orders

**Endpoint**

```text
GET /orders
```

---

### Get Order Detail

**Endpoint**

```text
GET /orders/{id}
```

---

### Update Order

**Endpoint**

```text
PUT /orders/{id}
```

---

### Confirm Order

**Endpoint**

```text
PATCH /orders/{id}/confirm
```

---

### Complete Order

**Endpoint**

```text
PATCH /orders/{id}/complete
```

---

## Production Planning API

### Get Production Schedule

**Endpoint**

```text
GET /production-schedules
```

---

### Create Production Schedule

**Endpoint**

```text
POST /production-schedules
```

---

### Update Production Schedule

**Endpoint**

```text
PUT /production-schedules/{id}
```

---

### Start Production

**Endpoint**

```text
PATCH /production-schedules/{id}/start
```

---

### Finish Production

**Endpoint**

```text
PATCH /production-schedules/{id}/finish
```

---

## Inventory Management API

### Get Raw Materials

**Endpoint**

```text
GET /raw-materials
```

---

### Create Raw Material

**Endpoint**

```text
POST /raw-materials
```

---

### Update Raw Material

**Endpoint**

```text
PUT /raw-materials/{id}
```

---

### Create Purchase Record

**Endpoint**

```text
POST /inventory-purchases
```

---

### Get Purchase History

**Endpoint**

```text
GET /inventory-purchases
```

---

## Payment Management API

### Create Payment

**Endpoint**

```text
POST /payments
```

---

### Get Payments

**Endpoint**

```text
GET /payments
```

---

### Update Payment

**Endpoint**

```text
PUT /payments/{id}
```

---

### Verify Payment

**Endpoint**

```text
PATCH /payments/{id}/verify
```

---

## Dashboard API

### Dashboard Summary

**Endpoint**

```text
GET /dashboard
```

---

### Recent Orders

**Endpoint**

```text
GET /dashboard/orders
```

---

### Production Status

**Endpoint**

```text
GET /dashboard/production
```

---

### Inventory Status

**Endpoint**

```text
GET /dashboard/inventory
```

---

### Payment Status

**Endpoint**

```text
GET /dashboard/payments
```

---

## HTTP Status Code

| Code | Description |
|------|------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

---

## Validation Error

```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "phone_number": [
            "Phone number is required."
        ]
    }
}
```

---

## Exception Handling

Seluruh pengecualian (exception) ditangani secara terpusat melalui Laravel Exception Handler agar respons API tetap konsisten.

---