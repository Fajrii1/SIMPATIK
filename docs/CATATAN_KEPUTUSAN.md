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

---

## 10. Pemisahan Seeder Minimal dan Seeder Demo

- **Konteks:** Perubahan akun dan tim (commit 6ee1b45) membuat `DatabaseSeeder` hanya berisi admin, pimpinan, dan 6 tim tanpa ketua/anggota. Data dummy PRD butuh akun dan tim lama.
- **Keputusan:**
  - `DatabaseSeeder` minimal: `SettingSeeder`, `UserSeeder`, `TeamSeeder`.
  - `DemoDataSeeder` opsional berisi akun ketua/pegawai, ketua dan anggota tim, kegiatan, penugasan, kendala, notifikasi, audit log.
  - Test memakai `RefreshDatabase` pada `jadwal_bps_test` dan seeder demo dipanggil lewat `$seed`/`$seeder` di TestCase.
  - `DemoDataSeeder` idempoten (firstOrCreate/syncWithoutDetaching).
  - Test berjalan di database `jadwal_bps_test` yang dipaksa oleh phpunit.xml, sedangkan perintah artisan manual selalu memakai `.env` sehingga harus memperhatikan `DB_DATABASE`.
- **Alasan:** Menjaga database development tetap bersih, test reproducible dari database kosong, dan tidak mengubah PRD.

---

## 11. Keputusan Aturan Akses dan Skala

- **Konteks:** Peninjauan Tahap 1 menemukan celah validasi bobot kegiatan, penafsiran pengaturan kendala, kewajiban catatan penutupan, perbandingan ID yang longgar, dan tampilan menu laporan yang belum mengikuti peran ketua tim.
- **Keputusan:**
  1. **Skala Bobot Kegiatan:** Bobot kesulitan kegiatan dibatasi pada rentang 0,50 sampai 3,00 dengan nilai bawaan 1,00. Nilai di luar rentang ditolak di backend (HTTP 422) dan dicegah di frontend. Angka 1,00 berarti pekerjaan standar, di atas 1 lebih berat, dan di bawah 1 lebih ringan.
  2. **Pengaturan `pegawai_boleh_isi_kendala`:** Nilai bawaan untuk instalasi baru adalah `true`. Seeder hanya membuat nilai tersebut jika belum ada sehingga nilai yang sudah diubah admin tidak tertimpa. Pembacaan nilai dilakukan dengan `filter_var(..., FILTER_VALIDATE_BOOLEAN)` agar string `'false'` tidak dianggap benar.
  3. **Pelapor Kendala:** Yang boleh melaporkan kendala adalah anggota tim, ketua tim, dan admin. Pimpinan hanya boleh melaporkan bila juga menjabat ketua atau menjadi anggota tim. Pengaturan `pegawai_boleh_isi_kendala` memperluas izin bagi pegawai anggota tim, tetapi tidak membuka akses ke tim lain.
  4. **Catatan Penutupan:** Status kendala `selesai` mewajibkan `catatan_penutupan` diisi. Permintaan tanpa catatan ditolak dengan HTTP 422 dan pesan validasi berbahasa Indonesia.
  5. **Hak Akses Beban Kerja:** Ketua tim hanya dapat melihat ringkasan dan rincian beban kerja anggota tim yang dipimpinnya. Perbandingan ID tim dan pengguna dilakukan secara ketat (`in_array(..., true)` dengan konversi `(int)`) agar tipe data yang berbeda tidak lolos pemeriksaan.
  6. **Menu Laporan:** Rute dan menu Laporan & Ekspor hanya ditampilkan untuk admin, pimpinan, dan pengguna yang menjabat ketua tim. Backend tetap menegakkan aturan ekspor yang sama.
  7. **Penanda Ketua pada Profil:** Endpoint login, `/api/me`, dan respons pengguna menyertakan `is_ketua` serta `led_team_ids` bila relasi `ledTeams` dimuat, sehingga frontend tidak perlu menebak status ketua dari daftar tim.
  8. **Beban Kerja Ketua Tim:** Ketua tim hanya melihat beban kerja anggota tim yang dipimpinnya. Ini menyimpang dari PRD bagian 5 yang memberi ketua tim akses ke total beban seluruh pegawai, dan disengaja agar pembatasan akses sesuai kebutuhan operasional tim.
  9. **Penugasan Bersamaan:** Peringatan penugasan bersamaan dihitung dari puncak tumpang tindih harian, bukan jumlah penugasan yang beririsan dengan bulan. Ini menyimpang dari PRD bagian 8.2 dan disengaja untuk menghindari peringatan palsu pada penugasan yang tidak benar-benar bertumpuk pada hari yang sama.
  10. **Keputusan Pemilik yang Akan Diterapkan pada Fase Berikutnya:**
      1. Pelapor kendala adalah anggota tim dan ketua tim; pimpinan hanya boleh melapor bila juga anggota atau ketua tim.
      2. Ketua tim hanya melihat beban kerja anggota timnya.
      3. Bobot kegiatan dibatasi 0,50 sampai 3,00.
      4. Penugasan bersamaan memakai puncak tumpang tindih harian.
      5. Penanggung Jawab wajib diisi pada form kegiatan.
      6. Volume penugasan dibatasi sesuai peran: admin dan pimpinan melihat semua, ketua tim melihat penugasan di kegiatan timnya, pegawai hanya melihat volume miliknya, dan rekan lain hanya melihat nama serta peran.
      7. Periode penugasan harus berada dalam periode kegiatan.
      8. Pimpinan hanya satu akun, yaitu kepala BPS, dan bersifat murni pemantau.
- **Alasan:** Menutup celah otorisasi dan validasi tanpa mengubah PRD, warna, atau dependensi, serta menjaga data pengaturan yang sudah ada.

