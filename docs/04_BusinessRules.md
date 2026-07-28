# Business Rules

## 1. Overview

Dokumen ini mendefinisikan aturan bisnis (Business Rules) yang mengatur bagaimana proses bisnis dijalankan pada sistem ERP Mobile. Aturan-aturan ini disusun berdasarkan hasil Discovery, Project Vision, dan Business Process untuk memastikan implementasi sistem tetap sesuai dengan proses bisnis UMKM makanan berbasis Make-to-Order.

## 2. Order Management Rules

| ID     | Business Rule                                                            |
| ------ | ------------------------------------------------------------------------ |
| BR-001 | Customer melakukan pemesanan tanpa registrasi akun.                      |
| BR-002 | Customer wajib mengisi nama dan nomor HP sebelum pesanan dikirim.        |
| BR-003 | Setiap pesanan harus memiliki minimal satu produk.                       |
| BR-004 | Setiap pesanan harus memiliki tanggal dan waktu pengambilan.             |
| BR-005 | Perubahan pesanan hanya dapat dilakukan sebelum proses produksi dimulai. |
| BR-006 | Pesanan harus dikonfirmasi oleh owner sebelum diproses lebih lanjut.     |

## 3. Production Planning Rules

| ID     | Business Rule                                                         |
| ------ | --------------------------------------------------------------------- |
| BR-007 | Produksi hanya dapat dimulai setelah pesanan dikonfirmasi.            |
| BR-008 | Jadwal produksi disusun berdasarkan waktu pengambilan pesanan.        |
| BR-009 | Setiap pesanan hanya memiliki satu jadwal produksi aktif.             |
| BR-010 | Status pesanan berubah menjadi **Finished** setelah produksi selesai. |

## 4. Inventory Management Rules

| ID     | Business Rule                                                                           |
| ------ | --------------------------------------------------------------------------------------- |
| BR-011 | Ketersediaan bahan baku harus diperiksa sebelum produksi dimulai.                       |
| BR-012 | Apabila stok tidak mencukupi maka owner melakukan pembelian bahan baku terlebih dahulu. |
| BR-013 | Data inventori diperbarui setelah proses produksi selesai.                              |

## 5. Payment Management Rules

| ID     | Business Rule                                                                                               |
| ------ | ----------------------------------------------------------------------------------------------------------- |
| BR-014 | Metode pembayaran mengikuti kesepakatan antara customer dan owner.                                          |
| BR-015 | Status pembayaran dicatat untuk setiap pesanan.                                                             |
| BR-016 | Status pesanan berubah menjadi **Completed** setelah pesanan selesai diproses dan pembayaran telah dicatat. |

## 6. Business Rule Traceability

| Business Rule   | Discovery | Project Vision | Business Process |
| --------------- | --------- | -------------- | ---------------- |
| BR-001 – BR-006 | ✔         | ✔              | ✔                |
| BR-007 – BR-010 | ✔         | ✔              | ✔                |
| BR-011 – BR-013 | ✔         | ✔              | ✔                |
| BR-014 – BR-016 | ✔         | ✔              | ✔                |
