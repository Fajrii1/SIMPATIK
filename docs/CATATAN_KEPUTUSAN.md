# Catatan Keputusan Arsitektur Database (ADR)
**Proyek:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang  
**Sumber Kebenaran:** `PRD.md`  
**Tanggal:** 28 Oktober 2026  
**Status:** Diterapkan (Implemented)

Dokumen ini mencatat keputusan-keputusan teknis arsitektur database, asumsi yang diterapkan, dan batas lingkup implementasi yang diambil oleh Autonomous AI Coding Agent sesuai arahan Bagian 0 dan Bagian 13 PRD.

---

## 1. Kebijakan Soft Delete pada Entitas
- **Konteks:** PRD Bagian 13 menandai entitas tertentu dengan `(SD)` untuk Soft Delete.
- **Keputusan:**
  - Soft delete (`deleted_at`) **hanya diterapkan pada 5 tabel**:
    1. `users`
    2. `teams`
    3. `activities`
    4. `assignments`
    5. `weekly_issues`
  - Tabel berikut **TIDAK menggunakan soft delete**:
    - `team_members`: Sebagai tabel relasi pivot many-to-many, penghapusan keanggotaan adalah pencabutan relasi aktual (hard delete pada pivot row).
    - `notifications`: Notifikasi bersifat pesan internal yang dapat dibaca atau dihapus permanen.
    - `audit_logs`: Data jejak audit bersifat historis immutable dan tidak boleh di-soft delete atau dimodifikasi.
    - `settings`: Tabel konfigurasi key-value sistem yang permanen.

---

## 2. Kebijakan Foreign Key Referensial (ON DELETE & ON UPDATE)
- **Konteks:** Database mendukung soft delete pada level aplikasi (Eloquent), namun integritas referensial level basis data tetap harus aman terhadap *orphaned data* maupun *accidental hard delete*.
- **Keputusan:**
  - `teams.ketua_id` -> `users.id`: `ON DELETE SET NULL`, `ON UPDATE CASCADE`. Jika pengguna dihapus permanen, tim tetap ada dan status ketua menjadi kosong sementara waktu.
  - `team_members.team_id` -> `teams.id`: `ON DELETE CASCADE`, `ON UPDATE CASCADE`.
  - `team_members.user_id` -> `users.id`: `ON DELETE CASCADE`, `ON UPDATE CASCADE`.
  - `activities.team_id` -> `teams.id`: `ON DELETE RESTRICT`, `ON UPDATE CASCADE`. Tim yang memiliki kegiatan tidak boleh dihapus secara hard delete sebelum kegiatannya dibersihkan.
  - `assignments.activity_id` -> `activities.id`: `ON DELETE CASCADE`, `ON UPDATE CASCADE`. Jika kegiatan dihapus hard delete, seluruh penugasan di dalamnya ikut terhapus.
  - `assignments.user_id` -> `users.id`: `ON DELETE RESTRICT`, `ON UPDATE CASCADE`. Pengguna yang memiliki penugasan tidak boleh dihapus hard delete.
  - `weekly_issues.activity_id` -> `activities.id`: `ON DELETE SET NULL`, `ON UPDATE CASCADE`. Kendala mingguan dapat bersifat independen pada tim (`activity_id` nullable).
  - `weekly_issues.team_id` -> `teams.id`: `ON DELETE RESTRICT`, `ON UPDATE CASCADE`.
  - `weekly_issues.pelapor_id` -> `users.id`: `ON DELETE RESTRICT`, `ON UPDATE CASCADE`.
  - `weekly_issues.pic_id` -> `users.id`: `ON DELETE SET NULL`, `ON UPDATE CASCADE`. Jika PIC dihapus, kendala tetap tersimpan dan kolom PIC menjadi null.
  - `notifications.user_id` -> `users.id`: `ON DELETE CASCADE`, `ON UPDATE CASCADE`.
  - `audit_logs.user_id` -> `users.id`: `ON DELETE SET NULL`, `ON UPDATE CASCADE`. Log audit wajib abadi dan tidak boleh terhapus ketika user akun terhapus.

---

## 3. Penegakan Constraint Keunikan Email pada Pengguna
- **Konteks:** PRD menetapkan `users.email VARCHAR(150) unik`. Pengguna juga memiliki kolom `aktif BOOLEAN default true` dan soft delete `deleted_at`.
- **Keputusan:**
  - Kolom `email` diberi constraint `UNIQUE` di tingkat database MySQL.
  - Penonaktifan pegawai dilakukan dengan mengubah `aktif = false` (bukan menghapus baris), sehingga akun nonaktif tetap mempertahankan riwayat dan emailnya tidak dapat didaftarkan ganda oleh pihak lain secara sembarangan, sesuai PRD Bagian 8.4 ("Pengguna yang dinonaktifkan tidak bisa login, tetapi riwayatnya tetap ada").

