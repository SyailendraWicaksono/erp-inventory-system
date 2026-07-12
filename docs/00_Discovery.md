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

### 3.11 Procurement on Demand

Apabila bahan baku yang dibutuhkan tidak tersedia atau jumlahnya tidak mencukupi, pemilik UMKM akan melakukan pembelian bahan baku terlebih dahulu agar pesanan tetap dapat diproduksi sesuai kesepakatan.

### 3.12 Flexible Payment Agreement

Metode pembayaran pada UMKM tidak selalu sama. Pembayaran dapat dilakukan dengan uang muka (Down Payment/DP) sebelum produksi dimulai atau dibayarkan setelah pesanan selesai dan diterima oleh pelanggan, sesuai kesepakatan antara kedua belah pihak.

### 3.13 Agreement-Based Business Process

Sebagian besar proses bisnis dilakukan berdasarkan kesepakatan antara pelanggan dan pemilik UMKM, termasuk harga, metode pembayaran, waktu penyerahan, serta bentuk kustomisasi produk.

### 3.14 Conditional Order Modification

Perubahan pesanan masih dapat dilakukan selama proses produksi belum dimulai. Namun, apabila proses produksi telah berjalan atau selesai, perubahan pesanan umumnya tidak dapat dilakukan karena akan berdampak pada biaya, waktu produksi, dan penggunaan bahan baku.

### 3.15 Owner-Centered Operation

Sebagian besar aktivitas operasional masih dilakukan langsung oleh pemilik usaha, mulai dari menerima pesanan, bernegosiasi dengan pelanggan, melakukan pembelian bahan baku, memproduksi pesanan, hingga menyerahkan pesanan kepada pelanggan. Hal ini umum dijumpai pada UMKM makanan berskala rumahan yang belum memiliki pembagian tugas secara formal.

## 4. Current Business Process
### 4.1 Business Process Overview

Proses bisnis UMKM makanan dimulai ketika pelanggan menghubungi atau datang langsung untuk melakukan pemesanan. Sebelum pesanan diterima, pemilik usaha akan memastikan waktu pengambilan, mendiskusikan kebutuhan pelanggan, serta melakukan negosiasi apabila diperlukan. Setelah seluruh informasi disepakati, pemilik memeriksa ketersediaan bahan baku secara manual sebelum memulai proses produksi. Produk yang telah selesai diproduksi kemudian dikemas dan diserahkan kepada pelanggan sesuai waktu yang telah disepakati.

### 4.2 Business Process Description

Tabel berikut menjelaskan aktivitas utama yang dilakukan dalam proses bisnis UMKM makanan mulai dari pelanggan melakukan pemesanan hingga produk diterima oleh pelanggan. Setiap aktivitas menunjukkan pelaku yang terlibat, informasi yang digunakan sebagai masukan (input), serta hasil yang dihasilkan (output). Deskripsi ini disusun berdasarkan hasil observasi terhadap proses bisnis yang saat ini berjalan.

| No | Aktivitas                                           | Pelaku           | Input                     | Output                     |
| -- | --------------------------------------------------- | ---------------- | ------------------------- | -------------------------- |
| 1  | Customer menyampaikan keinginan melakukan pemesanan | Customer         | Kebutuhan pelanggan       | Permintaan pesanan         |
| 2  | Menentukan waktu pengambilan                        | Customer & Owner | Permintaan pesanan        | Jadwal pengambilan         |
| 3  | Customer memilih menu                               | Customer         | Daftar menu               | Menu yang dipilih          |
| 4  | Negosiasi harga                                     | Customer & Owner | Menu yang dipilih         | Harga disepakati           |
| 5  | Menentukan jumlah pesanan                           | Customer & Owner | Harga yang disepakati     | Detail jumlah pesanan      |
| 6  | Menentukan kustomisasi (isian/topping)              | Customer & Owner | Menu yang dipilih         | Detail produk              |
| 7  | Menyepakati metode pembayaran                       | Customer & Owner | Detail pesanan            | Kesepakatan pembayaran     |
| 8  | Konfirmasi pesanan                                  | Customer & Owner | Detail pesanan            | Pesanan dikonfirmasi       |
| 9  | Pemeriksaan bahan baku                              | Owner            | Pesanan yang dikonfirmasi | Status ketersediaan bahan  |
| 10 | Pembelian bahan baku (jika diperlukan)              | Owner            | Status ketersediaan bahan | Bahan baku tersedia        |
| 11 | Produksi pesanan                                    | Owner            | Bahan baku                | Produk selesai diproduksi  |
| 12 | Pengemasan produk                                   | Owner            | Produk selesai            | Produk siap dikirim        |
| 13 | Pengiriman atau penyerahan pesanan                  | Owner            | Produk siap dikirim       | Pesanan diterima pelanggan |

