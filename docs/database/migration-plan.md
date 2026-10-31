# Rencana dan Urutan Migrasi Basis Data (Migration Plan)
**Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik — BPS Kota Palembang**  
**Sumber Spesifikasi:** `PRD.md` Bagian 9, 10, & 13  

---

## 1. Urutan Dependensi dan Eksekusi Migrasi

Urutan eksekusi migrasi harus dirancang secara ketat agar setiap tabel referensi (*parent table*) sudah terbentuk sebelum tabel yang memiliki foreign key (*child table*) dibuat.

```
1. users             (Independen - Parent utama)
   ↓
2. teams             (Memiliki FK ketua_id -> users.id)
   ↓
3. team_members      (Pivot - FK team_id -> teams.id, FK user_id -> users.id)
   ↓
4. activities        (FK team_id -> teams.id)
   ↓
5. assignments       (FK activity_id -> activities.id, FK user_id -> users.id)
   ↓
6. weekly_issues     (FK activity_id -> activities.id, FK team_id -> teams.id, FK pelapor_id -> users.id, FK pic_id -> users.id)
   ↓
7. notifications     (FK user_id -> users.id)
   ↓
8. audit_logs        (FK user_id -> users.id)
   ↓
9. settings          (Independen)
```

---

## 2. Rincian Berkas Migrasi Laravel

Berikut penamaan terurut berkas migrasi pada direktori `backend/database/migrations/`:

| No | Nama Berkas Migrasi | Tabel Dibuat | Dependensi Foreign Key |
|---|---|---|---|
| 01 | `2026_10_28_000001_create_users_table.php` | `users` | - |
| 02 | `2026_10_28_000002_create_teams_table.php` | `teams` | `users.id` |
| 03 | `2026_10_28_000003_create_team_members_table.php` | `team_members` | `teams.id`, `users.id` |
| 04 | `2026_10_28_000004_create_activities_table.php` | `activities` | `teams.id` |
| 05 | `2026_10_28_000005_create_assignments_table.php` | `assignments` | `activities.id`, `users.id` |
| 06 | `2026_10_28_000006_create_weekly_issues_table.php` | `weekly_issues` | `activities.id`, `teams.id`, `users.id` |
| 07 | `2026_10_28_000007_create_notifications_table.php` | `notifications` | `users.id` |
| 08 | `2026_10_28_000008_create_audit_logs_table.php` | `audit_logs` | `users.id` |
| 09 | `2026_10_28_000009_create_settings_table.php` | `settings` | - |

*(Catatan: Migrasi bawaan Laravel untuk sesi dan token personal jika diperlukan oleh Laravel Sanctum disiapkan secara harmonis tanpa mengganggu tabel domain di atas).*

---

## 3. Strategi Metode `down()` (Rollback)

Setiap berkas migrasi wajib menyertakan implementasi metode `down()` yang simetris dan aman:
- `down()` mengeksekusi `Schema::dropIfExists('nama_tabel');`
- Urutan rollback Laravel adalah kebalikan dari timestamp pembuatan migrasi (`9 -> 8 -> ... -> 1`), sehingga tidak akan terjadi pelanggaran constraint foreign key saat rollback dijalankan.

---

## 4. Catatan Teknis Lingkungan MySQL / MariaDB (XAMPP)

1. **Storage Engine**: Wajib menggunakan `InnoDB` agar constraint foreign key dan transaksi ACID aktif.
2. **Charset & Collation**:
   - Database default: `utf8mb4` dengan collation `utf8mb4_unicode_ci`.
   - Panjang VARCHAR indexable: `email` dibatasi `VARCHAR(150)` dan `kunci` dibatasi `VARCHAR(60)`, jauh di bawah batas maksimum index prefix key 767 bytes / 3072 bytes InnoDB.
3. **Tipe Data JSON**:
   - Kolom `sebelum` dan `sesudah` pada `audit_logs` menggunakan `$table->json(...)`.
   - Di MariaDB, tipe ini otomatis dipetakan ke `LONGTEXT` dengan constraint check `JSON_VALID()`, yang sepenuhnya transparan bagi Laravel Eloquent.

