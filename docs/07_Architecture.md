# Architecture

## 1. Overview

Sistem menggunakan arsitektur client–server. Customer mengakses Guest Ordering berbasis web, sedangkan Owner menggunakan aplikasi Android. Keduanya berkomunikasi melalui Backend API yang terhubung dengan database terpusat.

## 2. System Architecture

<p align="center">
  <img src="../assets/diagrams/Architecture/System Architecture.png" width="800">
</p>

<p align="center">
<b>Gambar 4.1.</b> Architecture
</p>

## 3. Component Description

| Component          | Responsibility              |
| ------------------ | --------------------------- |
| Guest Ordering Web | Menerima pesanan pelanggan  |
| Android App        | Mengelola operasional usaha |
| Backend API        | Memproses logika bisnis     |
| Database           | Menyimpan data operasional  |

## 4. Data Flow
<p align="center">
  <img src="../assets/diagrams/Architecture/Data Flow Diagram Lvl 0.png" width="800">
</p>

<p align="center">
<b>Gambar 4.2.</b> DFD Lvl 0
</p>

<p align="center">
  <img src="../assets/diagrams/Architecture/Data Flow Diagram Lvl 1.png" width="800">
</p>

<p align="center">
<b>Gambar 4.3.</b> DFD Lvl 1
</p>
## 5. Technology Stack

| Layer          | Technology                     |
| -------------- | ------------------------------ |
| Android        | Kotlin + Jetpack Compose       |
| Backend API    | Laravel                        |
| Database       | PostgreSQL                     |
| Guest Ordering | Blade Laravel **atau** Next.js |