### 4.3 Current Business Process Diagram (AS-IS)

Diagram berikut menggambarkan proses bisnis UMKM makanan yang berjalan saat ini berdasarkan hasil observasi. Diagram ini menunjukkan alur aktivitas mulai dari pelanggan melakukan pemesanan hingga pesanan diterima pelanggan tanpa melibatkan sistem ERP.

<p align="center">
  <img src="../assets/diagrams/discovery/Current-Business-Process-Diagram.png" width="800">
</p>

<p align="center">
<b>Gambar 4.1.</b> Current Business Process (AS-IS)
</p>

### 4.4 Process Observations

Berdasarkan hasil observasi terhadap proses bisnis UMKM makanan, diperoleh beberapa temuan sebagai berikut.

- Sebagian besar aktivitas operasional masih dilakukan langsung oleh owner tanpa pembagian tugas yang formal.
- Proses penerimaan pesanan diawali dengan evaluasi kemampuan memenuhi permintaan pelanggan berdasarkan waktu pengambilan, ketersediaan bahan baku, dan kapasitas produksi.
- Harga, metode pembayaran, serta bentuk kustomisasi produk ditentukan melalui kesepakatan antara owner dan pelanggan.
- Pemeriksaan bahan baku masih dilakukan secara manual dengan memperhatikan jumlah stok, kondisi bahan, dan masa kedaluwarsa.
- Apabila bahan baku tidak mencukupi, owner akan melakukan pembelian bahan baku sebelum proses produksi dimulai.
- Produksi hanya dilakukan setelah seluruh detail pesanan disepakati.
- Produk dikirim atau diserahkan kepada pelanggan sesuai waktu yang telah disepakati.

## 5. Domain Language

### 5.1 Overview

Domain Language merupakan kumpulan istilah bisnis yang digunakan secara konsisten selama proses analisis, perancangan, implementasi, hingga pengujian sistem. Dokumen ini bertujuan menyamakan pemahaman antara istilah yang digunakan oleh pelaku bisnis dengan istilah yang digunakan pada implementasi sistem.

### 5.2 Domain Language Table

| Business Term | System Term | Definition |
|---------------|-------------|------------|
| Pelanggan | Customer | Orang yang melakukan pemesanan produk. |
| Owner | Owner | Pemilik UMKM yang mengelola operasional usaha. |
| Pesanan | Order | Permintaan produk yang diajukan pelanggan dan telah disepakati. |
| Produk | Product | Barang atau makanan yang dijual oleh UMKM. |
| Bahan | Raw Material | Material yang digunakan dalam proses produksi. |
| Stok | Inventory | Jumlah bahan yang tersedia untuk produksi. |
| Produksi | Production | Proses pembuatan produk sesuai pesanan. |
| Pengemasan | Packaging | Proses mengemas produk sebelum diserahkan kepada pelanggan. |
| Pengiriman | Delivery | Penyerahan produk kepada pelanggan melalui pengiriman atau pengambilan. |
| Pembayaran | Payment | Proses pembayaran pesanan sesuai kesepakatan. |
| Harga | Price | Nilai transaksi yang disepakati antara owner dan pelanggan. |
| Resep | Recipe | Komposisi bahan yang digunakan untuk menghasilkan suatu produk. |
| Kustomisasi | Customization | Perubahan produk sesuai permintaan pelanggan. |

### 5.3 Naming Convention

- Dokumentasi bisnis menggunakan Bahasa Indonesia.
- Antarmuka aplikasi menggunakan Bahasa Indonesia.
- Implementasi teknis seperti database, API, class, dan variabel menggunakan Bahasa Inggris.
- Seluruh penamaan mengikuti Domain Language yang telah ditetapkan pada dokumen ini.


## 6. Pain Points

### 6.1 Prioritized Pain Points

Priority	Pain Point	Business Impact
🔴 1	Jadwal produksi masih dikelola secara manual.	Risiko bentrok jadwal, keterlambatan produksi, dan penolakan pesanan karena kapasitas sulit diperkirakan.
🟠 2	Penyesuaian harga masih dilakukan secara manual berdasarkan negosiasi pelanggan.	Margin keuntungan sulit dipertahankan dan perhitungan harga membutuhkan waktu lebih lama.
🟡 3	Pemeriksaan stok bahan masih dilakukan secara manual.	Owner harus mengecek bahan satu per satu sebelum produksi sehingga proses menjadi lebih lambat.
🟡 4	Perhitungan kebutuhan bahan masih berdasarkan pengalaman.	Berpotensi terjadi kekurangan atau kelebihan pembelian bahan baku.
🟢 5	Riwayat pesanan pelanggan belum terdokumentasi dengan baik.	Sulit melihat histori pesanan dan preferensi pelanggan.
🟢 6	Status pembayaran belum tercatat secara terstruktur.	Owner harus mengingat sendiri apakah pesanan sudah dibayar, DP, atau belum dibayar.

