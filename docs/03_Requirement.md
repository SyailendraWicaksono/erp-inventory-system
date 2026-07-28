# Requirements

## 1. Overview

Dokumen ini mendefinisikan kebutuhan sistem (system requirements) untuk Hybrid ERP Mobile pada UMKM makanan dengan model bisnis Make-to-Order. Requirements disusun berdasarkan hasil Discovery, Project Vision, dan Business Process yang telah ditetapkan. Dokumen ini menjadi acuan dalam proses perancangan modul, arsitektur sistem, database, API, serta implementasi aplikasi.

## 2. Stakeholders

| Stakeholder | Description                                                           |
| ----------- | --------------------------------------------------------------------- |
| Customer    | Melakukan pemesanan melalui Guest Ordering tanpa registrasi akun.     |
| Owner       | Mengelola seluruh operasional bisnis menggunakan aplikasi ERP Mobile. |
| ERP System  | Mengelola proses bisnis, penyimpanan data, dan otomatisasi sistem.    |

## 3. Functional Requirements

### 3.1 Order Management

| ID     | Functional Requirement                                                   |
| ------ | ------------------------------------------------------------------------ |
| FR-001 | Customer dapat melihat daftar menu yang tersedia.                        |
| FR-002 | Customer dapat memilih menu yang ingin dipesan.                          |
| FR-003 | Customer dapat menentukan jumlah pesanan.                                |
| FR-004 | Customer dapat menentukan tanggal dan waktu pengambilan pesanan.         |
| FR-005 | Customer dapat menambahkan kustomisasi pesanan sesuai menu yang dipilih. |
| FR-006 | Customer wajib mengisi nama dan nomor HP sebelum mengirim pesanan.       |
| FR-007 | Customer dapat mengirim pesanan melalui Guest Ordering.                  |
| FR-008 | Owner dapat melihat daftar pesanan yang masuk.                           |
| FR-009 | Owner dapat melihat detail pesanan.                                      |
| FR-010 | Owner dapat memperbarui detail pesanan sebelum proses produksi dimulai.  |
| FR-011 | Owner dapat mengonfirmasi pesanan.                                       |
| FR-012 | Sistem memvalidasi data pesanan sebelum disimpan.                        |
| FR-013 | Sistem menyimpan data pesanan.                                           |
| FR-014 | Sistem menghasilkan nomor pesanan secara otomatis.                       |
| FR-015 | Sistem memperbarui status pesanan setelah owner melakukan konfirmasi.    |

### 3.2 Production Planning

| ID     | Functional Requirement                                                            |
| ------ | --------------------------------------------------------------------------------- |
| FR-016 | Owner dapat memeriksa ketersediaan bahan baku sebelum produksi.                   |
| FR-017 | Owner dapat menyusun jadwal produksi berdasarkan pesanan yang telah dikonfirmasi. |
| FR-018 | Owner dapat melakukan proses produksi.                                            |
| FR-019 | Sistem memperbarui status pesanan setelah proses produksi selesai.                |

### 3.3 Inventory Management

| ID     | Functional Requirement                                                   |
| ------ | ------------------------------------------------------------------------ |
| FR-020 | Owner dapat melakukan pembelian bahan baku apabila stok tidak mencukupi. |
| FR-021 | Sistem memperbarui data inventori selama proses operasional.             |

### 3.4 Payment Management

| ID     | Functional Requirement                   |
| ------ | ---------------------------------------- |
| FR-022 | Owner dapat mencatat pembayaran pesanan. |
| FR-023 | Sistem memperbarui status pembayaran.    |

### 3.5 Dashboard

| ID     | Functional Requirement                                 |
| ------ | ------------------------------------------------------ |
| FR-024 | Owner dapat melihat ringkasan jumlah pesanan.          |
| FR-025 | Owner dapat melihat ringkasan jadwal produksi.         |
| FR-026 | Owner dapat melihat ringkasan ketersediaan bahan baku. |
| FR-027 | Owner dapat melihat ringkasan status pembayaran.       |

## 4. Non-Functional Requirements

| ID      | Non-Functional Requirement                                                                           | Sumber                         |
| ------- | ---------------------------------------------------------------------------------------------------- | ------------------------------ |
| NFR-001 | Owner mengakses sistem melalui aplikasi Android.                                                     | Project Vision                 |
| NFR-002 | Customer dapat melakukan Guest Ordering melalui browser pada perangkat mobile tanpa registrasi akun. | Business Process Assumption    |
| NFR-003 | Sistem menyimpan seluruh data operasional secara terpusat pada database.                             | Project Vision                 |
| NFR-004 | Sistem menyediakan autentikasi bagi Owner sebelum mengakses fitur ERP.                               | Target Users                   |
| NFR-005 | Antarmuka sistem dirancang agar mudah digunakan pada perangkat mobile.                               | Project Vision                 |
| NFR-006 | Sistem mendukung pengelolaan lebih dari satu pesanan secara bersamaan.                               | Discovery (MTO & operasional)  |

## 5. Requirement Traceability

Requirement Traceability Matrix menunjukkan hubungan antara functional requirements dengan dokumen analisis yang telah disusun sebelumnya, yaitu Discovery, Project Vision, dan Business Process. Matriks ini digunakan untuk memastikan bahwa setiap kebutuhan sistem memiliki dasar yang jelas dan dapat ditelusuri (traceable) selama proses pengembangan.

| Requirement     | Discovery                          | Project Vision          | Business Process     |
| --------------- | ---------------------------------- | ----------------------- | -------------------- |
| FR-001 – FR-015 | ✔ Order Management & Make-to-Order | ✔ Order Management      | ✔ Order Process      |
| FR-016 – FR-019 | ✔ Production Scheduling            | ✔ Production Planning   | ✔ Production Process |
| FR-020 – FR-021 | ✔ Raw Material Inventory           | ✔ Inventory Management  | ✔ Inventory Process  |
| FR-022 – FR-023 | ✔ Payment Agreement                | ✔ Payment Management    | ✔ Payment Process    |
| FR-024 – FR-027 | —                                  | ✔ Operational Dashboard | —                    |
