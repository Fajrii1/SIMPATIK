# PRD & Panduan Pengembangan: Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik

> Dokumen tunggal untuk pemilik proyek dan AI coding agent. **Baca seluruh dokumen sebelum menulis kode.** Bagian 0 adalah aturan kerja agent, bagian 1 sampai 8 menjelaskan produk, bagian 9 sampai 21 menjelaskan cara membangunnya.

**Nama produk:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik (nama singkat: ditentukan pemilik proyek)
**Instansi:** BPS Kota Palembang
**Konteks:** Penelitian Mandiri Semester 7, D4 Manajemen Informatika, Politeknik Negeri Sriwijaya
**Platform:** Web application, responsif untuk desktop, tablet, dan HP
**Stack:** React + TypeScript (Vite), Laravel (REST API), MySQL (XAMPP)
**Repo:** satu repo, dua folder: `backend/` dan `frontend/`

---

## 0. Aturan kerja untuk AI coding agent

1. **Bangun hanya yang tertulis di dokumen ini.** Jangan menambah fitur, halaman, atau dependensi besar di luar ruang lingkup (bagian 4). Bila ragu, pilih opsi paling sederhana dan tulis komentar `TODO` singkat.
2. **Kerjakan berurutan** sesuai bagian 19 (urutan pengerjaan). Tiap fase harus lulus kriteria penerimaannya (bagian 16) sebelum lanjut.
3. **Cek lingkungan dulu** (bagian 9.1) dan sesuaikan versi Laravel dan Vite dengan PHP dan Node yang terpasang. Jangan mengubah versi PHP atau Node pemilik proyek.
4. **Semua teks antarmuka berbahasa Indonesia.** Nama tabel, kolom, dan istilah domain mengikuti bagian 13. Nama kelas, fungsi, dan variabel kode memakai bahasa Inggris.
5. **Rumus beban kerja hanya boleh ada di `backend/app/Services/WorkloadService.php`.** Frontend hanya menampilkan hasil dari API.
6. **Hak akses dicek di server** (Laravel Policy dan Gate). Menyembunyikan tombol di React bukan pengamanan.
7. **Jangan hardcode** nilai yang dinamis (ambang beban, batas peringatan, daftar jenis kegiatan). Ambil dari tabel `settings`, enum, atau konstanta terpusat.
8. **Jangan menyimpan rahasia di kode atau repo.** Semua kredensial, port, dan URL lewat `.env`. Sertakan `.env.example` untuk backend dan frontend.
9. **Jangan mengubah palet atau identitas visual** (bagian 11) tanpa persetujuan pemilik proyek.
10. **Tidak ada data responden survei.** Hanya data dummy (bagian 17).
11. **Sebelum menyatakan satu fase selesai:** jalankan lint, build frontend, dan seluruh test backend. Laporkan hasilnya, termasuk yang gagal.
12. **Jangan mengarang kebutuhan.** Bila ada hal yang tidak dijelaskan dokumen, tanyakan atau ambil keputusan paling konservatif dan catat di `docs/CATATAN_KEPUTUSAN.md`.

## 1. Ringkasan

Aplikasi web untuk menyusun jadwal kegiatan statistik lintas tim, menugaskan pegawai, menghitung beban kerja otomatis, memantau status kegiatan, serta mencatat kendala dan tindak lanjut mingguan. Aplikasi menggantikan proses yang sekarang memakai spreadsheet (Matriks Beban Kerja dan catatan kendala mingguan).

Aplikasi ini adalah output penelitian: dikembangkan dan diuji dengan data dummy, dan belum tentu dipakai operasional oleh BPS.

## 2. Latar belakang dan masalah

Sumber: Laporan Kinerja BPS Kota Palembang Tahun 2025 (nomor halaman adalah nomor cetak laporan).

- Survei tahunan sering berhimpitan dalam satu lini masa (hlm. 11), dan perlu koordinasi jadwal yang lebih sinkron agar beban kerja SDM dan petugas tidak menumpuk (hlm. 12).
- Beban kerja meningkat saat beberapa survei berjalan bersamaan (hlm. 187, 233).
- Respons BPS: Matriks Alokasi Beban Kerja (hlm. 93) dan rapat mingguan untuk mengevaluasi progres, kendala, dan solusi (hlm. 150). Portal SATU memiliki menu Matriks Beban Kerja (hlm. 244).
- Pengamatan awal: matriks beban kerja dan catatan kendala berupa spreadsheet yang terpisah, sehingga jadwal, beban kerja, dan kendala belum terhubung.

**Masalah yang dijawab**
1. Jadwal kegiatan antar tim belum terlihat dalam satu tampilan, sehingga jadwal yang menumpuk baru disadari belakangan.
2. Beban kerja per pegawai sulit direkap karena tersebar di banyak sheet.
3. Kendala mingguan tidak terhubung dengan kegiatan, sehingga tindak lanjutnya sulit dilacak.

## 3. Tujuan dan indikator keberhasilan

1. **Penjadwalan terpusat:** semua kegiatan semua tim dalam satu kalender.
2. **Beban kerja terukur:** beban per pegawai terhitung otomatis dari penugasan.
3. **Monitoring kegiatan dan kendala:** status kegiatan dan kendala mingguan terhubung ke kegiatan.
4. **Pusat data pemantauan pimpinan:** dashboard dan rekap yang selalu terbaru.

| Indikator | Target |
|---|---|
| Seluruh fitur prioritas Must dan Should lulus uji black box | 100% |
| Uji penerimaan pengguna (UAT) dengan pegawai BPS | Skor SUS 68 atau lebih |
| Melihat beban kerja seorang pegawai pada data uji | Kurang dari 1 menit |
| Pelanggaran hak akses pada skenario uji | 0 |

## 4. Ruang lingkup

