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

<p align="center">
  <img src="../assets/diagrams/BusinessProcess/TO-BE 2 .drawio.png" width="800">
</p>

<p align="center">
<b>Gambar 4.0.</b> Current Business Process (TO-BE)
</p>

## 5. Process Improvements

| No | AS-IS                                                             | TO-BE                                                                                   |
| -- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| 1  | Pemesanan dicatat secara manual oleh owner.                       | Customer melakukan pemesanan melalui Guest Ordering dan data langsung tersimpan di ERP. |
| 2  | Owner harus mengingat atau mencatat status pesanan secara manual. | Status pesanan dikelola secara otomatis oleh ERP.                                       |
| 3  | Pemeriksaan stok dilakukan secara manual.                         | ERP membantu pengelolaan inventori dan status stok.                                     |
| 4  | Informasi pesanan tersebar di chat dan catatan.                   | Seluruh data pesanan tersimpan terpusat di ERP.                                         |
| 5  | Jadwal produksi ditentukan tanpa pencatatan yang terstruktur.     | Owner menyusun jadwal produksi berdasarkan data pesanan di ERP.                         |
| 6  | Pembayaran dicatat secara manual di luar sistem.                  | Pembayaran dicatat dan statusnya diperbarui melalui ERP.                                |

## 6. Business Process Assumptions

| No | Assumption                                                                                   |
| -- | -------------------------------------------------------------------------------------------- |
| 1  | Customer melakukan pemesanan melalui Guest Ordering tanpa registrasi akun.                   |
| 2  | Owner merupakan pengguna utama aplikasi ERP Mobile.                                          |
| 3  | Komunikasi tambahan antara owner dan customer dilakukan melalui WhatsApp apabila diperlukan. |
| 4  | Pembayaran dilakukan secara langsung (cash atau transfer) dan dicatat oleh owner di ERP.     |
| 5  | Setiap pesanan memiliki satu owner yang bertanggung jawab untuk memprosesnya.                |
| 6  | ERP menjadi pusat penyimpanan data pesanan, inventori, dan status operasional.               |
