# Modules

## 1. Overview

Dokumen ini menjelaskan pembagian modul pada ERP Mobile untuk UMKM makanan dengan model bisnis Make-to-Order. Pembagian modul dilakukan berdasarkan hasil Discovery, Project Vision, Business Process, dan Requirement Analysis. Setiap modul memiliki tanggung jawab yang berbeda namun saling terintegrasi dalam mendukung proses bisnis.

## 2. Order Management

### Tujuan

Mengelola seluruh proses pemesanan pelanggan mulai dari pembuatan pesanan hingga pesanan dikonfirmasi.

### 2.1 Responsibilities

- Menerima data pesanan dari Guest Ordering.
- Menyimpan data pesanan.
- Mengelola detail pesanan.
- Mengelola status pesanan.
- Menampilkan daftar pesanan.
- Mengelola perubahan pesanan sebelum produksi dimulai.

### 2.2 Input

- Nama customer
- Nomor HP
- Menu
- Jumlah
- Jadwal pengambilan
- Kustomisasi

### 2.3 Output

- Order
- Order Status

## 3. Production Planning

### Tujuan

Membantu owner mengelola proses produksi berdasarkan pesanan yang telah dikonfirmasi.

### Responsibilities

- Menyusun jadwal produksi.
- Mengelola proses produksi.
- Mengubah status produksi.
- Mengubah status pesanan menjadi Finished.

### Input

- Order
- Pickup Schedule

### Output

- Production Schedule
- Production Status

## 4. Inventory Management

### Tujuan

Mengelola informasi bahan baku yang digunakan selama proses produksi.

### Responsibilities

- Menampilkan stok bahan.
- Memperbarui stok.
- Mendukung pengecekan ketersediaan bahan.
- Mendukung pencatatan pembelian bahan.

### Input

- Raw Material
- Production Data

### Output

- Inventory
- Stock Availability

## 5. Payment Management

### Tujuan

Mengelola pencatatan pembayaran setiap pesanan.

### Responsibilities

- Mencatat pembayaran.
- Mengubah status pembayaran.
- Menghubungkan pembayaran dengan pesanan.

### Input

- Order
- Payment Information

### Output

- Payment Status

## 6. Operational Dashboard

### Tujuan

Menyajikan ringkasan informasi operasional untuk membantu owner memantau kondisi usaha.

### Responsibilities

- Menampilkan jumlah pesanan.
- Menampilkan jadwal produksi.
- Menampilkan status stok.
- Menampilkan status pembayaran.

### Input

- Order Data
- Production Data
- Inventory Data
- Payment Data

### Output

- Dashboard Summary

## 7. Module Interaction

| Module                | Uses                                    |
| --------------------- | --------------------------------------- |
| Order Management      | Production Planning, Payment Management |
| Production Planning   | Inventory Management                    |
| Inventory Management  | Production Planning                     |
| Payment Management    | Order Management                        |
| Operational Dashboard | Seluruh modul                           |