**Masuk:** login dan peran, manajemen pengguna dan tim, kegiatan dan jadwal, kalender lintas tim, penugasan, beban kerja otomatis, peringatan jadwal menumpuk, status kegiatan, kendala dan tindak lanjut mingguan, dashboard, rekap dan ekspor (Excel dan PDF), notifikasi di dalam aplikasi, riwayat perubahan, pengaturan.

**Tidak masuk (jangan dibangun):** impor dari Excel, SSO (disiapkan nanti), progres pengisian kuesioner survei, honor dan pembayaran mitra, penilaian kinerja pegawai resmi, pengguna mitra lapangan, unggah file dan foto, data responden survei, notifikasi email atau WhatsApp, pendaftaran akun mandiri, integrasi Google API.

## 5. Pengguna dan hak akses

Ada tiga peran sistem: **admin**, **pegawai**, dan **pimpinan**. **Ketua tim bukan peran tersendiri.** Ketua tim adalah pegawai atau pimpinan yang tercatat sebagai `teams.ketua_id`, mengikuti SK Tim Kerja, dan haknya selalu berlaku hanya untuk tim yang dipimpinnya. Satu orang bisa memimpin lebih dari satu tim, dan Kepala yang merangkap ketua tim tetap terlayani.

| Fungsi | Admin | Ketua tim (timnya) | Pegawai | Pimpinan |
|---|---|---|---|---|
| Kelola pengguna, tim, anggota, ketua tim | Ya | Tidak | Tidak | Tidak |
| Kelola pengaturan sistem | Ya | Tidak | Tidak | Tidak |
| Lihat riwayat perubahan (audit log) | Ya | Tidak | Tidak | Tidak |
| Buat dan ubah kegiatan | Ya (semua) | Ya (timnya) | Tidak | Tidak |
| Tandai kegiatan selesai atau buka kembali | Ya | Ya (timnya) | Tidak | Tidak |
| Menugaskan pegawai | Ya | Ya (kegiatan timnya) | Tidak | Tidak |
| Lihat kalender | Ya | Ya (semua tim) | Ya (semua tim, hanya lihat) | Ya |
| Lihat total beban kerja bulanan semua pegawai | Ya | Ya | Tidak (hanya miliknya) | Ya |
| Lihat rincian penugasan seorang pegawai | Ya | Ya (anggota timnya dan penugasan di kegiatan timnya) | Ya (miliknya) | Ya |
| Isi dan ubah kendala mingguan | Ya | Ya (timnya) | Bila pengaturan mengizinkan (kendala miliknya) | Tidak |
| Ubah status kendala | Ya | Ya (timnya) | Penanggung jawab kendala tersebut | Tidak |
| Dashboard | Seluruh kantor | Timnya | Pribadi | Seluruh kantor |
| Ekspor rekap | Ya | Ya (timnya) | Tidak | Ya |

Catatan:
- Ketua tim perlu melihat total beban seorang pegawai (angka dan warna) saat menugaskan agar tidak menumpuk, walau pegawai itu juga bertugas di tim lain. Rincian penugasan lintas tim hanya menampilkan nama kegiatan dan periode.
- Pimpinan yang juga ketua tim mendapat hak ketua tim untuk timnya saja.
- Akun dibuat admin. Tidak ada pendaftaran mandiri. Akun baru berstatus `must_change_password` dan wajib ganti kata sandi saat login pertama.

## 6. Alur kerja

**Alur utama**
1. Admin menyiapkan pengguna, tim (dengan ketuanya), anggota tim, dan pengaturan.
2. Ketua tim membuat kegiatan timnya (tanggal mulai dan selesai, bobot, tanda kegiatan besar).
3. Ketua tim menugaskan pegawai ke kegiatan dengan peran, volume, satuan, dan periode.
4. Sistem menghitung beban kerja per pegawai per bulan dan memberi peringatan bila jadwal atau beban menumpuk.
5. Ketua tim menyesuaikan jadwal atau penugasan setelah melihat peringatan.
6. Selama kegiatan berjalan, ketua tim menandai kegiatan selesai bila sudah tuntas. Status lain terhitung otomatis.
7. Pimpinan memantau kalender, beban kerja, dan kendala lewat dashboard.

**Alur kendala mingguan**
1. Menjelang rapat mingguan, ketua tim (atau pegawai bila diizinkan) mencatat kendala: kegiatan terkait (boleh kosong), kendala, usulan solusi, tindak lanjut, penanggung jawab, dan tenggat.
2. Kendala yang belum selesai dari minggu sebelumnya otomatis tampil di daftar "terbawa".
3. Setelah rapat, status diperbarui: Terbuka, Diproses, Selesai. Kendala selesai ditutup dengan catatan penutupan.

**Alur perubahan jadwal:** ketua tim mengubah tanggal atau penugasan, sistem menghitung ulang beban dan peringatan, perubahan tercatat di audit log, dan pegawai terdampak mendapat notifikasi.

## 7. Fitur, fungsi, dan prioritas

Semua fitur di bawah dikerjakan di versi pertama. Prioritas menentukan urutan bila waktu terbatas.

