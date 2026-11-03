# Laporan Remediasi Fase 7

## 1. Berkas yang Diubah atau Ditambah

### 1.1 Berkas baru

- `docs/HAK_AKSES.md`: tabel hak akses per peran (admin, pimpinan, ketua tim, pegawai) sesuai kode saat ini.

### 1.2 Berkas yang diubah

- `README.md`:
  - Menambah bagian **Variabel Seeder (`.env.example`)** yang mendokumentasikan `SEED_ADMIN_PASSWORD` dan `SEED_KEPALA_PASSWORD`.
  - Bagian **Perintah Terjadwal (Scheduler)** dan **Pengujian** sudah ada dari Fase 5, tidak diubah.
- `docs/CATATAN_KEPUTUSAN.md`:
  - Menambah bagian **15. Daftar Penyimpangan Terhadap PRD yang Sengaja** berisi tabel 9 penyimpangan disengaja yang telah diterapkan (ketua tim beban, puncak harian, pimpinan murni pemantau, volume dibatasi, form kegiatan di Daftar Kegiatan, PJ wajib, palet Tailwind dan #002b6a, tenggat kendala, notifikasi kegiatan besar).
  - Nomor bagian tetap berurutan 1 sampai 15 tanpa duplikat angka.
- Tidak ada berkas kode produksi yang diubah pada Fase 7. Fase 7 hanya berupa regresi, dokumentasi, dan penyimpangan PRD.

## 2. Keluaran Verifikasi Mentah

### 2.1 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
   Tests:    164 passed (1576 assertions)
   Duration: 13.04s
```

Baseline sebelum Fase 7 (setelah Fase 6): 164 lulus, 1576 assertion. Tid ada regresi.

### 2.2 Uji seeder dua kali (7.2)

Perintah (pada `jadwal_bps_test`, bukan `jadwal_bps`):

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan migrate --force
$env:DB_DATABASE='jadwal_bps_test'; php artisan db:seed --class=DemoDataSeeder
$env:DB_DATABASE='jadwal_bps_test'; php artisan db:seed --class=DemoDataSeeder
```

Keluaran: kedua pemanggilan `db:seed --class=DemoDataSeeder` selesai tanpa error. Karena seeder menggunakan `firstOrCreate`, tidak ada duplikasi data dan ketua/sandi akun yang sudah ada tidak berubah. Ini juga sudah tercakup oleh `SeederTest` yang lulus dalam suite penuh.

### 2.3 Format backend

Perintah:

```powershell
vendor\bin\pint --test
```

Keluaran:

```text
{"tool":"pint","result":"passed"}
```

### 2.4 Lint frontend

Perintah:

```powershell
npx oxlint
```

Ringkasan:

```text
LINES=21
WARNINGS=21
ERRORS=0
```

Jumlah peringatan sama dengan baseline (21), tidak ada error.

### 2.5 Build frontend

Perintah:

```powershell
npm run build
```

Keluaran:

```text
dist/index.html                   0.45 kB │ gzip:   0.29 kB
dist/assets/index-DMUgZBOm.css   54.28 kB │ gzip:  10.08 kB
dist/assets/index-BSVR6iBk.js   943.94 kB │ gzip: 261.66 kB
(!) Some chunks are larger than 500 kB after minification.
✓ built in 756ms
```

Peringatan ukuran chunk sudah ada sebelum Fase 7.

### 2.6 Pemeriksaan larangan

```text
=====ALERT=====
=====EMOJI=====
```

Tidak ada hasil untuk `alert(` maupun emoji pada `frontend/src`.

```text
=====SLATE-800/900/950 (bg/border)=====
(tidak ditemukan)
```

Tidak ada `bg-slate-800/900/950` maupun `border-slate-800/900/950` di `frontend/src`.

```text
=====HEX #12305C=====
(tidak ditemukan)
```

Tidak ada hex `#12305C` di `frontend/src`.

### 2.7 Status berkas

```text
 M README.md
 M docs/CATATAN_KEPUTUSAN.md
?? docs/HAK_AKSES.md
?? docs/LAPORAN_REMEDIASI_FASE7.md
```

Sebelum laporan ini ditambahkan, satu-satunya berkas tak terlacak adalah `docs/HAK_AKSES.md`. Tidak ada berkas kode produksi yang diubah.

## 3. Hal yang Tidak Dapat Diverifikasi

- Pengujian `db:seed` dua kali hanya dijalankan pada `jadwal_bps_test`, bukan pada `jadwal_bps` (database development), sesuai aturan keras.
- Verifikasi visual pada lebar 360px untuk halaman baru (`ActivityFormModal`, `HAK_AKSES.md` adalah dokumen) belum dijalankan di peramban sungguhan.
- Ekspor PDF kendala belum dibuka untuk diperiksa visual; hanya `content-type: application/pdf` yang diverifikasi pada Fase 5.
- Scheduler `notify:due-issues` hanya diverifikasi bahwa perintah terdaftar dan bisa dipanggil manual; eksekusi otomatis lewat Windows Task Scheduler belum diuji di lingkungan ini.

## 4. Temuan di Luar Lingkup yang Tidak Diubah

- Defek `GET /api/workload/users/{id}` 500 (`Attempt to read property "value" on string`) dari Fase 3 masih belum diperbaiki, karena berada di luar cakupan Fase 7 (hanya regresi dan dokumentasi). Pengujian terkait tetap ditandai `incomplete`.
- `poin_beban` tidak pernah ada (kolom/accessor yang tidak ada) dari Fase 2/3 masih belum diperbaiki. `penugasan_aktif[].poin` di dashboard selalu 0, tetapi total beban pribadi tetap benar.
- Peringatan kegiatan besar bersamaan tidak memicu notifikasi `peringatan_jadwal` (sengaja, dicatat di bagian 15 CATATAN_KEPUTUSAN.md).
- Perubahan yang bukan lewat penugasan (mis. kegiatan di-reschedule atau bobot diubah) tidak memicu notifikasi peringatan jadwal (di luar cakupan 5.2).

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps` (atau `jadwal_bps_test`). Seluruh query berikut tidak mengubah data.

### 5.1 Verifikasi tidak ada duplikasi pengguna setelah seeder dua kali

```sql
SELECT email, COUNT(*) AS jumlah
FROM users
GROUP BY email
HAVING jumlah > 1
ORDER BY email;
```

Hasil yang diharapkan: tidak ada baris. Setiap email harus unik.

### 5.2 Verifikasi ketua tim masih ada dan tidak berubah

```sql
SELECT t.id, t.nama AS tim, t.ketua_id, u.nama AS ketua
FROM teams t
LEFT JOIN users u ON u.id = t.ketua_id
WHERE t.ketua_id IS NOT NULL
ORDER BY t.id;
```

Hasil yang diharapkan: setiap tim yang memiliki `ketua_id` harus memiliki data ketua yang cocok. Tidak ada `ketua_id` yang merujuk pada pengguna yang tidak ada.

### 5.3 Verifikasi sandi akun tidak berubah setelah seder dua kali

```sql
SELECT id, nama, email, password
FROM users
WHERE email IN ('admin@contoh.test', 'kepala@contoh.test')
ORDER BY email;
```

Hasil yang diharapkan: kolom `password` tetap berupa hash bcrypt yang sama sebelum dan setelah `DemoDataSeeder` dijalankan dua kali (tidak boleh berubah menjadi hash baru).

### 5.4 Daftar hak akses per peran (dari docs/HAK_AKSES.md)

Lihat `docs/HAK_AKSES.md` untuk tabel lengkap hak akses admin, pimpinan, ketua tim, dan pegawai.

### 5.5 Daftar penyimpangan PRD sengaja (dari docs/CATATAN_KEPUTUSAN.md bagian 15)

Lihat `docs/CATATAN_KEPUTUSAN.md` bagian 15 untuk tabel 9 penyimpangan disengaja yang telah diterapkan.