---

## 12. Keamanan Akun dan Audit Log (Fase 2)

- **Konteks:** Peninjauan Fase 2 menemukan sesi pengguna nonaktif masih dapat memakai endpoint terautentikasi, reset sandi admin masih memakai sandi default, mutasi pengguna/tim/pengaturan belum tercatat di audit log, dan penghapusan ketua tim belum memberi pesan yang jelas.
- **Keputusan:**
  1. **Middleware Pengguna Aktif:** `EnsureUserIsActive` dijalankan setelah `auth:sanctum` dan sebelum `password.changed`. Bila pengguna terautentikasi berstatus nonaktif, guard web di-logout, sesi diinvalidasi bila ada, dan respons berupa HTTP 401 dengan pesan "Akun Anda dinonaktifkan.".
  2. **Sandi Sementara Acak:** Reset sandi oleh admin tanpa mengisi kata sandi baru menghasilkan 12 karakter acak berisi huruf dan angka tanpa karakter ambigu (`0`, `O`, `o`, `1`, `I`, `l`). Kata sandi dikembalikan sebagai `temporary_password` hanya pada respons reset tersebut, tidak pernah masuk log atau audit. Frontend menampilkannya satu kali melalui modal dengan tombol salin dan peringatan bahwa sandi tidak akan ditampilkan lagi.
  3. **Pemutusan Sesi:** `UserSessionService` menghapus baris `sessions` milik pengguna hanya bila `config('session.driver')` bernilai `database`. Driver lain dilewati karena sesi tidak disimpan di tabel tersebut. Pemutusan dijalankan saat reset sandi, penonaktifan akun, penghapusan akun, dan pergantian sandi sendiri dengan mengecualikan sesi yang sedang dipakai.
  4. **Audit Mutasi Baru:** `AuditLogger` mencatat mutasi pengguna (buat, ubah, reset sandi, aktif/nonaktif, hapus), tim (buat, ubah, ubah ketua, ubah anggota, tambah anggota, hapus anggota, hapus tim), dan pengaturan (nilai lama dan baru per kunci yang benar-benar berubah). Snapshot audit tidak memuat kata sandi, hash, remember token, atau token lain.
  5. **Ketua Tim Dikosongkan:** Menghapus anggota yang menjabat ketua tim atau menghapus akun yang menjadi ketua tim tetap mengosongkan `ketua_id`. Perubahan itu kini diaudit dan respons memuat pesan "Ketua tim dikosongkan.".
- **Alasan:** Memutus akses akun nonaktif, menghilangkan sandi default yang mudah ditebak, menjaga jejak audit mutasi penting, dan mempertahankan perilaku pengosongan ketua yang sudah ada tanpa mengubah PRD atau dependensi.

---

## 13. Kendala Selesai Terkunci, Riwayat Detail, dan Notifikasi Status (Fase 4)

- **Konteks:** Halaman kendala mingguan belum memiliki modal detail baca saja, riwayat status hanya ada mentah di audit log, kendala selesai masih bisa dibuka kembali oleh PIC, kegiatan dan PIC tidak dibatasi terhadap tim yang dipilih, dan notifikasi perubahan status belum mencakup pelapor serta ketua tim secara konsisten.
- **Keputusan:**
  1. **Kendala Selesai Terkunci:** Isi kendala dan statusnya hanya dapat diubah oleh admin atau ketua tim terkait. PIC tidak dapat membuka kembali kendala yang sudah selesai. Backend menolak dengan HTTP 422 dan pesan yang jelas; frontend menonaktifkan kontrol status dan penyimpanan ketika kendala selesai dan pengguna bukan admin/ketua.
  2. **Kegiatan Harus Satu Tim:** `activity_id` pada kendala harus merupakan kegiatan milik `team_id` yang sama. Pelanggaran ditolak 422 dengan pesan dalam bahasa Indonesia.
  3. **PIC Aktif dan Anggota Tim:** `pic_id` harus pengguna berstatus aktif yang tercatat sebagai anggota `team_id`. Pelanggaran ditolak 422 dengan pesan dalam bahasa Indonesia.
  4. **Tenggat Tidak Dibatasi oleh Minggu Mulai:** PRD 8.5 mengandalkan konsep jatuh tempo untuk kendala yang lewat tenggat dan tidak melarang tenggat sebelum `minggu_mulai`. Karena itu tenggat tidak dipaksa berada sesudah minggu mulai.
  5. **Riwayat Status dari Audit Log:** `GET /api/issues/{id}` mengembalikan semua field kendala plus `history` yang disusun dari `audit_logs` (waktu, aktor, aksi, status lama, status baru, catatan). Tidak ada tabel atau kolom baru yang ditambahkan; riwayat tetap berasal dari mutasi yang sudah dicatat.
  6. **Notifikasi Perubahan Status:** Notifikasi dikirim ke pelapor, PIC, dan ketua tim, tanpa duplikat dan tanpa aktor, dengan tautan `/issues?id={id}` serta status baru dan catatan maksimal 120 karakter. Notifikasi kendala baru dan penunjukan PIC tetap memakai pola yang sama.
  7. **Halaman Detail via Query:** `IssuesPage` membuka modal detail bila parameter `id` ada di URL. Kendala yang tidak ditemukan atau di luar wewenang menampilkan pesan yang wajar, bukan layar kosong atau error mentah.
- **Alasan:** Menutup celah buka kembali kendala oleh PIC, menjaga integritas kegiatan dan PIC terhadap tim, memberi riwayat status yang dapat dibaca, dan memastikan notifikasi status sampai ke seluruh pihak yang terlibat tanpa kebisingan.