| ID | Fitur | Fungsi | Prioritas |
|---|---|---|---|
| F01 | Login dan hak akses | Membatasi siapa melihat dan mengubah apa | Must |
| F02 | Manajemen pengguna dan tim | Akun dibuat admin, anggota tim, penetapan ketua tim | Must |
| F03 | Kegiatan dan jadwal | Menyimpan kegiatan: tanggal mulai dan selesai, tim, bobot, tanda kegiatan besar | Must |
| F04 | Kalender lintas tim | Semua kegiatan dalam tampilan bulan dan minggu, dengan filter | Must |
| F05 | Penugasan | Menugaskan pegawai dengan peran, volume, satuan, periode | Must |
| F06 | Beban kerja otomatis | Menghitung beban per pegawai per bulan | Must |
| F07 | Peringatan jadwal menumpuk | Menandai pegawai atau periode yang terlalu padat | Must |
| F08 | Status kegiatan | Belum mulai, berjalan, selesai, terlambat | Must |
| F09 | Kendala dan tindak lanjut mingguan | Mencatat kendala, solusi, tindak lanjut, penanggung jawab, status | Must |
| F10 | Dashboard per peran | Ringkasan beban, kegiatan terlambat, kendala terbuka | Should |
| F11 | Rekap dan ekspor | Ekspor beban kerja, kegiatan, dan kendala ke Excel dan PDF | Should |
| F12 | Notifikasi di dalam aplikasi | Pengingat penugasan, perubahan jadwal, kendala jatuh tempo | Should |
| F13 | Riwayat perubahan | Mencatat siapa mengubah apa, kapan, sebelum dan sesudah | Should |
| F14 | Pengaturan sistem | Ambang beban dan parameter peringatan | Should |

## 8. Aturan bisnis

### 8.1 Beban kerja
- Setiap penugasan punya `volume` dan `bobot`. `bobot` diambil dari kegiatan (bawaan 1.00). **Poin total penugasan = volume × bobot.**
- Periode penugasan memakai `assignments.mulai` dan `selesai`. Bila kosong, memakai tanggal kegiatan. Tanggal awal dan akhir dihitung inklusif.
- Poin dibagi ke bulan yang dilalui penugasan **proporsional jumlah hari**: `poin_bulan = poin_total × (hari_penugasan_di_bulan_itu / total_hari_penugasan)`.
- Beban pegawai pada suatu bulan = jumlah `poin_bulan` seluruh penugasan aktifnya (kegiatan yang tidak dihapus).
- `ambang_beban_bulanan` bawaan **100**. Persentase = beban / ambang. Indikator: aman di bawah 80%, hampir penuh 80% sampai di bawah 100%, melebihi 100% ke atas.
- Hitung dua desimal, tampilkan satu desimal.

**Contoh uji (wajib menjadi unit test):** penugasan volume 60, bobot 1.5, periode 1 Oktober sampai 15 November. Poin total 90. Total hari 46 (Oktober 31, November 15). Poin Oktober = 90 × 31/46 = 60.65. Poin November = 90 × 15/46 = 29.35.

### 8.2 Peringatan jadwal menumpuk
Dihitung per bulan dan dikembalikan oleh API sebagai daftar `{jenis, pegawai_id atau null, bulan, pesan}`.
- **Beban:** beban pegawai mencapai atau melebihi ambang.
- **Penugasan bersamaan:** jumlah penugasan pegawai yang periodenya beririsan dengan bulan itu mencapai `maks_penugasan_bersamaan` (bawaan 3) atau lebih.
- **Kegiatan besar bersamaan (tingkat kantor):** jumlah kegiatan bertanda `kegiatan_besar` yang periodenya beririsan dengan bulan itu melebihi `maks_kegiatan_besar_bersamaan` (bawaan 3).

Peringatan dihitung ulang setiap kali jadwal atau penugasan berubah, dan hilang setelah kondisi teratasi.

### 8.3 Status kegiatan
- Yang disimpan hanya `selesai_manual` (boolean) dan `selesai_pada`.
- Status tampil: **Selesai** bila `selesai_manual`; **Belum mulai** bila hari ini sebelum `mulai`; **Berjalan** bila hari ini antara `mulai` dan `selesai`; **Terlambat** bila hari ini lewat `selesai` dan belum selesai manual. Dihitung saat dibaca (accessor), tanpa cron.
- Kegiatan yang ditandai selesai tetap dapat dibuka kembali oleh admin atau ketua tim, dan tercatat di audit log.

### 8.4 Validasi dan kepemilikan
- `selesai` tidak boleh sebelum `mulai`. Periode penugasan harus berada dalam periode kegiatan.
- Pegawai yang ditugaskan harus aktif. Satu pegawai boleh punya beberapa penugasan di kegiatan yang sama dengan peran berbeda.
- Setiap kegiatan punya satu tim penanggung jawab. Ketua tim hanya mengubah kegiatan dan penugasan tim yang dipimpinnya.
- Penghapusan memakai soft delete. Setiap perubahan kegiatan dan penugasan dicatat di `audit_logs` (data sebelum dan sesudah).
- Pengguna yang dinonaktifkan tidak bisa login, tetapi riwayatnya tetap ada.

### 8.5 Kendala mingguan
- Status: `terbuka`, `diproses`, `selesai`. Kendala boleh melekat pada kegiatan atau umum (`activity_id` kosong).
- Minggu dimulai hari Senin (`minggu_mulai` selalu tanggal Senin).
- Daftar "terbawa" untuk minggu M = kendala dengan `minggu_mulai` lebih awal dari M dan status bukan `selesai`. Dihitung lewat query, bukan menyalin data.
- Kendala dengan `tenggat` terlewati dan belum selesai ditandai jatuh tempo.

### 8.6 Notifikasi (hanya di dalam aplikasi)
- Pegawai: ditugaskan, penugasan diubah atau dihapus, jadwal kegiatan yang diikutinya berubah.
- Penanggung jawab kendala: kendala diberikan kepadanya, kendala jatuh tempo.
- Ketua tim: peringatan jadwal menumpuk baru muncul di timnya.
- Notifikasi jatuh tempo dibuat oleh perintah artisan `notify:due-issues` yang bisa dijalankan manual atau dijadwalkan lewat scheduler Laravel.

## 9. Spesifikasi teknologi dan lingkungan

