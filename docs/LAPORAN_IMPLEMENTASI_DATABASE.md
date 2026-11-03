> Catatan: nama tim dan akun pada dokumen ini mengacu pada seeder sebelum commit 6ee1b45. Nama tim kanonik sekarang: Umum, Sosial, Produksi, Distribusi, NWAS, IPDS. Lihat docs/CATATAN_KEPUTUSAN.md bagian 10.

# Laporan Audit Akhir Implementasi Basis Data dan Kerangka Kerja Backend
**Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik — BPS Kota Palembang**  
**Tanggal Audit:** 28 Oktober 2026  
**Status Audit Keseluruhan:** **BERHASIL (Passed - 100%)**  
**Target Basis Data:** MySQL / MariaDB (XAMPP - `127.0.0.1:3306`) | **Nama Database:** `jadwal_bps`  
**Framework & Runtime:** Laravel Framework 12.70.2, PHP 8.2.12 (cli), Composer 2.8.11  

---

## 1. Matriks Status Audit Komprehensif

Seluruh komponen diperiksa secara langsung di lingkungan lokal dan diklasifikasikan ke dalam kategori status yang tegas:

| ID | Komponen Pemeriksaan | Status | Keterangan & Bukti Verifikasi |
|---|---|---|---|
| **A-01** | Keberadaan `PRD.md` dan struktur workspace | **BERHASIL** | `PRD.md` utuh (37.7 KB), folder `backend/`, `docs/`, `docs/database/` tersedia lengkap. |
| **A-02** | Dokumentasi Arsitektur Database di `docs/` | **BERHASIL** | 5 dokumen (`database-design.md`, `erd.md`, `database-constraints.md`, `migration-plan.md`, `CATATAN_KEPUTUSAN.md`) terisi lengkap dan konsisten dengan PRD. |
| **A-03** | Konfigurasi Koneksi Database (`backend/.env`) | **BERHASIL** | Terhubung ke `DB_DATABASE=jadwal_bps`, host `127.0.0.1:3306`, engine MariaDB 10.4.32. Kredensial sensitif diamankan dan tidak terekspos. Tersedia `backend/.env.example`. |
| **A-04** | Status Migrasi Laravel (`migrate:status`) | **BERHASIL** | 12 berkas migrasi tercatat Batch 1 dengan status `[1] Ran` tanpa error. |
| **A-05** | Skema 9 Tabel Domain Sesuai PRD Bagian 13 | **BERHASIL** | Seluruh 9 tabel domain terverifikasi di MySQL (`SHOW TABLES`, `DESCRIBE`): `users`, `teams`, `team_members`, `activities`, `assignments`, `weekly_issues`, `notifications`, `audit_logs`, `settings`. |
| **A-06** | Penegakan Primary Key & Tipe Data | **BERHASIL** | Seluruh tabel menggunakan `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`. |
| **A-07** | Ketentuan Soft Delete (SD) | **BERHASIL** | **Tepat 5 tabel** memiliki `deleted_at`: `users`, `teams`, `activities`, `assignments`, `weekly_issues`. Tabel `team_members`, `notifications`, `audit_logs`, dan `settings` **TIDAK** memiliki `deleted_at`. |
| **A-08** | Indeks Gabungan (Composite Index) Wajib | **BERHASIL** | 5 indeks komposit terverifikasi: `activities(team_id, mulai, selesai)`, `assignments(user_id, mulai, selesai)`, `weekly_issues(team_id, minggu_mulai, status)`, `notifications(user_id, dibaca_pada)`, `audit_logs(objek, objek_id)`. |
| **A-09** | Constraint Keunikan (Unique Constraints) | **BERHASIL** | Terverifikasi: `users.email` (UNIQUE), `team_members(team_id, user_id)` (UNIQUE), `settings.kunci` (UNIQUE). |
| **A-10** | Integritas Foreign Key Referensial | **BERHASIL** | Seluruh FK terhubung ke tabel induk dengan aksi `RESTRICT`, `CASCADE`, atau `SET NULL` yang melindungi dari data yatim (*orphaned data*). |
| **A-11** | Pemisahan Logika Beban Kerja & Status Kegiatan | **BERHASIL** | Tidak ada kolom statis `status` pada `activities` (dihitung dinamis via Accessor). Tidak ada kolom `poin`/`beban` pada `assignments` dan tidak ada database trigger (kalkulasi murni di backend). |
| **A-12** | Integritas Data Seeder (Dummy Data PRD Bagian 17)| **BERHASIL** | Data dummy tersimpan nyata: 17 users, 6 teams, 23 team_members, 14 activities, 31 assignments, 12 weekly_issues, 5 notifications, 4 audit_logs, 4 settings. Idempotensi seeder teruji aman. |
| **A-13** | Automated Feature Tests (`php artisan test`) | **BERHASIL** | 20 test cases lulus 100% (196 assertions), durasi 1.06s, exit code 0. |
| **A-14** | Kualitas Kode Backend (`vendor/bin/pint --test`)| **BERHASIL** | Seluruh berkas PHP memenuhi standar PSR-12, `result: passed`, exit code 0. |
| **A-15** | Fitur Bisnis Aplikasi (API Endpoints, UI React) | **BELUM DIVERIFIKASI / DI LUAR LINGKUP** | Sesuai PRD Bagian 19 (Fase 1–8), controller bisnis, form request API, dan UI frontend dijadwalkan pada fase berikutnya dan secara tegas tidak diklaim selesai pada tahap perancangan database ini. |

