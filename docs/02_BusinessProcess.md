# Business Process

## 1. Overview

Dokumen ini menjelaskan proses bisnis yang akan diterapkan setelah implementasi ERP Mobile pada UMKM makanan dengan model bisnis Make-to-Order. Proses bisnis yang dijelaskan merupakan hasil transformasi dari proses bisnis saat ini (AS-IS) menjadi proses bisnis yang didukung oleh sistem ERP (TO-BE). Dokumen ini menjadi acuan dalam penyusunan kebutuhan sistem, desain modul, serta implementasi aplikasi.

## 2. Business Process Transformation

### 2.1 AS-IS and TO-BE Comparison

| Business Activity     | AS-IS                | TO-BE                      |
| --------------------- | -------------------- | -------------------------- |
| Order Recording       | Dicatat manual       | Dicatat melalui ERP Mobile |
| Inventory Checking    | Dicek manual         | Ditampilkan oleh sistem    |
| Production Scheduling | Disusun manual       | Dikelola melalui sistem    |
| Payment Tracking      | Diingat owner        | Dicatat dalam sistem       |
| Order History         | Tidak terdokumentasi | Tersimpan dalam ERP        |

### 2.2 TO-BE Process

Setelah implementasi ERP Mobile, proses bisnis UMKM makanan dengan model Make-to-Order berpusat pada pengelolaan pesanan (Order Management). Setiap pesanan pelanggan dicatat melalui sistem dan menjadi dasar bagi aktivitas operasional berikutnya, seperti perencanaan produksi, pengelolaan bahan baku, serta pencatatan pembayaran.

ERP Mobile berperan sebagai sistem yang membantu owner dalam mengelola seluruh proses operasional secara terintegrasi. Sistem menyediakan informasi yang dibutuhkan pada setiap tahap proses bisnis, mulai dari pencatatan pesanan, pemantauan stok bahan baku, pelaksanaan produksi, hingga penyelesaian pembayaran. Dengan demikian, seluruh aktivitas operasional dapat terdokumentasi secara lebih terstruktur, efisien, dan mudah dipantau.

## 3. TO-BE Business Process Description

| No | Aktivitas                | Aktor |
| -- | ------------------------ | ----- |
| 1  | Membuat pesanan          | Owner |
| 2  | Menyimpan pesanan        | ERP   |
| 3  | Menampilkan stok bahan   | ERP   |
| 4  | Konfirmasi pesanan       | Owner |
| 5  | Menyusun jadwal produksi | Owner |
| 6  | Produksi                 | Owner |
| 7  | Mengurangi stok          | ERP   |
| 8  | Mencatat pembayaran      | Owner |
| 9  | Mengubah status pesanan  | ERP   |

## 4. TO-BE Business Process Diagram (BPMN)

## 5. Process Improvements

## 6. Business Process Assumptions