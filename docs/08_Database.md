# Database Design

## Overview

### Purpose

Dokumen ini menjelaskan perancangan basis data yang digunakan pada Hybrid ERP Mobile untuk UMKM makanan dengan model bisnis Make-to-Order (MTO). Perancangan basis data dilakukan untuk mendukung seluruh proses bisnis mulai dari penerimaan pesanan, perencanaan produksi, pengelolaan bahan baku, proses pembayaran, hingga penyajian informasi operasional secara terpusat. Seluruh struktur data dirancang berdasarkan hasil proses discovery, project vision, business process, requirement analysis, business rules, modules, user flow, dan architecture.

### Scope

Ruang lingkup perancangan basis data meliputi modul berikut:

- Order Management
- Production Planning
- Inventory Management
- Payment Management
- Operational Dashboard

---

## Database Management System

### PostgreSQL

Sistem menggunakan PostgreSQL sebagai basis data utama karena memiliki kemampuan pengelolaan transaksi yang baik, mendukung relasi data yang kompleks, serta dapat diintegrasikan dengan Laravel secara optimal.

### Database Configuration

| Parameter | Value |
| ---------- | ----- |
| Database Engine | PostgreSQL |
| Character Set | UTF-8 |
| Time Zone | Asia/Jakarta |
| Port | 5432 |

---

## Database Architecture

### Database Structure

Sistem menggunakan pendekatan Relational Database Management System (RDBMS) untuk menjaga konsistensi dan integritas data.

Seluruh modul akan menggunakan satu basis data terpusat yang terdiri atas beberapa entitas utama, yaitu:

- Users
- Customers
- Products
- Orders
- Production Schedules
- Raw Materials
- Payments

### Naming Convention

Aturan penamaan yang digunakan adalah sebagai berikut.

- Menggunakan format `snake_case`.
- Nama tabel menggunakan bentuk jamak (*plural*).
- Primary key menggunakan nama `id`.
- Foreign key menggunakan format `<table_name>_id`.

Contoh:

```text
customers
order_items
raw_materials
payment_status
created_at
updated_at
```

### Data Type Standardization

| Data Type | Description |
| ---------- | ----------- |
| bigint | Primary key |
| varchar | Data teks |
| text | Data deskripsi |
| decimal | Data numerik |
| boolean | Nilai logika |
| timestamp | Data waktu |
| date | Data tanggal |

---

## Entity Relationship Diagram (ERD)

### Entity Relationship Diagram

Entitas utama yang digunakan dalam sistem adalah sebagai berikut.

- Users
- Customers
- Products
- Product Customizations
- Recipes
- Recipe Details
- Raw Materials
- Inventory Purchases
- Orders
- Order Items
- Production Schedules
- Payments

### Relationship Description

Setiap pesanan terhubung dengan pelanggan, produk, pembayaran, serta jadwal produksi. Produk memiliki hubungan dengan resep dan bahan baku untuk mendukung fleksibilitas proses produksi.

---

## Table Design

### Users

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| name | varchar |
| email | varchar |
| password | varchar |
| role | varchar |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Email harus bersifat unik.
- Password disimpan dalam bentuk hash.

---

### Customers

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| name | varchar |
| phone_number | varchar |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Nomor telepon harus unik.

---

### Products

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| name | varchar |
| description | text |
| base_price | decimal |
| is_active | boolean |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Harga produk harus lebih besar dari nol.

---

### Product Customizations

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| product_id | bigint |
| name | varchar |
| additional_price | decimal |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Setiap kustomisasi harus terhubung dengan satu produk.

---

### Recipes

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| product_id | bigint |
| recipe_name | varchar |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Satu resep hanya dimiliki oleh satu produk.

---

### Recipe Details

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| recipe_id | bigint |
| raw_material_id | bigint |
| quantity | decimal |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Jumlah bahan baku harus lebih besar dari nol.

---

### Raw Materials

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| name | varchar |
| stock_quantity | decimal |
| unit | varchar |
| expiration_date | date |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Stok tidak boleh bernilai negatif.

---

### Inventory Purchases

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| raw_material_id | bigint |
| quantity | decimal |
| purchase_date | timestamp |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Data pembelian harus terhubung dengan bahan baku.

---

### Orders

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| customer_id | bigint |
| order_number | varchar |
| pickup_datetime | timestamp |
| order_status | varchar |
| total_price | decimal |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Nomor pesanan harus unik.
- Setiap pesanan harus memiliki pelanggan.

---

### Order Items

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| order_id | bigint |
| product_id | bigint |
| quantity | integer |
| customization_note | text |
| subtotal | decimal |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Jumlah pesanan harus lebih besar dari nol.

---

### Production Schedules

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| order_id | bigint |
| start_time | timestamp |
| end_time | timestamp |
| production_status | varchar |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Setiap pesanan hanya memiliki satu jadwal produksi aktif.

---

### Payments

#### Attributes

| Column | Type |
| ------ | ---- |
| id | bigint |
| order_id | bigint |
| payment_method | varchar |
| payment_status | varchar |
| payment_amount | decimal |
| payment_date | timestamp |
| created_at | timestamp |
| updated_at | timestamp |

#### Constraints

- Pembayaran harus terhubung dengan satu pesanan.

---

## Relationships

### One-to-One Relationships

- Orders → Production Schedules

### One-to-Many Relationships

- Customers → Orders
- Orders → Order Items
- Products → Product Customizations
- Products → Recipes
- Recipes → Recipe Details
- Raw Materials → Inventory Purchases
- Orders → Payments

### Many-to-Many Relationships

- Products ↔ Raw Materials
- Orders ↔ Products

---

## Indexing Strategy

### Primary Index

- id

### Foreign Key Index

- customer_id
- product_id
- recipe_id
- raw_material_id
- order_id

### Search Optimization

- order_number
- phone_number
- pickup_datetime
- payment_status
- production_status

---

## Migration Strategy

### Migration Order

1. Users
2. Customers
3. Products
4. Product Customizations
5. Raw Materials
6. Recipes
7. Recipe Details
8. Orders
9. Order Items
10. Inventory Purchases
11. Production Schedules
12. Payments

### Rollback Strategy

Laravel Migration digunakan untuk mengembalikan perubahan struktur basis data apabila terjadi kesalahan selama proses pengembangan.

Rollback dilakukan dengan mekanisme berikut:

```bash
php artisan migrate:rollback
```

Untuk menghapus seluruh tabel dan menjalankan migrasi kembali, digunakan perintah berikut.

```bash
php artisan migrate:fresh
```

---