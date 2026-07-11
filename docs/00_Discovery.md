# Product Discovery

## 1. Overview

Dokumen ini disusun untuk memahami proses bisnis dan permasalahan operasional yang terjadi pada UMKM makanan yang menerapkan sistem produksi berdasarkan pesanan (Make to Order). Seluruh hasil observasi dalam dokumen ini akan menjadi dasar dalam penyusunan visi proyek, proses bisnis, kebutuhan sistem, hingga implementasi aplikasi ERP.

## 2.Busines Domain
### Industry

Usaha Mikro, Kecil, dan Menengah (UMKM) Makanan.

### Business Model

Make to Order (MTO), yaitu proses produksi yang dilakukan setelah pesanan pelanggan diterima dan disetujui.

### Domain Focus

ERP yang dikembangkan berfokus pada pengelolaan proses bisnis UMKM makanan, mulai dari penerimaan pesanan, perencanaan produksi, pengelolaan bahan baku, proses produksi, hingga penyerahan pesanan kepada pelanggan.

## 3. Business Characteristic
### 3.1 Make to Order

Produksi dilakukan setelah pelanggan melakukan pemesanan. Produk tidak diproduksi untuk disimpan sebagai stok barang jadi.

### 3.2 Menu-Based Product

Pelanggan memilih produk dari daftar menu yang telah disediakan oleh UMKM.

### 3.3 Product Customization

Pelanggan dapat melakukan penyesuaian terhadap produk, seperti mengubah isian, topping, atau komposisi tertentu sesuai kebutuhan selama masih memungkinkan untuk diproduksi.

### 3.4 Price Negotiation

Harga transaksi tidak selalu mengikuti harga acuan. Dalam beberapa kondisi pelanggan menentukan batas anggaran (budget), kemudian pemilik UMKM melakukan penyesuaian komposisi produk agar tetap memenuhi target keuntungan.

### 3.5 Product Scheduling

Waktu pengambilan pesanan menjadi salah satu faktor utama sebelum proses produksi dilakukan. Jadwal produksi disusun agar produk selesai tepat waktu sesuai kesepakatan.

### 3.6 Raw Material Inventory

UMKM menyimpan stok bahan baku dalam jumlah terbatas. Sebelum produksi dimulai dilakukan pemeriksaan terhadap jumlah stok, kondisi bahan, dan masa kedaluwarsa secara manual.

### 3.7 Flexible Recipe

Resep dapat bersifat tetap maupun berubah. Pada beberapa produk, resep dapat disesuaikan berdasarkan permintaan pelanggan atau hasil eksperimen pemilik UMKM.

### 3.8 Order Confirmation

Produksi hanya dimulai setelah detail pesanan, harga, waktu pengambilan, dan bentuk kustomisasi disepakati oleh pelanggan dan pemilik UMKM

### 3.9 Limited Production Capacity

Kapasitas produksi UMKM memiliki batas tertentu yang dipengaruhi oleh jumlah tenaga kerja, peralatan, dan waktu produksi. Apabila kapasitas produk telah penuh, pesanan baru dapat dijadwalkan ulang atau ditolak.

### 3.10 Manual Inventory Checking

Sebelum proses produksi dimulai, pemilik atau bagian produksi melakukan pemeriksaan bahan baku secara manual untuk memastikan jumlah stok, kondisi bahan, dan masa kedaluwarsa masih memenuhi kebutuhan produksi.

## Business Goals

- Mengurangi kesalahan pencatatan bahan baku.
- Membantu perencanaan produksi.
- Membantu menentukan harga yang tetap menguntungkan.
- Mengintegrasikan proses pemesanan hingga produksi.
- Mengurangi risiko keterlambatan produksi.