---

## 2. Struktur Workspace dan Berkas Penting

Pemeriksaan direktori menunjukkan struktur workspace bersih dan terorganisasi rapi:

```
Projek Aplikasi Penjadwalan/
├── PRD.md                             # Sumber kebenaran spesifikasi sistem
├── docs/                              # Dokumentasi arsitektur dan audit
│   ├── CATATAN_KEPUTUSAN.md           # Catatan keputusan arsitektural (ADR)
│   ├── LAPORAN_IMPLEMENTASI_DATABASE.md # Laporan audit akhir ini
│   └── database/
│       ├── database-design.md         # Kamus data (data dictionary) 9 tabel
│       ├── erd.md                     # Diagram ERD Mermaid & kardinalitas
│       ├── database-constraints.md    # Matriks constraint, FK, dan indeks
│       └── migration-plan.md          # Urutan migrasi & rencana rollback
└── backend/                           # Proyek Laravel 12 REST API
    ├── .env                           # Konfigurasi lokal terhubung ke jadwal_bps
    ├── .env.example                   # Templat konfigurasi lingkungan
    ├── phpunit.xml                    # Konfigurasi pengujian MySQL
    ├── app/
    │   ├── Enums/
    │   │   ├── PeranPengguna.php      # Enum: admin, pegawai, pimpinan
    │   │   └── StatusKendala.php      # Enum: terbuka, diproses, selesai
    │   └── Models/
    │       ├── User.php               # Model User (SD, Sanctum, Relasi)
    │       ├── Team.php               # Model Team (SD, Relasi ketua & anggota)
    │       ├── Activity.php           # Model Activity (SD, Accessor status)
    │       ├── Assignment.php         # Model Assignment (SD, Fallback tanggal)
    │       ├── WeeklyIssue.php        # Model WeeklyIssue (SD, CarriedOver scope)
    │       ├── Notification.php       # Model Notification (Non-SD, Unread scope)
    │       ├── AuditLog.php           # Model AuditLog (Non-SD, JSON array cast)
    │       └── Setting.php            # Model Setting (Non-SD, get/set helpers)
    ├── database/
    │   ├── factories/UserFactory.php  # Factory User untuk pengujian
    │   ├── migrations/                # 9 Migrasi domain + 3 migrasi sistem
    │   └── seeders/                   # 8 Seeders domain + DatabaseSeeder master
    └── tests/
        └── Feature/
            ├── DatabaseSchemaTest.php         # 10 automated schema tests
            └── DatabaseDataIntegrityTest.php  # 8 automated data integrity tests
```

---

## 3. Hasil Audit Koneksi dan Lingkungan Database

- **Koneksi Engine:** MySQL / MariaDB via PDO (`pdo_mysql`).
- **Versi Server:** `10.4.32-MariaDB` (XAMPP).
- **Nama Basis Data Aktif:** `jadwal_bps`.
- **Charset & Collation:** `utf8mb4` / `utf8mb4_unicode_ci`.
- **Hasil Verifikasi Laravel Tinker:**
  ```
  DB::connection()->getDatabaseName() => 'jadwal_bps'
  DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) => '10.4.32-MariaDB'
  ```

---

## 4. Hasil Audit Skema Database (9 Tabel Domain & Tabel Tambahan)