---

## 4. Pemisahan Logika Beban Kerja dan Status Kegiatan
- **Konteks:** PRD Bagian 8.1–8.3 secara tegas melarang perhitungan beban kerja di database maupun penyimpanan status kegiatan statis yang redundant.
- **Keputusan:**
  - **Tabel `activities` TIDAK memiliki kolom `status`.** Yang disimpan hanya kolom state primer: `selesai_manual` (BOOLEAN) dan `selesai_pada` (DATETIME nullable). Status dinamis (`Belum mulai`, `Berjalan`, `Selesai`, `Terlambat`) dihitung melalui accessor model Eloquent (`getStatusAttribute()`).
  - **Tabel `assignments` TIDAK memiliki kolom poin rekap beban.** Kolom yang disimpan murni data primer: `volume`, `satuan`, `mulai`, dan `selesai`. Poin beban (`volume × bobot kegiatan`) dan proporsi hari bulanan diproses seutuhnya di kelas backend `app/Services/WorkloadService.php`.

---

## 5. Standar Format JSON pada `audit_logs`
- **Konteks:** PRD menetapkan kolom `sebelum JSON null` dan `sesudah JSON null` pada `audit_logs`.
- **Keputusan:**
  - Menggunakan tipe native `$table->json('sebelum')->nullable()` dan `$table->json('sesudah')->nullable()` pada migration Laravel.
  - Pada MariaDB/MySQL bawaan XAMPP, tipe ini diimplementasikan secara native dengan validasi format JSON otomatis.

---

## 7. Pemisahan Seeder Minimal dan Seeder Demo

- **Konteks:** Perubahan akun dan tim (commit 6ee1b45) membuat `DatabaseSeeder` hanya berisi admin, pimpinan, dan 6 tim tanpa ketua/anggota. Data dummy PRD butuh akun dan tim lama.
- **Keputusan:**
  - `DatabaseSeeder` minimal: `SettingSeeder`, `UserSeeder`, `TeamSeeder`.
  - `DemoDataSeeder` opsional berisi akun ketua/pegawai, ketua dan anggota tim, kegiatan, penugasan, kendala, notifikasi, audit log.
  - Test memakai `RefreshDatabase` pada `jadwal_bps_test` dan seeder demo dipanggil lewat `$seed`/`$seeder` di TestCase.
  - `DemoDataSeeder` idempoten (updateOrCreate/syncWithoutDetaching).
- **Alasan:** Menjaga database development tetap bersih, test reproducible dari database kosong, dan tidak mengubah PRD.
  - Pada Model `AuditLog`, atribut ini di-cast sebagai `array` (`'sebelum' => 'array'`, `'sesudah' => 'array'`).

---

## 6. Validasi Tanggal dan Integritas Lintas Tabel
- **Konteks:** Aturan PRD mencakup:
  1. `activities.selesai >= activities.mulai`.
  2. `assignments.selesai >= assignments.mulai`.
  3. Rentang efektif penugasan harus berada di dalam rentang kegiatan penanggung jawab.
  4. `weekly_issues.minggu_mulai` wajib merupakan tanggal hari Senin.
- **Keputusan:**
  - Integritas relasional diproteksi pada layer **Laravel Form Request / Model Event Validasi**.
  - Untuk integritas database lokal, ditambahkan check constraint dasar di migrasi jika didukung oleh engine database, dengan fallback penegakan utama pada layer validasi Laravel agar kompatibel lintas versi MySQL 8 / MariaDB 10.4+.



---