### 9.1 Cek lingkungan (wajib sebelum mulai)
Jalankan dan catat hasilnya: `php -v`, `composer -V`, `node -v`, `npm -v`. Untuk memastikan PHP milik XAMPP, jalankan juga `C:\xampp\php\php.exe -v`. Pilih versi Laravel dan Vite yang kompatibel dengan versi tersebut. Bila ekstensi PHP yang dibutuhkan belum aktif di `php.ini` XAMPP (misalnya `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `zip`, `gd`), aktifkan dan catat langkahnya di README.

### 9.2 Stack

| Komponen | Pilihan |
|---|---|
| Frontend | React 18 atau lebih baru, Vite, TypeScript (strict) |
| UI | Tailwind CSS (token lewat CSS variables) dan shadcn/ui |
| Routing, data, HTTP | React Router, TanStack Query, Axios |
| Form dan validasi | React Hook Form dan Zod |
| Kalender dan grafik | FullCalendar (React) dan Recharts |
| Ikon | `lucide-react` |
| Font | Plus Jakarta Sans (judul) dan Inter (isi), lewat `@fontsource` |
| Backend | Laravel (REST API), Eloquent, Form Request, API Resource, Policy |
| Autentikasi | Laravel Sanctum, mode SPA berbasis cookie |
| Database | MySQL (MariaDB bawaan XAMPP), utf8mb4, collation `utf8mb4_unicode_ci` |
| Ekspor | `maatwebsite/excel` dan `barryvdh/laravel-dompdf` |
| Pengujian | Pest (atau PHPUnit) untuk backend, Vitest untuk frontend |
| Kualitas kode | ESLint dan Prettier (frontend), Laravel Pint (backend) |

### 9.3 Pengembangan lokal (XAMPP)
- Aktifkan MySQL di XAMPP Control Panel. Buat database `jadwal_bps` lewat phpMyAdmin.
- Backend: `php artisan serve` di port 8000. Frontend: `npm run dev` di port 5173. Apache tidak wajib.
- `.env` backend (contoh):

```
APP_NAME="Jadwal BPS"
APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jadwal_bps
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

- `.env` frontend: `VITE_API_URL=http://localhost:8000`
- CORS: izinkan origin `http://localhost:5173` dengan `supports_credentials=true`. Axios memakai `withCredentials: true` dan memanggil `/sanctum/csrf-cookie` sebelum login.
- Perintah awal setelah clone: `composer install`, salin `.env.example` ke `.env`, `php artisan key:generate`, `php artisan migrate --seed`, lalu `npm install` di `frontend/`. Tulis ini di README.

## 10. Struktur repo

```
jadwal-bps/
  README.md                 # langkah setup XAMPP, akun dummy, cara menjalankan test
  docs/CATATAN_KEPUTUSAN.md # keputusan yang diambil agent
  backend/                  # Laravel
    app/Enums/              # PeranPengguna, StatusKendala
    app/Models/             # User, Team, Activity, Assignment, WeeklyIssue, Notification, AuditLog, Setting
    app/Http/Controllers/Api/
    app/Http/Requests/      # satu Form Request per aksi
    app/Http/Resources/
    app/Policies/           # TeamPolicy, ActivityPolicy, AssignmentPolicy, WeeklyIssuePolicy, UserPolicy
    app/Services/WorkloadService.php   # satu-satunya tempat rumus beban dan peringatan
    app/Services/AuditLogger.php
    app/Services/NotificationService.php
    app/Console/Commands/NotifyDueIssues.php
    database/migrations/  database/factories/  database/seeders/
    routes/api.php
    tests/Feature/  tests/Unit/
  frontend/                 # React + Vite + TypeScript
    src/api/                # klien axios dan fungsi per sumber daya
    src/components/ui/      # tombol, kartu, badge, input, tabel (shadcn)
    src/components/layout/  # sidebar, topbar, navigasi bawah HP
    src/features/           # calendar, activities, workload, issues, dashboard, reports, users, settings
    src/pages/              # satu berkas per layar
    src/routes/             # definisi rute dan penjaga peran
    src/hooks/  src/lib/  src/types/
    src/constants/          # label status, jenis kegiatan, teks pesan galat
```

## 11. Identitas visual

**Tema:** formal, modern, bersih, cocok untuk lingkungan instansi pemerintah. Banyak ruang kosong, tipografi jelas, warna dipakai dengan fungsi, bukan hiasan. Hindari tampilan template generik.

### 11.1 Palet 15 warna (token, jangan menambah warna di luar daftar)

| Token | Hex | Pemakaian |
|---|---|---|
| `--navy` | `#12305C` | Sidebar, judul halaman, latar penutup |
| `--blue` | `#1F4E8C` | Warna utama: tombol primer, tautan, menu aktif |
| `--sky` | `#3B82C4` | Info, status Berjalan, grafik sekunder |
| `--pale-blue` | `#E3EEFA` | Latar kartu info, baris terpilih, chip aktif |
| `--teal` | `#0B8F80` | Sukses, status Selesai, beban aman |
| `--mint` | `#DDF4EF` | Latar badge sukses |
| `--orange` | `#F28C28` | Aksen, tombol aksi utama kedua, penanda penting |
| `--amber` | `#F7B731` | Peringatan, beban hampir penuh |
| `--peach` | `#FDEBD3` | Latar badge peringatan |
| `--coral` | `#E5604D` | Bahaya, status Terlambat, beban melebihi ambang |
| `--blush` | `#FCE4E0` | Latar badge bahaya |
| `--bg` | `#F7F9FC` | Latar halaman |
| `--white` | `#FFFFFF` | Kartu, panel, input |
| `--slate` | `#5B6676` | Teks sekunder, status Belum mulai, placeholder |
| `--ink` | `#1F2937` | Teks utama |

### 11.2 Pemetaan status

| Konteks | Warna teks atau ikon | Latar badge |
|---|---|---|
| Belum mulai | `--slate` | `--bg` dengan garis tipis |
| Berjalan | `--sky` (teks gelap `--navy`) | `--pale-blue` |
| Selesai | `--teal` | `--mint` |
| Terlambat | `--coral` | `--blush` |
| Beban aman | `--teal` | `--mint` |
| Beban hampir penuh | `--ink` | `--peach` (penanda `--amber`) |
| Beban melebihi ambang | `--coral` | `--blush` |
| Kendala Terbuka | `--coral` | `--blush` |
| Kendala Diproses | `--ink` | `--peach` |
| Kendala Selesai | `--teal` | `--mint` |

### 11.3 Aturan visual
- Tombol primer: latar `--blue`, teks putih, radius 8px. Tombol aksi sekunder: kontur `--blue`. Aksen `--orange` hanya untuk satu aksi utama per layar (misalnya "Tambah kegiatan") dengan teks `--navy` atau `--ink`.
- Kartu: latar putih, radius 12 sampai 16px, bayangan sangat halus, garis tipis `--pale-blue`. Tanpa border tebal.
- Teks di atas `--orange`, `--amber`, dan `--peach` selalu `--navy` atau `--ink`. Pastikan kontras minimal WCAG AA.
- Judul memakai Plus Jakarta Sans, isi memakai Inter. Ukuran dasar isi 16px, tabel boleh 14px, keterangan tidak kurang dari 12px.
- **Tanpa emoji** di UI, heading, tombol, atau pesan. Status dibedakan lewat teks, warna, dan ikon garis (`lucide-react`), jangan warna saja.
- Tidak ada gradien neon, glow berlebihan, atau animasi mengganggu. Transisi 150 sampai 300ms, hormati `prefers-reduced-motion`.
- Setiap layar punya keadaan kosong (satu kalimat dan satu aksi), loading skeleton, dan pesan galat yang jelas.
- Fokus keyboard terlihat jelas. Target sentuh minimal 44px di HP.

### 11.4 Responsif
- Desktop (1024px ke atas): sidebar kiri `--navy` selebar 260px, bar atas dengan pencarian, lonceng notifikasi (dengan angka belum dibaca), dan menu pengguna.
- Tablet (768 sampai 1023px): sidebar menjadi rel ikon.
- HP (di bawah 768px): sidebar menjadi drawer, ada navigasi bawah berisi empat menu utama sesuai peran, tabel berubah menjadi kartu bertumpuk, filter masuk ke lembar bawah (bottom sheet), aksi utama menjadi tombol melayang atau bilah bawah tetap.
- Tidak boleh ada scroll horizontal pada halaman utama di lebar 360px.

## 12. Halaman, navigasi, dan perilaku layar

### 12.1 Navigasi per peran

| Menu | Rute | Admin | Ketua tim | Pegawai | Pimpinan |
|---|---|---|---|---|---|
| Dashboard | `/` | Ya | Ya | Ya | Ya |
| Kalender | `/kalender` | Ya | Ya | Ya | Ya |
| Kegiatan | `/kegiatan` | Ya | Ya | Ya (lihat) | Ya (lihat) |
| Beban Kerja | `/beban-kerja` | Ya | Ya | Ya (milik sendiri) | Ya |
| Kendala | `/kendala` | Ya | Ya | Ya (sesuai pengaturan) | Ya (lihat) |
| Rekap | `/rekap` | Ya | Ya | Tidak | Ya |
| Pengguna dan Tim | `/master/pengguna`, `/master/tim` | Ya | Tidak | Tidak | Tidak |
| Riwayat | `/riwayat` | Ya | Tidak | Tidak | Tidak |
| Pengaturan | `/pengaturan` | Ya | Tidak | Tidak | Tidak |
| Notifikasi | `/notifikasi` | Ya | Ya | Ya | Ya |
| Profil | `/profil` | Ya | Ya | Ya | Ya |

Menu "Ketua tim" bukan peran: item yang hanya relevan bagi ketua tim (misalnya tombol "Tambah kegiatan") muncul bila pengguna memimpin minimal satu tim. Rute yang tidak diizinkan menampilkan halaman "Tidak ada akses" (dan API menjawab 403).

### 12.2 Layar

**S1 Login `/login`:** Kolom email dan kata sandi (ikon tampilkan sandi), tombol Masuk, pesan galat "Email atau kata sandi salah", pembatasan percobaan. Tidak ada tombol daftar atau SSO. Saat `must_change_password`, arahkan ke layar ganti kata sandi sebelum halaman lain.

**S2 Dashboard `/`:** Isi menyesuaikan peran.
- Kartu ringkasan: Kegiatan Berjalan, Kegiatan Terlambat, Pegawai Melebihi Ambang, Kendala Terbuka. Untuk pegawai: Beban Bulan Ini, Penugasan Aktif, Kegiatan Terlambat Saya, Kendala untuk Saya.
- Grafik batang: beban kerja per pegawai bulan terpilih (warna sesuai indikator). Grafik donat: komposisi status kegiatan.
- Daftar tugas: kegiatan terlambat dan kendala jatuh tempo, masing-masing dengan tautan.
- Blok peringatan jadwal menumpuk bulan berjalan.

**S3 Kalender `/kalender`:** FullCalendar tampilan bulan, minggu, dan daftar. Filter tim, pegawai, status (multi pilih). Warna acara sesuai status, kegiatan besar bertanda ikon. Klik acara membuka panel samping berisi ringkasan dan penugasan. Penanda peringatan menumpuk pada bulan atau minggu terkait. Di HP default tampilan daftar.

**S4 Daftar kegiatan `/kegiatan`:** Tabel (kartu di HP) dengan pencarian, filter tim, status, rentang tanggal, urutan, dan pagination. Tombol "Tambah kegiatan" untuk admin dan ketua tim.

**S5 Detail dan form kegiatan `/kegiatan/baru`, `/kegiatan/:id`, `/kegiatan/:id/ubah`:** Data kegiatan (nama, jenis, tim, mulai, selesai, bobot, kegiatan besar, keterangan), status, daftar penugasan (tambah, ubah, hapus) dengan pratinjau beban pegawai sebelum menyimpan, tombol Tandai Selesai atau Buka Kembali, dan tab Riwayat. Form menampilkan peringatan langsung bila penugasan menyebabkan pegawai melewati ambang.

**S6 Beban kerja `/beban-kerja`:** Pemilih bulan, filter tim. Tabel pegawai dengan kolom beban, persentase, indikator, jumlah penugasan. Baris dapat dibuka untuk melihat rincian penugasan per bulan (`/beban-kerja/:userId`) dengan grafik beban beberapa bulan. Pegawai hanya melihat halaman miliknya.

**S7 Kendala `/kendala`:** Pemilih minggu (Senin sampai Minggu), tab Minggu Ini, Terbawa, dan Semua. Daftar kendala dengan status, kegiatan, penanggung jawab, dan tenggat. Form tambah dan ubah (dialog atau lembar). Tindak cepat untuk mengubah status. Kendala jatuh tempo diberi tanda.

**S8 Rekap `/rekap`:** Filter periode dan tim. Grafik beban per pegawai, kegiatan per status, kendala per status. Tabel rekap. Tombol Ekspor Excel dan Ekspor PDF.

**S9 Pengguna dan Tim `/master/pengguna`, `/master/tim`:** Tabel pengguna (nama, email, peran, tim, status aktif) dengan tambah, ubah, nonaktifkan, dan reset kata sandi sementara. Layar tim: nama, ketua, anggota (multi pilih), status. Mengganti ketua tim tidak mengubah kode.

**S10 Notifikasi `/notifikasi`:** Daftar dikelompokkan Hari ini, Kemarin, Sebelumnya, penanda belum dibaca, tombol "Tandai semua dibaca". Pratinjau lima terbaru di dropdown lonceng.

**S11 Pengaturan `/pengaturan`:** Ambang beban bulanan, maksimal penugasan bersamaan, maksimal kegiatan besar bersamaan, izin pegawai mengisi kendala. Validasi angka positif.

**S12 Riwayat `/riwayat`:** Tabel audit log (waktu, pengguna, aksi, objek) dengan filter. Baris dapat dibuka untuk melihat perbandingan data sebelum dan sesudah.

**S13 Profil `/profil`:** Data diri (baca saja), ganti kata sandi.

## 13. Model data (MySQL)

Semua tabel memiliki `id` BIGINT unsigned auto increment, `created_at`, dan `updated_at`. Tabel bertanda (SD) memakai `deleted_at` (soft delete). Gunakan foreign key dengan aturan yang jelas dan indeks yang disebutkan.

| Tabel | Kolom | Catatan |
|---|---|---|
| users (SD) | nama VARCHAR(120), email VARCHAR(150) unik, password, peran ENUM(`admin`,`pegawai`,`pimpinan`), nip VARCHAR(30) null, jabatan VARCHAR(100) null, aktif BOOLEAN default true, must_change_password BOOLEAN default true | Peran sistem, bukan jabatan di tim |
| teams (SD) | nama VARCHAR(100), ketua_id FK users null, aktif BOOLEAN default true | Ketua tim ditetapkan di sini |
| team_members | team_id FK, user_id FK, unik(team_id, user_id) | Satu pegawai boleh di beberapa tim |
| activities (SD) | nama VARCHAR(200), jenis VARCHAR(60), team_id FK, mulai DATE, selesai DATE, bobot DECIMAL(5,2) default 1.00, kegiatan_besar BOOLEAN default false, selesai_manual BOOLEAN default false, selesai_pada DATETIME null, keterangan TEXT null | Indeks (team_id, mulai, selesai) |
| assignments (SD) | activity_id FK, user_id FK, peran VARCHAR(60), volume DECIMAL(10,2), satuan VARCHAR(30), mulai DATE null, selesai DATE null | Indeks (user_id, mulai, selesai). Periode kosong berarti mengikuti kegiatan |
| weekly_issues (SD) | activity_id FK null, team_id FK, pelapor_id FK users, minggu_mulai DATE, kendala TEXT, solusi TEXT null, tindak_lanjut TEXT null, pic_id FK users null, tenggat DATE null, status ENUM(`terbuka`,`diproses`,`selesai`) default `terbuka`, catatan_penutupan TEXT null, ditutup_pada DATETIME null | Indeks (team_id, minggu_mulai, status) |
| notifications | user_id FK, tipe VARCHAR(40), pesan VARCHAR(255), tautan VARCHAR(255) null, dibaca_pada DATETIME null | Indeks (user_id, dibaca_pada) |
| audit_logs | user_id FK null, aksi VARCHAR(40), objek VARCHAR(60), objek_id BIGINT, sebelum JSON null, sesudah JSON null | Indeks (objek, objek_id) |
| settings | kunci VARCHAR(60) unik, nilai VARCHAR(255) | Kunci: `ambang_beban_bulanan` (100), `maks_penugasan_bersamaan` (3), `maks_kegiatan_besar_bersamaan` (3), `pegawai_boleh_isi_kendala` (false) |

Relasi: team 1-N activities; activity 1-N assignments; user 1-N assignments; team N-N users lewat team_members; activity 1-N weekly_issues (opsional).

Jenis kegiatan (konstanta terpusat): Survei, Sensus, Pengolahan Data, Diseminasi, Pembinaan, Rapat dan Koordinasi, Lainnya. Satuan penugasan (konstanta terpusat): Dokumen, Blok Sensus, Responden, Hari, Laporan, Lainnya.

## 14. Rancangan API

Semua endpoint berawalan `/api` dan memerlukan login kecuali `login`. Gunakan API Resource untuk bentuk data, Form Request untuk validasi, pagination untuk daftar. Galat validasi memakai format standar Laravel (422), tanpa login 401, tanpa hak 403.

| Metode | Path | Akses |
|---|---|---|
| POST | `/login`, `/logout` | Publik / semua |
| GET | `/me` | Semua |
| POST | `/change-password` | Semua |
| GET, POST | `/users` | Admin |
| GET, PUT | `/users/{id}` | Admin |
| PATCH | `/users/{id}/status`, `/users/{id}/reset-password` | Admin |
| GET, POST | `/teams` | GET semua, POST admin |
| GET, PUT | `/teams/{id}` | GET semua, PUT admin |
| POST, DELETE | `/teams/{id}/members`, `/teams/{id}/members/{userId}` | Admin |
| GET | `/activities` (filter: tim, status, rentang tanggal, kata kunci) | Semua |
| POST | `/activities` | Admin, ketua tim |
| GET | `/activities/{id}` | Semua |
| PUT, DELETE | `/activities/{id}` | Admin, ketua tim |
| PATCH | `/activities/{id}/complete`, `/activities/{id}/reopen` | Admin, ketua tim |
| GET | `/activities/{id}/assignments` | Semua |
| POST | `/activities/{id}/assignments` | Admin, ketua tim |
| PUT, DELETE | `/assignments/{id}` | Admin, ketua tim |
| POST | `/assignments/preview` (pratinjau beban sebelum simpan) | Admin, ketua tim |
| GET | `/calendar?start&end&team_id&user_id&status` | Semua |
| GET | `/workload?month=YYYY-MM&team_id` | Sesuai hak (bagian 5) |
| GET | `/workload/users/{id}?from&to` | Admin, pimpinan, ketua tim, atau pegawai bersangkutan |
| GET | `/workload/warnings?month` | Admin, pimpinan, ketua tim |
| GET, POST | `/issues` (filter: minggu, status, tim) | Sesuai hak |
| PUT, PATCH | `/issues/{id}`, `/issues/{id}/status` | Admin, ketua tim, penanggung jawab, atau pemilik (bila diizinkan) |
| GET | `/issues/carried?week=YYYY-MM-DD` | Sesuai hak |
| GET | `/dashboard` | Semua (isi menyesuaikan peran) |
| GET | `/reports/workload?format=xlsx\|pdf&month&team_id` | Admin, ketua tim, pimpinan |
| GET | `/reports/activities?format=xlsx\|pdf&...` | Admin, ketua tim, pimpinan |
| GET | `/reports/issues?format=xlsx\|pdf&...` | Admin, ketua tim, pimpinan |
| GET | `/notifications` | Pemilik |
| PATCH, POST | `/notifications/{id}/read`, `/notifications/read-all` | Pemilik |
| GET, PUT | `/settings` | GET semua, PUT admin |
| GET | `/audit-logs` | Admin |

## 15. Ekspor

- **Excel beban kerja:** satu baris per pegawai, kolom per bulan di rentang terpilih, beban, persentase, indikator (teks), jumlah penugasan.
- **Excel kegiatan:** nama, jenis, tim, mulai, selesai, status, kegiatan besar, jumlah penugasan.
- **Excel kendala:** minggu, kegiatan, kendala, solusi, tindak lanjut, penanggung jawab, tenggat, status.
- **PDF:** ringkasan berformat rapi dengan judul, periode, tim, tanggal cetak, dan tabel yang sama. Semua teks Indonesia.
- Angka di ekspor harus sama dengan yang tampil di layar untuk filter yang sama.

## 16. Kriteria penerimaan

1. Pegawai hanya melihat rincian beban kerjanya sendiri dan tidak bisa mengubah jadwal atau penugasan.
2. Ketua tim hanya dapat mengubah kegiatan dan penugasan tim yang dipimpinnya, dan permintaan API ke tim lain ditolak (403).
3. Pimpinan tidak dapat mengubah data apa pun, kecuali sebagai ketua tim untuk timnya.
4. Kegiatan dengan tanggal selesai sebelum mulai tidak bisa disimpan, dan periode penugasan di luar periode kegiatan ditolak.
5. Saat penugasan ditambah, diubah, atau dihapus, beban kerja terhitung ulang sesuai bagian 8.1 dan sesuai contoh uji.
6. Indikator beban muncul aman, hampir penuh, atau melebihi sesuai persentase terhadap ambang.
7. Peringatan jadwal menumpuk muncul bila aturan bagian 8.2 terpenuhi dan hilang setelah teratasi.
8. Kegiatan yang lewat tanggal selesai dan belum diselesaikan berstatus Terlambat otomatis.
9. Kalender menampilkan semua kegiatan dan dapat difilter per tim, pegawai, dan status.
10. Kendala dapat dikaitkan dengan kegiatan, dan kendala belum selesai dari minggu sebelumnya muncul di daftar terbawa.
11. Setiap perubahan jadwal dan penugasan tercatat di riwayat dengan pelaku, waktu, data sebelum, dan sesudah.
12. Pegawai terdampak menerima notifikasi saat penugasannya dibuat, diubah, atau dihapus.
13. Ekspor Excel dan PDF sesuai filter dan angkanya sama dengan layar.
14. Admin dapat menambah pengguna, memindahkan pegawai antar tim, dan mengganti ketua tim tanpa mengubah kode.
15. Akun baru wajib mengganti kata sandi pada login pertama, dan pengguna nonaktif tidak bisa login.
16. Semua halaman dapat dipakai di lebar 360px tanpa scroll horizontal pada halaman utama.
17. Akses tanpa login ke endpoint mana pun (kecuali login) ditolak (401).
18. Tidak ada emoji di antarmuka, dan semua warna berasal dari palet bagian 11.
19. `lint`, `build`, dan semua test lulus.

## 17. Data dummy (seeder)

Seluruhnya fiktif. Kata sandi dummy `password` hanya untuk pengembangan lokal dan dilarang dipakai di luar itu.

- **Akun:** `admin@contoh.test` (admin), `kepala@contoh.test` (pimpinan, juga ketua satu tim), `ketua1@contoh.test` sampai `ketua5@contoh.test` (pegawai, ketua tim), `pegawai01@contoh.test` sampai `pegawai10@contoh.test` (pegawai). Nama memakai bentuk generik seperti "Pegawai 01".
- **Tim:** Tim Sosial, Tim Produksi, Tim Distribusi, Tim Neraca dan Analisis, Tim Pelayanan Statistik, Tim IPDS. Tiap tim punya ketua dan beberapa anggota, dan beberapa pegawai berada di dua tim.
- **Kegiatan:** sekitar 14 kegiatan dengan jadwal yang sengaja beririsan, 3 bertanda kegiatan besar, satu sudah lewat tanggal selesai dan belum selesai (uji Terlambat), satu sudah selesai, dan satu belum mulai.
- **Penugasan:** sekitar 30, termasuk satu pegawai yang sengaja melewati ambang, satu yang punya 3 penugasan beririsan, dan kasus contoh bagian 8.1.
- **Kendala:** sekitar 12 di beberapa minggu dengan status berbeda, termasuk satu belum selesai dari minggu lalu (uji terbawa) dan satu dengan tenggat terlewati.
- **Notifikasi dan audit log:** beberapa contoh agar layar terisi.
- **Pengaturan:** nilai bawaan bagian 13.

## 18. Aturan kode dan kualitas

- TypeScript strict di frontend, hindari `any`. Komponen kecil dan bernama jelas. Teks UI, label status, dan pesan galat ada di `src/constants/`.
- Styling lewat Tailwind dan token bagian 11, bukan nilai warna acak.
- Backend: controller tipis, logika di Service, validasi di Form Request, otorisasi di Policy, bentuk respons di API Resource. Gunakan enum untuk peran dan status. Hindari query N+1 (eager loading) dan pastikan indeks bagian 13 dibuat.
- Jangan menambah dependensi besar tanpa alasan tertulis di pesan commit.
- Komentar hanya untuk logika yang tidak jelas. Laravel Pint dan ESLint harus bersih.
- Tulis test: Pest feature test untuk setiap kriteria penerimaan (khususnya 1, 2, 3, 15, 17) dan unit test `WorkloadService` memakai contoh bagian 8.1 serta kasus peringatan 8.2.
- Pesan commit singkat dan jelas, satu perubahan logis per commit.

## 19. Urutan pengerjaan (SDLC dengan model Prototyping)

| Fase | Isi | Selesai bila |
|---|---|---|
| 0 | Wawancara, PRD, evaluasi dengan pembimbing BPS dan dosen | PRD disetujui |
| 1 | Cek lingkungan, setup Laravel dan React, Sanctum SPA, migrasi, seeder, login, ganti sandi pertama, penjaga rute, kerangka layout dan token visual | Kriteria 15, 17, 18 lulus |
| 2 | Manajemen pengguna, tim, anggota, ketua tim, pengaturan | Kriteria 14 lulus |
| 3 | Kegiatan, penugasan, kalender | Kriteria 2, 4, 9 lulus |
| 4 | `WorkloadService`: beban, indikator, peringatan menumpuk, status otomatis | Kriteria 1, 5, 6, 7, 8 lulus, unit test hijau |
| 5 | Kendala mingguan dan daftar terbawa | Kriteria 10 lulus |
| 6 | Notifikasi dan audit log | Kriteria 11, 12 lulus |
| 7 | Dashboard, rekap, ekspor Excel dan PDF | Kriteria 3, 13 lulus |
| 8 | Penyempurnaan responsif, aksesibilitas, pengujian black box, UAT, SUS | Kriteria 16, 19 lulus |
| 9 | README, panduan pengguna, catatan keputusan | Dokumen lengkap |

## 20. Pengujian dan kualitas

- **Backend:** feature test per kriteria penerimaan dan unit test perhitungan beban (bandingkan dengan hitungan manual pada data seeder).
- **Black box:** satu skenario per kriteria, dicatat dalam tabel (skenario, langkah, hasil diharapkan, hasil aktual, lulus atau tidak).
- **UAT:** pegawai BPS menjalankan skenario tugas (menyusun jadwal, menugaskan pegawai, membaca peringatan, mencatat kendala, melihat dashboard), lalu mengisi kuesioner SUS.
- **Kualitas:** ESLint, Prettier, Pint, dan build bersih. Uji manual di lebar 360px, 768px, dan 1280px. Periksa navigasi keyboard dan kontras warna pada layar utama.

## 21. Hal yang tidak boleh dilakukan

- Menambah fitur di luar bagian 4, termasuk impor Excel dan SSO.
- Menyimpan data responden survei atau data pegawai asli di repo atau seeder.
- Menaruh rumus beban kerja di luar `WorkloadService` atau menghitungnya di frontend.
- Mengandalkan penyembunyian tombol untuk hak akses.
- Memakai emoji, warna di luar palet, atau mengubah identitas visual tanpa persetujuan.
- Menyimpan rahasia di kode atau repo, atau mengubah versi PHP dan Node pemilik proyek.
- Menggunakan gambar atau aset berhak cipta tanpa izin.
- Menyatakan pekerjaan selesai tanpa menjalankan lint, build, dan test.

## 22. Risiko dan batasan

| Hal | Catatan |
|---|---|
| BPS belum tentu memakai aplikasi | Tujuan ditulis sebagai rancang bangun dan evaluasi dengan pengguna |
| Aturan beban kerja yang berkembang | Rumus ada di satu modul (`WorkloadService`) agar mudah diganti |
| Hosting | Selama pengembangan di XAMPP lokal. Bila nanti dipindah, aplikasi Laravel umumnya dapat berjalan di hosting PHP atau server BPS, dan Vercel tidak cocok untuk Laravel |
| Data internal | Gunakan hanya data dummy sampai ada izin tertulis |
| Kualitas kode dari AI | Tinjau dan uji hasilnya sendiri, jangan diterima begitu saja |