### A. Sembilan Tabel Domain Sesuai PRD Bagian 13
1. **`users` (SD):**
   - Kolom: `id` (PK), `nama` (VARCHAR 120), `email` (VARCHAR 150, UNIQUE), `password`, `peran` (ENUM 'admin','pegawai','pimpinan'), `nip` (VARCHAR 30, NULL), `jabatan` (VARCHAR 100, NULL), `aktif` (BOOL default 1), `must_change_password` (BOOL default 1), `remember_token`, `created_at`, `updated_at`, `deleted_at`.
2. **`teams` (SD):**
   - Kolom: `id` (PK), `nama` (VARCHAR 100), `ketua_id` (FK `users.id`, NULL, `ON DELETE SET NULL`), `aktif` (BOOL default 1), `created_at`, `updated_at`, `deleted_at`.
3. **`team_members` (Non-SD):**
   - Kolom: `id` (PK), `team_id` (FK `teams.id`, `ON DELETE CASCADE`), `user_id` (FK `users.id`, `ON DELETE CASCADE`), `created_at`, `updated_at`.
   - Indeks Unik: `unique_team_user` pada `(team_id, user_id)`. Tanpa `deleted_at`.
4. **`activities` (SD):**
   - Kolom: `id` (PK), `nama` (VARCHAR 200), `jenis` (VARCHAR 60), `team_id` (FK `teams.id`, `ON DELETE RESTRICT`), `mulai` (DATE), `selesai` (DATE), `bobot` (DECIMAL 5,2 default 1.00), `kegiatan_besar` (BOOL default 0), `selesai_manual` (BOOL default 0), `selesai_pada` (DATETIME, NULL), `keterangan` (TEXT, NULL), `created_at`, `updated_at`, `deleted_at`.
   - Indeks: `idx_activities_team_timeline` pada `(team_id, mulai, selesai)`.
   - **Bebas dari kolom `status` statis.**
5. **`assignments` (SD):**
   - Kolom: `id` (PK), `activity_id` (FK `activities.id`, `ON DELETE CASCADE`), `user_id` (FK `users.id`, `ON DELETE RESTRICT`), `peran` (VARCHAR 60), `volume` (DECIMAL 10,2), `satuan` (VARCHAR 30), `mulai` (DATE, NULL), `selesai` (DATE, NULL), `created_at`, `updated_at`, `deleted_at`.
   - Indeks: `idx_assignments_user_timeline` pada `(user_id, mulai, selesai)`.
   - **Bebas dari kolom rekap `poin` atau `beban`.**
6. **`weekly_issues` (SD):**
   - Kolom: `id` (PK), `activity_id` (FK `activities.id`, NULL, `ON DELETE SET NULL`), `team_id` (FK `teams.id`, `ON DELETE RESTRICT`), `pelapor_id` (FK `users.id`, `ON DELETE RESTRICT`), `minggu_mulai` (DATE), `kendala` (TEXT), `solusi` (TEXT, NULL), `tindak_lanjut` (TEXT, NULL), `pic_id` (FK `users.id`, NULL, `ON DELETE SET NULL`), `tenggat` (DATE, NULL), `status` (ENUM 'terbuka','diproses','selesai' default 'terbuka'), `catatan_penutupan` (TEXT, NULL), `ditutup_pada` (DATETIME, NULL), `created_at`, `updated_at`, `deleted_at`.
   - Indeks: `idx_weekly_issues_team_week_status` pada `(team_id, minggu_mulai, status)`.
7. **`notifications` (Non-SD):**
   - Kolom: `id` (PK), `user_id` (FK `users.id`, `ON DELETE CASCADE`), `tipe` (VARCHAR 40), `pesan` (VARCHAR 255), `tautan` (VARCHAR 255, NULL), `dibaca_pada` (DATETIME, NULL), `created_at`, `updated_at`.
   - Indeks: `idx_notifications_user_read` pada `(user_id, dibaca_pada)`. Tanpa `deleted_at`.
8. **`audit_logs` (Non-SD):**
   - Kolom: `id` (PK), `user_id` (FK `users.id`, NULL, `ON DELETE SET NULL`), `aksi` (VARCHAR 40), `objek` (VARCHAR 60), `objek_id` (BIGINT), `sebelum` (JSON / LONGTEXT, NULL), `sesudah` (JSON / LONGTEXT, NULL), `created_at`, `updated_at`.
   - Indeks: `idx_audit_logs_object` pada `(objek, objek_id)`. Tanpa `deleted_at`.
9. **`settings` (Non-SD):**
   - Kolom: `id` (PK), `kunci` (VARCHAR 60, UNIQUE), `nilai` (VARCHAR 255), `created_at`, `updated_at`. Tanpa `deleted_at`.

