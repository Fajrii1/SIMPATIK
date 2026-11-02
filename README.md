# SIMPATIK — Sistem Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang

Aplikasi web untuk menyusun jadwal kegiatan statistik lintas tim, menugaskan pegawai, menghitung beban kerja otomatis, memantau status kegiatan, serta mencatat kendala dan tindak lanjut mingguan. Dikembangkan sebagai output penelitian dengan data dummy.

## Teknologi

- Backend: Laravel 12, PHP 8.2, Laravel Sanctum, REST API, Laravel Excel, DomPDF
- Frontend: React 19, TypeScript, Vite, Tailwind CSS 4, FullCalendar, Recharts
- Database: MySQL/MariaDB

Struktur repo: satu repo dengan dua folder `backend/` dan `frontend/`.

## Persyaratan

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL/MariaDB (XAMPP dapat dipakai)

## Setup Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Sesuaikan `.env` (DB_DATABASE, DB_USERNAME, DB_PASSWORD). Buat database `jadwal_bps` (atau sesuai konfigurasi), lalu:

```bash
php artisan migrate
php artisan db:seed
# Opsional, untuk data demo sesuai PRD:
php artisan db:seed --class=DemoDataSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

Catatan: `db:seed` minimal hanya membuat admin, pimpinan, dan 6 tim. Data dummy dijalankan melalui `DemoDataSeeder`.

## Setup Frontend

```bash
cd frontend
npm install
npm run dev
```

Frontend berjalan di `http://localhost:5173` dan mem-proxy `/api` ke backend `http://127.0.0.1:8000`.

## Akun Pengujian

Seeder menyediakan akun demo (kata sandi `password`):

- `admin@contoh.test` — Admin
- `pimpinan@contoh.test` — Pimpinan (jika tersedia di seeder)
- akun ketua tim dan pegawai lain sesuai seeder

Periksa `database/seeders/UserSeeder.php` untuk daftar lengkap.

## Role dan Hak Akses (ringkas)

| Fitur | Admin | Ketua Tim | Pegawai | Pimpinan |
|---|---|---|---|---|
| Kelola pengguna/tim | Ya | Tidak | Tidak | Tidak |
| Tambah/edit kegiatan | Semua tim | Tim yang dipimpin | Tidak | Tidak |
| Ekspor rekap | Ya | Tim yang dipimpin | Tidak | Ya |
| Lihat kalender | Semua tim | Semua tim | Semua tim (lihat) | Semua tim |
| Lihat beban kerja | Semua | Semua | Hanya miliknya | Semua |
| Laporan kendala | Ya | Timnya | Bila pengaturan mengizinkan (timnya) | Tidak |

Hak akses dicek di backend; frontend hanya menyesuaikan tampilan.

## Rumus Beban Kerja

Rumus dasar (PRD):

```
Total Points = Volume × Weight
```

Implementasi hanya ada di `backend/app/Services/WorkloadService.php`. Perhitungan memperhatikan distribusi per hari penugasan (periode efektif penugasan), kegiatan lintas bulan/tahun, dan aturan kegiatan besar. Frontend hanya menampilkan hasil API.

## Struktur Folder

```
backend/
  app/Http/Controllers/Api/   # Activity, Assignment, Calendar, Dashboard, Issue, Report, ...
  app/Services/WorkloadService.php
  database/migrations/
  database/seeders/
  tests/Feature/
frontend/
  src/pages/                  # Calendar*, Dashboard, Issues, Reports, ...
  src/pages/calendarUtils.ts
  src/pages/calendarTypes.ts
  src/types/
```

## Dokumentasi Lain

- `PRD.md` — kebutuhan produk dan aturan implementasi.
- `PANDUAN_INSTALASI_DAN_PENGGUNAAN.md` — panduan instalasi detail.
- `docs/` (jika ada) — catatan keputusan dan laporan fase.

## Pengujian

Backend (gunakan database pengujian terpisah `jadwal_bps_test`):

```bash
cd backend
# buat DB jadwal_bps_test, lalu:
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoDataSeeder
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

Verifikasi browser: buka `http://localhost:5173`, login, uji kalender (heatmap/timeline/bulanan/ringkasan/rekap), tambah/detail kegiatan, filter, dashboard, laporan, dan kendala mingguan.

## Catatan Pengembangan

- `end` pada FullCalendar bersifat eksklusif; jangan mengubah aturan tanggal.
- Jangan menjalankan `migrate:fresh`/`db:wipe` pada data yang sudah ada.
- Semua perubahan belum di-commit; lihat `git status`.