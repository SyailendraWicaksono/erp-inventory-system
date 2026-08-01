# Testing

## Overview

### Purpose

Dokumen ini menjelaskan strategi pengujian yang digunakan untuk memastikan bahwa seluruh komponen sistem berjalan sesuai kebutuhan bisnis dan spesifikasi teknis.

---

### Scope

Pengujian dilakukan pada:

- Guest Ordering
- Android Application
- Backend API
- Database
- Integration Flow

---

## Testing Strategy

### Unit Testing

Pengujian dilakukan terhadap setiap fungsi secara terpisah.

---

### Integration Testing

Pengujian dilakukan terhadap interaksi antar modul.

---

### System Testing

Pengujian dilakukan terhadap sistem secara keseluruhan.

---

### User Acceptance Testing (UAT)

Pengujian dilakukan berdasarkan kebutuhan pengguna.

---

## Unit Testing

### Order Management

| Test ID | Description |
|----------|-------------|
| UT-001 | Create order |
| UT-002 | Update order |
| UT-003 | Confirm order |

---

### Production Planning

| Test ID | Description |
|----------|-------------|
| UT-004 | Create production schedule |
| UT-005 | Start production |
| UT-006 | Finish production |

---

### Inventory Management

| Test ID | Description |
|----------|-------------|
| UT-007 | Create raw material |
| UT-008 | Update stock |
| UT-009 | Record purchase |

---

### Payment Management

| Test ID | Description |
|----------|-------------|
| UT-010 | Create payment |
| UT-011 | Update payment status |

---

## Integration Testing

### Guest Ordering

| Test ID | Description |
|----------|-------------|
| IT-001 | Guest Ordering → API |
| IT-002 | API → Database |

---

### Production Flow

| Test ID | Description |
|----------|-------------|
| IT-003 | Order → Production |
| IT-004 | Production → Inventory |

---

### Payment Flow

| Test ID | Description |
|----------|-------------|
| IT-005 | Order → Payment |

---

## System Testing

### Functional Testing

| Test ID | Scenario | Expected Result |
|----------|----------|-----------------|
| ST-001 | Customer creates order | Order saved |
| ST-002 | Owner confirms order | Status updated |
| ST-003 | Owner starts production | Status updated |
| ST-004 | Owner records payment | Payment saved |

---

### Performance Testing

| Test ID | Scenario |
|----------|------------|
| PT-001 | Concurrent requests |
| PT-002 | Database performance |
| PT-003 | API response time |

---

### Security Testing

| Test ID | Scenario |
|----------|------------|
| SCT-001 | Authentication |
| SCT-002 | Authorization |
| SCT-003 | SQL injection |
| SCT-004 | Cross-site scripting |

---

## User Acceptance Testing

### Order Management

| Requirement | Result |
|-------------|---------|
| FR-001 | Pass |
| FR-002 | Pass |
| FR-003 | Pass |

---

### Production Planning

| Requirement | Result |
|-------------|---------|
| FR-016 | Pass |
| FR-017 | Pass |

---

### Inventory Management

| Requirement | Result |
|-------------|---------|
| FR-020 | Pass |
| FR-021 | Pass |

---

### Payment Management

| Requirement | Result |
|-------------|---------|
| FR-022 | Pass |
| FR-023 | Pass |

---

## Bug Classification

| Severity | Description |
|-----------|-------------|
| Critical | System unavailable |
| High | Main feature unavailable |
| Medium | Feature partially unavailable |
| Low | User interface issue |

---

## Exit Criteria

Sistem dianggap siap digunakan apabila:

- Seluruh unit testing berhasil dijalankan.
- Seluruh integration testing berhasil dijalankan.
- Seluruh system testing berhasil dijalankan.
- Seluruh user acceptance testing berhasil dijalankan.
- Tidak terdapat bug dengan tingkat keparahan kritis.

---