### B. Tabel Tambahan dari Framework Laravel / Sanctum dan Fungsinya
Tabel-tabel berikut merupakan infrastruktur standar ekosistem Laravel yang aman dan diperlukan untuk operasional backend:
- `password_reset_tokens`: Menyimpan token reset kata sandi sementara (Laravel Auth).
- `sessions`: Menyimpan sesi pengguna saat driver session diset ke database atau saat mode Sanctum SPA aktif.
- `cache` & `cache_locks`: Penyimpanan cache performa tinggi untuk agregasi data kalender dan filter beban kerja.
- `jobs`, `job_batches`, & `failed_jobs`: Antrean proses latar belakang (misal pengiriman notifikasi pengingat jatuh tempo via scheduled command `notify:due-issues`).
- `personal_access_tokens`: Tabel token autentikasi Laravel Sanctum untuk mobile/API token bila diperlukan.
- `migrations`: Tabel internal pelacak histori eksekusi migrasi basis data.

---

## 5. Hasil Audit Rekod Data Seeder di Database Nyata

Pemeriksaan data langsung pada database `jadwal_bps` menghasilkan verifikasi sebagai berikut:

1. **Pengguna (`users`): 17 rekod**
   - 1 Admin: `admin@contoh.test` (`must_change_password = 0`).
   - 1 Pimpinan: `kepala@contoh.test` (`must_change_password = 0`, akun demo siap login).
   - 5 Ketua Tim (Pegawai): `ketua1@contoh.test` s.d. `ketua5@contoh.test`.
   - 10 Staf Pegawai: `pegawai01@contoh.test` s.d. `pegawai10@contoh.test`.
   - Kata sandi dummy seluruh akun fiktif: `password` (bcrypt hash).
2. **Tim Kerja & Anggota (`teams` & `team_members`): 6 tim, 23 relasi anggota**
   - Tim Sosial: dipimpin `ketua1@contoh.test`, 4 anggota.
   - Tim Produksi: dipimpin `ketua2@contoh.test`, 4 anggota.
   - Tim Distribusi: dipimpin `ketua3@contoh.test`, 4 anggota.
   - Tim Neraca dan Analisis: dipimpin `ketua4@contoh.test`, 4 anggota.
   - Tim IPDS: dipimpin `ketua5@contoh.test`, 3 anggota.
   - Tim Pelayanan Statistik: dipimpin oleh `kepala@contoh.test` (Pimpinan merangkap ketua tim, sesuai PRD Bagian 5 & 17), 4 anggota.
   - Pegawai seperti `pegawai01`, `pegawai02`, `pegawai05`, `pegawai09`, `pegawai10` terdaftar ganda di 2 tim kerja yang berbeda.
3. **Kegiatan Statistik (`activities`): 14 kegiatan**
   - 3 Kegiatan Besar beririsan: Susenas Modul, Survei Pertanian Terpadu II, Survei Harga Konsumen.
   - 1 Kegiatan Terlambat otomatis: Sakernas Triwulan III (selesai 15 Okt 2026, status dinamis `Terlambat`).
   - 1 Kegiatan Selesai manual: Publikasi Daerah Dalam Angka (`selesai_manual = 1`, status `Selesai`).
   - 1 Kegiatan Belum Mulai: Survei Kepuasan Konsumen (mulai 10 Nov 2026, status `Belum mulai`).
   - 11 Kegiatan Berjalan (status dinamis `Berjalan`).
4. **Penugasan Pegawai (`assignments`): 31 penugasan**
   - Kasus Uji PRD 8.1: Pegawai 01 pada Susenas Modul (volume 60 Dokumen, bobot 1.50, rentang 1 Okt s.d. 15 Nov 2026).
   - Kasus Beban Berlebih: Pegawai 02 memiliki akumulasi beban > 100 poin di bulan Oktober.
   - Kasus Peringatan Jadwal Menumpuk: Pegawai 03 memiliki 3 penugasan beririsan di bulan Oktober.
   - Kasus Fallback Periode: Penugasan desain tata letak publikasi dengan `mulai = null` dan `selesai = null` otomatis mewarisi tanggal kegiatan.
   - Kasus Peran Ganda: Ketua Tim 01 memiliki 2 peran berbeda (PML dan Pemeriksa Administrasi) pada kegiatan Susenas yang sama.