## 7. Arsitektur Autentikasi Laravel Sanctum SPA (Fase 1)
- **Konteks:** PRD Bagian 9.2, 9.3, dan 14 menetapkan autentikasi SPA berbasis cookie menggunakan Laravel Sanctum, integrasi dengan React Frontend Vite di `http://localhost:5173`.
- **Keputusan:**
  1. **Mode Autentikasi:** Murni menggunakan Sanctum SPA berbasis session cookie (`SESSION_DRIVER=cookie`), bukan Bearer API token personal, guna menjaga keamanan terhadap XSS dan menyelaraskan dengan PRD.
  2. **Penolakan Pengguna Nonaktif & Pencegahan User Enumeration:** Autentikasi dilakukan dengan menyertakan kriteria `'aktif' => true` langsung ke dalam `Auth::attempt(...)`. Jika akun nonaktif, email tidak ditemukan, atau kata sandi keliru, API secara seragam mengembalikan HTTP 422 dengan pesan `"Email atau kata sandi salah."` sehingga tidak membocorkan keberadaan akun di sistem.
  3. **Penegakan `must_change_password`:** Diterapkan melalui middleware kustom `EnsurePasswordChanged` (`password.changed`). Pengguna dengan flag `must_change_password = true` diblokir dengan HTTP 403 (`{"message": "Anda harus mengganti kata sandi terlebih dahulu.", "must_change_password": true}`) dari seluruh endpoint domain (seperti `/api/dashboard`, `/api/activities`, dll), dan hanya diizinkan mengakses `/api/me` (agar frontend tahu status sesi), endpoint penggantian sandi (`POST /api/change-password`), dan keluar (`POST /api/logout`). Akun demo hasil seeder dibuat dengan flag `false` agar langsung dapat dipakai; akun baru buatan admin dan hasil reset sandi tetap `true`.
  4. **Penyembunyian Kredensial:** Data kata sandi tidak pernah dikembalikan dalam respon API berkat penggunaan `UserResource` dan atribut `$hidden` pada model `User`. Kata sandi selalu di-hash menggunakan algoritma default Laravel (Bcrypt rounds 12).
  5. **Rate Limiting:** Dibangun di dalam `LoginRequest` menggunakan `RateLimiter` dengan pembatasan 5 kali percobaan gagal per menit berdasarkan kombinasi email dan IP klien. Percobaan ke-6 menghasilkan HTTP 429 Too Many Requests dalam bahasa Indonesia.


---

## 8. Arsitektur Manajemen Pengguna, Tim, dan Pengaturan (Fase 2)
- **Konteks:** PRD Bagian 5, 8.4, 12.2 S9, 12.2 S11, dan 14 menetapkan tata kelola data master pengguna, tim kerja, anggota tim, dan konfigurasi pengaturan sistem.
- **Keputusan:**
  1. **Matriks Otorisasi Berbasis Server (Laravel Policy):**
     - `UserPolicy`: Seluruh operasi CRUD (`viewAny`, `view`, `create`, `update`, `delete`, `updateStatus`, `resetPassword`) dibatasi secara ketat hanya untuk peran `admin`. Pengguna dengan peran `pegawai` atau `pimpinan` ditolak dengan HTTP 403 Forbidden.
     - `TeamPolicy`: Pembacaan data tim (`GET /api/teams`, `GET /api/teams/{id}`, `GET /api/teams/{id}/members`) diizinkan untuk semua peran terautentikasi (karena seluruh pegawai dan pimpinan membutuhkan visibilitas tim kerja). Mutasi tim dan keanggotaan dibatasi khusus peran `admin`.
     - `SettingPolicy`: Seluruh pengguna terautentikasi dapat membaca konfigurasi sistem (`GET /api/settings`), namun hanya `admin` yang berwenang memperbarui (`PUT /api/settings`).
  2. **Proteksi Integritas Akun Mandiri (*Self-Destruction Protection*):**
     - Admin yang sedang login secara mutlak dilarang menonaktifkan status akunnya sendiri (`aktif = false`), mengubah perannya sendiri menjadi non-admin, atau menghapus (soft delete) akunnya sendiri. Pelanggaran aturan ini menghasilkan HTTP 422 Unprocessable Content.
  3. **Pemberian Kata Sandi & `must_change_password`:**
     - Setiap pembuatan akun baru (`POST /api/users`) oleh admin secara otomatis mengaktifkan `must_change_password = true`, mewajibkan pengguna mengganti kata sandi pada login pertama.
     - Reset kata sandi oleh admin (`PATCH /api/users/{id}/reset-password`) mengembalikan status `must_change_password = true` untuk memaksa pembaruan kredensial.
  4. **Konsistensi Hubungan Ketua Tim dan Anggota Tim:**
     - Ketika seorang pengguna ditetapkan sebagai ketua tim (`teams.ketua_id`), pengguna tersebut secara otomatis dipastikan terdaftar pula dalam daftar anggota tim (`team_members`) dalam satu transaksi database.
     - Jika seorang anggota tim yang menjabat sebagai ketua dihapus dari keanggotaan tim (`DELETE /api/teams/{id}/members/{user_id}`), nilai `teams.ketua_id` secara atomik disetel ke `null` guna mencegah inkonsistensi referensial.
     - Ketua tim wajib berstatus aktif (`aktif = true`).
  5. **Pagination & Query Limits:**
     - Endpoint `GET /api/users` menerapkan pagination server-side dengan batas maksimal 100 entri per halaman (`per_page`) serta mendukung pencarian multi-kolom (`nama`, `email`, `nip`) dan filter (`peran`, `aktif`, `team_id`) guna mencegah beban berlebih pada database saat jumlah data bertumbuh.


