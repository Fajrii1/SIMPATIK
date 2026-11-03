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

Akun awal dari `db:seed` (kata sandi `password`):

- `admin@contoh.test` — Admin
- `kepala@contoh.test` — Pimpinan

Akun `ketua1@contoh.test` sampai `ketua5@contoh.test` dan `pegawai01@contoh.test` sampai `pegawai10@contoh.test` hanya tersedia setelah menjalankan `DemoDataSeeder`.

Tim kanonik: Umum, Sosial, Produksi, Distribusi, NWAS, IPDS.

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

Catatan: "Ketua Tim" bukan peran tersendiri, melainkan pegawai atau pimpinan yang tercatat sebagai `teams.ketua_id`.

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
- `docs/PANDUAN_INSTALASI_DAN_PENGGUNAAN.md` — panduan instalasi detail.
- `docs/` (jika ada) — catatan keputusan dan laporan fase.

## Perintah Terjadwal (Scheduler)

Perintah artisan `notify:due-issues` mengirim notifikasi kendala yang tenggatnya sudah lewat dan belum selesai ke PIC (bila ada) dan ketua tim pemilik tim. Maksimal satu notifikasi per kendala per hari per penerima, dan kendala selesai tidak menyimpan antrean.

```bash
cd backend
php artisan notify:due-issues
```

Perintah ini dijadwalkan setiap hari pukul 07:00 WIB melalui `routes/console.php`:

```php
Schedule::command('notify:due-issues')->dailyAt('07:00')->timezone('Asia/Jakarta');
```

Laravel tidak menjalankan scheduler dengan sendirinya; sistem operasi harus memanggil `schedule:run` tiap menit.

Cara menjalankan di Windows dengan XAMPP:

1. Buat skrip `sched.bat` di folder XAMPP Anda (misal `C:\xampp\simpatik-scheduler.bat`):

   ```bat
   @echo off
   C:\xampp\php\php.exe "C:\path\ke\repo\backend\artisan" schedule:run >> C:\xampp\simpatik-scheduler.log 2>&1
   ```

   Ganti `C:\path\ke\repo\backend` dengan lokasi repo proyek ini. Bila PHP di terminal Anda sudah bekerja, boleh memakai `php` alih-alih `C:\xampp\php\php.exe`.

2. Buka Task Scheduler → Create Task… (bukan Basic Task, agar interval menit bisa diatur).
3. Pada tab General, nama tugas: `SIMPATIK Scheduler`. Centang opsi yang diperlukan (mis. "Run whether user is logged on or not").
4. Pada tab Triggers, New… → Daily, start time sembarang, centang "Repeat task every: 1 minutes", dan pilih durasi "indefinitely".
5. Pada tab Actions, New… → Start a program → isi `C:\xampp\simpatik-scheduler.bat`.
6. OK dan beri kredensial bila diminta.

Ringkas jalurnya: `Windows Task Scheduler` → memanggil `php artisan schedule:run` tiap menit → Laravel menjalankan `notify:due-issues` hanya bila sekarang waktunya 07:00 Asia/Jakarta → kendala yang terlambat mendapat notifikasi.

Untuk pengujian tanpa menunggu pukul 07:00, jalankan perintah langsung:

```bash
php artisan notify:due-issues
```

## Pengujian

Backend (gunakan database pengujian terpisah `jadwal_bps_test`):

```bash
cd backend
# buat database kosong bernama jadwal_bps_test (misalnya lewat phpMyAdmin), lalu:
php artisan test
```

Test men-migrate dan men-seed database pengujian sendiri lewat `RefreshDatabase` dan `DemoDataSeeder`, sehingga tidak ada langkah migrate atau seed manual. Jangan menjalankan `migrate:fresh` tanpa memastikan `DB_DATABASE` adalah `jadwal_bps_test`.

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