5. **Kendala Mingguan (`weekly_issues`): 12 kendala**
   - Status: 5 Terbuka, 4 Diproses, 3 Selesai (dengan catatan dan waktu penutupan).
   - Kasus Carried-Over: 4 kendala berstatus belum selesai dengan `minggu_mulai` mendahului 26 Oktober 2026 terdeteksi via scope `carriedOver`.
   - Kasus Overdue: Kendala pengembalian dokumen sensus dengan tenggat 23 Oktober 2026 terdeteksi `isOverdue() = true`.
6. **Notifikasi (`notifications`): 5 rekod**
   - 3 notifikasi belum dibaca (`dibaca_pada = null`).
   - 2 notifikasi sudah dibaca.
7. **Riwayat Mutasi (`audit_logs`): 4 rekod**
   - Mutasi kegiatan, pengaturan, dan penugasan dengan format JSON array `sebelum` dan `sesudah`.
8. **Pengaturan Sistem (`settings`): 4 rekod**
   - `ambang_beban_bulanan` = `'100'`
   - `maks_penugasan_bersamaan` = `'3'`
   - `maks_kegiatan_besar_bersamaan` = `'3'`
   - `pegawai_boleh_isi_kendala` = `'false'`

---

## 6. Hasil Pengujian Otomatis dan Kualitas Kode

### A. Automated Feature Tests (`php artisan test`)
- **Perintah:** `cd backend; php artisan test`
- **Exit Code:** `0`
- **Waktu Eksekusi:** 1.06 detik
- **Jumlah Pengujian:** **20 passed (100%), 0 failed, 0 skipped**
- **Jumlah Assertion:** **196 assertions**

```
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\DatabaseDataIntegrityTest
  ✓ users seeder data integrity                                            0.16s
  ✓ teams seeder data integrity                                            0.04s
  ✓ activities seeder data and status logic                                0.03s
  ✓ assignments seeder data integrity                                      0.03s
  ✓ weekly issues seeder data and scopes                                   0.01s
  ✓ settings seeder default values                                         0.03s
  ✓ notifications and audit logs exist                                     0.03s
  ✓ soft deletes functionality                                             0.09s

   PASS  Tests\Feature\DatabaseSchemaTest
  ✓ all nine domain tables exist                                           0.03s
  ✓ users table has expected columns                                       0.06s
  ✓ teams table has expected columns                                       0.04s
  ✓ team members table has expected columns                                0.05s
  ✓ activities table has expected columns                                  0.06s
  ✓ assignments table has expected columns                                 0.06s
  ✓ weekly issues table has expected columns                               0.07s
  ✓ non soft delete tables do not have deleted at                          0.04s
  ✓ mandatory composite indexes exist                                      0.03s
  ✓ unique constraints exist                                               0.03s

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                          0.04s

  Tests:    20 passed (196 assertions)
  Duration: 1.06s
```

### B. Standardisasi Gaya Kode (`vendor/bin/pint --test`)
- **Perintah:** `cd backend; ./vendor/bin/pint --test`
- **Exit Code:** `0`
- **Hasil:**
  ```json
  {"tool":"pint","result":"passed"}
  ```
- Seluruh 34 berkas PHP domain, migrasi, model, seeder, dan pengujian mematuhi standar PSR-12 tanpa pengecualian.

---

## 7. Rekomendasi Tahap Pengembangan Selanjutnya

Basis data telah terbukti kokoh, terstruktur, tervalidasi 100%, dan siap menjadi fondasi pengembangan layer aplikasi. Berdasarkan urutan pengerjaan pada Bagian 19 PRD, tahapan pengembangan berikutnya yang direkomendasikan adalah:

1. **Fase 1 (Penyelesaian Auth Sanctum SPA):**
   - Pembuatan endpoint autentikasi: `POST /api/login`, `POST /api/logout`, `GET /api/me`, `POST /api/change-password`.
   - Penegakan aturan login: pengguna nonaktif (`aktif = false`) ditolak, akun dengan `must_change_password = true` diarahkan untuk ubah kata sandi terlebih dahulu.
2. **Fase 2 (Manajemen Pengguna, Tim, dan Pengaturan):**
   - Pembuatan Form Request, Policy, API Resource, dan Controller untuk `/api/users`, `/api/teams`, `/api/teams/{id}/members`, dan `/api/settings`.
3. **Fase 3 & 4 (Kegiatan, Penugasan, dan `WorkloadService`):**
   - Implementasi kelas layanan tunggal `backend/app/Services/WorkloadService.php` untuk kalkulasi poin bulanan proporsional dan deteksi otomatis 3 jenis peringatan jadwal menumpuk.