---

## 9. Arsitektur Kegiatan, Penugasan, Perhitungan Beban Kerja, dan Kalender (Fase 3)
- **Konteks:** PRD Bagian 5, 8.1–8.4, 12.2 S3–S6, dan 14 menetapkan tata kelola kegiatan, penugasan, pemusatan rumus beban kerja, kalender lintas tim, dan pratinjau dampak penugasan sebelum disimpan.
- **Keputusan:**
  1. **Pemusatan Rumus Beban Kerja (`WorkloadService`):**
     - Sesuai aturan PRD Bagian 0 nomor 5 dan Bagian 8.1, seluruh rumus perhitungan beban kerja, distribusi proporsional hari, indikator beban, peringatan jadwal menumpuk, dan simulasi pratinjau diletakkan secara terpusat pada `backend/app/Services/WorkloadService.php`.
     - `poin_total = volume × bobot`. Poin bulanan dibagi proporsional hari: `poin_bulan = poin_total × (hari_penugasan_di_bulan_itu / total_hari_penugasan)`. Dihitung dengan presisi 2 desimal (`round(..., 2)`).
     - Indikator beban kerja ditentukan dari persentase terhadap `ambang_beban_bulanan` (tabel `settings`):
       - `aman`: di bawah 80%.
       - `hampir_penuh`: 80% s.d. di bawah 100%.
       - `melebihi`: 100% ke atas.
  2. **Otorisasi Kegiatan & Penugasan:**
     - Admin memiliki wewenang penuh pada seluruh kegiatan dan penugasan di semua tim.
     - Ketua Tim hanya berwenang membuat, memperbarui, menghapus, menyelesaikan, dan membuka kembali kegiatan pada tim yang dipimpinnya (`teams.ketua_id = auth()->id()`). Permintaan mutasi ke tim lain ditolak dengan HTTP 403 Forbidden.
     - Pegawai biasa dan Pimpinan dibatasi akses hanya-baca (*read-only*) untuk kegiatan dan penugasan.
  3. **Integritas Penugasan Pegawai & Periode:**
     - Pegawai yang ditugaskan wajib berstatus aktif (`aktif = true`). Penugasan pengguna nonaktif ditolak dengan HTTP 422.
     - Periode penugasan (`mulai` dan `selesai`) wajib berada dalam rentang tanggal kegiatan induknya (`activities.mulai` s.d. `activities.selesai`). Jika penugasan tidak memiliki tanggal khusus (null), periode otomatis mewarisi tanggal kegiatan.
  4. **Pratinjau Beban Kerja Tanpa Efek Samping (*Side-Effect Free*):**
     - Endpoint `POST /api/assignments/preview` melakukan kalkulasi dampak beban kerja, persentase, indikator, dan potensi peringatan baru tanpa melakukan `INSERT`, `UPDATE`, atau mutasi apa pun pada basis data.
  5. **Audit Logging & Notifikasi:**
     - Setiap mutasi kegiatan (`create`, `update`, `delete`, `complete`, `reopen`) dan penugasan (`create`, `update`, `delete`) dicatat secara otomatis ke tabel `audit_logs` dengan menyimpan snapshot JSON data sebelum dan sesudah.
     - Pembuatan, pembaruan, dan penghapusan penugasan memicu pembuatan entri `Notification` di dalam aplikasi untuk pegawai penerima penugasan.
     - Perubahan tanggal kegiatan memicu notifikasi pembaruan jadwal ke seluruh pegawai yang terlibat pada kegiatan tersebut.
  6. **Penyajian Data Kalender:**
     - Endpoint `GET /api/calendar` mengembalikan data dengan format standar FullCalendar (React) (`id`, `title`, `start`, `end`, `allDay`, `extendedProps`).
     - Mendukung filter `team_id`, `user_id`, `status`, dan rentang tanggal `start`/`end`. Kegiatan multi-bulan otomatis ditampilkan berkesinambungan melintasi bulan terkait.