### 6.2 Summary

Berdasarkan hasil observasi, permasalahan utama UMKM makanan bukan terletak pada proses produksi, melainkan pada proses pengambilan keputusan sebelum produksi dimulai. Penentuan jadwal produksi dan penyesuaian harga menjadi aktivitas yang paling sering memerlukan pertimbangan manual dari owner. Sementara itu, pengelolaan stok, kebutuhan bahan, riwayat pesanan, dan pembayaran masih dapat dilakukan secara manual meskipun belum terdokumentasi secara terstruktur.

## 7. Business Opportunities

### 7.1 Draft Business Opportunities

| Priority | Opportunity                                                                                   | Expected Benefit                                                            |
| :------: | --------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
|   🔴 1   | Sistem membantu menyusun jadwal produksi berdasarkan waktu pengambilan pesanan.               | Mengurangi benturan jadwal dan keterlambatan produksi.                      |
|   🟠 2   | Sistem membantu menghitung rekomendasi harga berdasarkan resep, bahan, dan target keuntungan. | Owner lebih mudah menentukan harga yang tetap menguntungkan saat negosiasi. |
|   🟡 3   | Sistem mencatat stok bahan baku secara otomatis setelah produksi.                             | Mengurangi pengecekan stok secara manual.                                   |
|   🟡 4   | Sistem menghitung kebutuhan bahan berdasarkan pesanan yang diterima.                          | Mengurangi kekurangan maupun kelebihan pembelian bahan.                     |
|   🟢 5   | Sistem menyimpan riwayat pesanan pelanggan.                                                   | Memudahkan pelayanan pelanggan yang pernah memesan sebelumnya.              |
|   🟢 6   | Sistem mencatat status pembayaran setiap pesanan.                                             | Memudahkan pemantauan pesanan yang sudah dibayar, DP, maupun belum dibayar. |

### 7.2 Opportunity Summary

ERP yang dikembangkan tidak hanya berfungsi sebagai sistem pencatatan transaksi, tetapi juga sebagai sistem pendukung pengambilan keputusan (Decision Support System) bagi owner UMKM. Sistem membantu owner dalam menentukan jadwal produksi, memperkirakan kebutuhan bahan baku, serta memberikan rekomendasi harga agar proses operasional menjadi lebih terstruktur dan efisien.

## 8. Open Questions

Beberapa aspek berikut masih memerlukan validasi lebih lanjut pada tahap analisis dan perancangan sistem.

| No | Open Question | Planned Resolution |
|----|---------------|-------------------|
| 1 | Bagaimana sistem menentukan estimasi waktu produksi setiap produk? | Ditentukan pada tahap perancangan modul Production Planning. |
| 2 | Bagaimana aturan rekomendasi harga dihitung? | Dibahas pada tahap Business Rules. |
| 3 | Bagaimana owner mengubah rekomendasi jadwal produksi? | Ditentukan pada tahap desain antarmuka (UI/UX). |
| 4 | Bagaimana sistem menangani perubahan pesanan setelah produksi dimulai? | Ditentukan pada tahap Requirement Analysis. |
| 5 | Bagaimana sistem menghitung kapasitas produksi harian? | Ditentukan pada tahap desain Production Planning. |

## 9. Scope Limitation

Ruang lingkup pengembangan ERP pada penelitian ini dibatasi pada:

- UMKM makanan dengan model Make-to-Order.
- Satu owner sebagai pengguna utama sistem.
- Tidak mendukung multi-cabang.
- Tidak mendukung multi-gudang.
- Tidak terintegrasi dengan marketplace.
- Tidak terintegrasi dengan sistem pembayaran digital.
- Tidak mendukung akuntansi dan laporan keuangan lengkap.
- Fokus pada proses pemesanan, produksi, bahan baku, dan pembayaran.

## 10. Discovery Summary

Berdasarkan hasil observasi terhadap UMKM makanan dengan model bisnis Make-to-Order, diperoleh pemahaman mengenai karakteristik bisnis, proses operasional, serta berbagai permasalahan yang dihadapi owner dalam menjalankan usaha. Permasalahan utama ditemukan pada proses penjadwalan produksi dan penyesuaian harga yang masih dilakukan secara manual berdasarkan pengalaman.

Hasil discovery menunjukkan bahwa sistem ERP yang akan dikembangkan tidak hanya berfungsi sebagai media pencatatan operasional, tetapi juga sebagai pendukung pengambilan keputusan dalam proses perencanaan produksi, pengelolaan bahan baku, serta penentuan harga. Seluruh hasil discovery ini menjadi dasar dalam penyusunan visi proyek, kebutuhan sistem, dan desain ERP pada tahap berikutnya.