# Deployment

## Overview

### Purpose

Dokumen ini menjelaskan proses deployment Hybrid ERP Mobile untuk UMKM makanan dengan model bisnis Make-to-Order (MTO).

Deployment dilakukan untuk memastikan seluruh komponen sistem dapat berjalan secara terintegrasi dalam lingkungan produksi.

---

### Scope

Dokumen ini mencakup beberapa komponen berikut:

- Guest Ordering Web
- Android Application
- Backend API
- Database Server
- Reverse Proxy
- Monitoring System

---

## Deployment Architecture

### High-Level Architecture

```text
                     Customer
                         │
                         ▼
                Guest Ordering
                         │
                         ▼
                     Nginx
                         │
                         ▼
                   Laravel API
                         │
                         ▼
                   PostgreSQL
                         │
                         ▼
                  Android App
                         │
                         ▼
                      Owner
```

---

### Component Architecture

| Component | Technology |
| ---------- | ---------- |
| Guest Ordering | Laravel Blade |
| Mobile Application | Kotlin |
| Backend API | Laravel |
| Database | PostgreSQL |
| Web Server | Nginx |
| Operating System | Ubuntu Server |

---

## Development Environment

### Hardware Specification

| Component | Specification |
| ---------- | -------------- |
| Processor | Ryzen 7 |
| Memory | 12 GB |
| Storage | SSD |
| Operating System | Windows |

---

### Software Specification

| Component | Version |
| ---------- | -------- |
| PHP | 8.3 |
| Laravel | 13 |
| PostgreSQL | 17 |
| Android Studio | Latest |
| Composer | Latest |
| Git | Latest |

---

## Local Development Environment

### Backend

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

---

### Database

```bash
psql -U postgres
CREATE DATABASE erp_mobile;
```

---

### Android Application

```bash
gradlew build
```

---

### Guest Ordering

```bash
npm install
npm run dev
```

---

## Environment Variables

### Laravel

```env
APP_NAME=ERP_MOBILE
APP_ENV=production
APP_KEY=
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_mobile
DB_USERNAME=postgres
DB_PASSWORD=password

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

---

## Production Environment

### Virtual Private Server (VPS)

| Component | Specification |
| ---------- | ---------- |
| CPU | 2 Core |
| Memory | 4 GB |
| Storage | 80 GB |
| Operating System | Ubuntu |

---

### Database Server

| Component | Value |
| ---------- | ----- |
| Database Engine | PostgreSQL |
| Port | 5432 |
| Backup Schedule | Daily |

---

## Deployment Workflow

### Backend Deployment

```bash
git pull origin main

composer install --no-dev

php artisan config:cache

php artisan route:cache

php artisan migrate

php artisan optimize
```

---

### Database Deployment

```bash
php artisan migrate --force
```

---

### Android Deployment

```text
Android Studio
        │
        ▼
Release Build
        │
        ▼
APK / AAB
        │
        ▼
Google Play Store
```

---

### Guest Ordering Deployment

```text
GitHub
   │
   ▼
VPS
   │
   ▼
Nginx
   │
   ▼
Laravel
```

---

## Security Configuration

### Authentication

- Laravel Sanctum
- Password hashing
- Session management

---

### API Security

- HTTPS
- Request validation
- Authentication token
- Rate limiting

---

### Database Security

- Database backup
- Access control
- Encryption
- Firewall

---

## Backup Strategy

### Database Backup

```bash
pg_dump erp_mobile > backup.sql
```

---

### File Backup

```bash
tar -czf backup.tar.gz storage
```

---

## Monitoring Strategy

### System Monitoring

- CPU usage
- Memory usage
- Storage usage
- Database performance

---

### Application Monitoring

- API response time
- Failed request count
- Database query performance
- Error logging

---

## Logging Strategy

### Application Log

```text
storage/logs/laravel.log
```

---

### Database Log

```text
postgresql.log
```

---

### Web Server Log

```text
nginx/access.log
nginx/error.log
```

---