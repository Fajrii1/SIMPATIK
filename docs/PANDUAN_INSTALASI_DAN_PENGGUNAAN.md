# Panduan Instalasi dan Penggunaan Aplikasi Penjadwalan BPS Kota Palembang

## 1. Prasyarat Sistem

- **PHP**: Versi 8.2 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, `gd`, `zip`.
- **Composer**: Versi 2.x
- **Node.js**: Versi 18.x atau lebih baru (npm disertakan).
- **Database**: MySQL 8.0+ atau MariaDB 10.4+.
- **Web Server**: Apache / Nginx atau PHP Built-in Server untuk lingkungan pengembangan.

---

## 2. Konfigurasi Lingkungan Backend (`backend/`)

1. Buka terminal pada folder `backend/`:
   ```bash
   cd backend
   ```
2. Pastikan file `.env` telah sesuai dengan database lokal:
   ```env
   APP_NAME="Jadwal BPS"
   APP_ENV=local
   APP_KEY=base64:...
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=jadwal_bps
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Pasang dependensi PHP jika diperlukan:
   ```bash
   composer install
   ```
4. Jalankan migrasi dan seeder awal (apabila database masih baru/kosong):
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
5. Jalankan server backend Laravel:
   ```bash
   php artisan serve --port=8000
   ```

---

## 3. Konfigurasi Lingkungan Frontend (`frontend/`)

1. Buka terminal pada folder `frontend/`:
   ```bash
   cd frontend
   ```
2. Pasang dependensi JavaScript:
   ```bash
   npm install
   ```
3. Jalankan server pengembangan Vite:
   ```bash
   npm run dev
   ```
   Aplikasi web akan tersedia pada alamat: `http://localhost:5173`.
4. Untuk mengompilasi rilis produksi:
   ```bash
   npm run build
   ```

---

## 4. Kredensial Akun Pengujian & Demo

Seluruh akun demo memiliki kata sandi awal: `password`.

| Peran | Alamat Email | Jabatan / Keterangan |
| :--- | :--- | :--- |
| **Administrator** | `admin@contoh.test` | Pranata Komputer Ahli Muda (Akses Penuh Manajemen Sistem) |
| **Pimpinan** | `kepala@contoh.test` | Kepala BPS Kota Palembang (Monitoring Seluruh Tim & Laporan) |
| **Ketua Tim 1** | `ketua1@contoh.test` | Ketua Tim Statistik Sosial |
| **Ketua Tim 2** | `ketua2@contoh.test` | Ketua Tim Statistik Produksi |
| **Ketua Tim 3** | `ketua3@contoh.test` | Ketua Tim Statistik Distribusi |
| **Pegawai 1** | `pegawai01@contoh.test` | Statistisi Pelaksana / Lapangan |
| **Pegawai 2** | `pegawai02@contoh.test` | Statistisi Pertama |

---

## 5. Fitur Utama Berdasarkan Peran

### A. Administrator
- Mengelola data seluruh pegawai (tambah, nonaktifkan, aktifkan, reset password).
- Mengelola struktur tim kerja dan menentukan ketua tim.
- Mengatur konfigurasi ambang batas beban kerja bulanan, batas penugasan bersamaan, dan kebijakan kendala.
- Mengakses Audit Log riwayat mutasi data.
- Mengunduh rekapitulasi laporan dalam format CSV dan PDF.

### B. Ketua Tim
- Mengelola kegiatan statistik tim yang dipimpinnya.
- Menugaskan pegawai ke kegiatan dengan pratinjau simulasi dampak beban kerja sebelum disimpan.
- Memantau beban kerja anggota tim dan menyelesaikan kendala lapangan yang dilaporkan anggota.

### C. Pegawai
- Melihat penugasan aktif pribadi dan jadwal pada kalender.
- Memantau total poin beban kerja pribadi terhadap ambang batas standar (dengan warna indikator aman, hampir penuh, atau melebihi).
- Melaporkan kendala operasional lapangan.
- Menerima notifikasi pembaruan penugasan secara real-time.

