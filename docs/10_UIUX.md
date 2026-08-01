# UI/UX Design

## Overview

### Purpose

Dokumen ini menjelaskan rancangan antarmuka pengguna (User Interface) dan pengalaman pengguna (User Experience) yang digunakan pada Hybrid ERP Mobile untuk UMKM makanan dengan model bisnis Make-to-Order (MTO).

Dokumen ini bertujuan untuk memastikan seluruh interaksi pengguna berjalan secara konsisten, mudah dipahami, dan sesuai dengan proses bisnis yang telah ditentukan.

### Scope

Dokumen ini mencakup:

- Guest Ordering Interface
- Android Application Interface
- Navigation Structure
- Screen Design
- User Interaction
- Responsive Design

---

## Design Principles

### Simplicity

Antarmuka dirancang agar mudah digunakan oleh owner tanpa memerlukan pelatihan khusus.

### Consistency

Seluruh komponen menggunakan pola desain yang seragam.

### Accessibility

Seluruh fitur dapat digunakan dengan mudah melalui perangkat seluler.

### Efficiency

Jumlah langkah yang diperlukan untuk menyelesaikan suatu proses diminimalkan.

---

## User Roles

### Customer

Customer menggunakan Guest Ordering untuk:

- Melihat menu.
- Membuat pesanan.
- Menentukan jadwal pengambilan.
- Menambahkan kustomisasi.
- Mengirim pesanan.

---

### Owner

Owner menggunakan aplikasi Android untuk:

- Mengelola pesanan.
- Mengelola produksi.
- Mengelola inventori.
- Mengelola pembayaran.
- Melihat dashboard.

---

## Navigation Structure

### Customer Navigation

```text
Home
 ├── Menu List
 ├── Product Detail
 ├── Cart
 ├── Checkout
 └── Success
```

---

### Owner Navigation

```text
Splash Screen
        │
        ▼
Login
        │
        ▼
Dashboard
        │
        ├── Orders
        ├── Production
        ├── Inventory
        ├── Payments
        ├── Products
        └── Profile
```

---

## Guest Ordering Design

### Home Screen

Komponen utama:

- Logo
- Banner
- Search bar
- Product category
- Product list

---

### Product Detail Screen

Komponen utama:

- Product image
- Product name
- Product description
- Product price
- Quantity selector
- Customization section

---

### Cart Screen

Komponen utama:

- Product list
- Quantity
- Subtotal
- Total price

---

### Checkout Screen

Komponen utama:

- Customer name
- Phone number
- Pickup schedule
- Notes
- Payment information

---

### Success Screen

Komponen utama:

- Order number
- Confirmation message
- Pickup information

---

## Android Application Design

### Splash Screen

Komponen utama:

- Application logo
- Application name

---

### Login Screen

Komponen utama:

- Email field
- Password field
- Login button

---

### Dashboard Screen

Komponen utama:

- Total orders
- Production schedule
- Inventory status
- Payment status
- Daily summary

---

### Order Screen

Komponen utama:

- Search bar
- Order list
- Filter button
- Order status

---

### Order Detail Screen

Komponen utama:

- Customer information
- Product information
- Customization details
- Payment information
- Order status

---

### Production Screen

Komponen utama:

- Production list
- Production schedule
- Production status

---

### Inventory Screen

Komponen utama:

- Material list
- Available stock
- Unit information
- Expiration date

---

### Payment Screen

Komponen utama:

- Payment list
- Payment status
- Payment history

---

### Product Screen

Komponen utama:

- Product list
- Recipe information
- Product customization

---

### Profile Screen

Komponen utama:

- Profile information
- Change password
- Logout

---

## User Interaction Design

### Customer Flow

```text
Home
   │
   ▼
Product Detail
   │
   ▼
Cart
   │
   ▼
Checkout
   │
   ▼
Success
```

---

### Owner Flow

```text
Login
   │
   ▼
Dashboard
   │
   ▼
Orders
   │
   ▼
Production
   │
   ▼
Inventory
   │
   ▼
Payments
```

---

## Responsive Design

### Mobile Device

| Item | Value |
|------|--------|
| Orientation | Portrait |
| Platform | Android |
| Minimum Width | 360 dp |

---

### Tablet Device

| Item | Value |
|------|--------|
| Orientation | Landscape |
| Platform | Android |
| Minimum Width | 600 dp |

---

## Design System

### Typography

| Element | Style |
|----------|--------|
| Heading | Bold |
| Subtitle | Medium |
| Body Text | Regular |

---

### Color Palette

| Component | Usage |
|------------|--------|
| Primary Color | Main component |
| Secondary Color | Secondary component |
| Success Color | Success notification |
| Warning Color | Warning notification |
| Error Color | Error notification |

---

### Spacing System

| Type | Size |
|------|------|
| Small | 8 dp |
| Medium | 16 dp |
| Large | 24 dp |

---