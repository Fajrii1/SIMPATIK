# Laporan Remediasi Fase 2

## 1. Berkas yang Diubah

### 1.1 Berkas baru

- `backend/app/Http/Middleware/EnsureUserIsActive.php`: middleware `user.active`. Bila pengguna terautentikasi berstatus `aktif = false`, guard `web` di-logout, sesi diinvalidasi bila tersedia, dan respons berupa HTTP 401 dengan pesan `"Akun Anda dinonaktifkan."`.
- `backend/app/Services/UserSessionService.php`: penghapus baris tabel `sessions` milik satu pengguna. Hanya berjalan bila `config('session.driver') === 'database'`; driver lain mengembalikan `0` tanpa menyentuh tabel. Mendukung pengecualian satu ID sesi agar sesi yang sedang dipakai tidak ikut terhapus.
- `backend/app/Services/AuditLogger.php`: penulis `audit_logs` dan pembuat snapshot. Snapshot pengguna memuat `id`, `nama`, `email`, `peran`, `nip`, `jabatan`, `aktif`, `must_change_password`, dan `team_ids`; snapshot tim memuat `id`, `nama`, `ketua_id`, `aktif`, dan `member_ids`. Tidak ada kata sandi, hash, `remember_token`, atau token lain yang masuk snapshot.
- `backend/tests/Feature/AccountSecurityTest.php`: 7 pengujian keamanan akun.
- `backend/tests/Feature/AuditTrailTest.php`: 3 pengujian jejak audit.

### 1.2 Berkas yang diubah

- `backend/bootstrap/app.php`: mendaftarkan alias middleware `user.active`.
- `backend/routes/api.php`: grup terproteksi menjadi `['auth:sanctum', 'user.active']`, sehingga middleware aktif berjalan sebelum `password.changed` pada rute yang memakainya.
- `backend/app/Http/Controllers/Api/UserController.php`:
  - Reset sandi tanpa isian menghasilkan 12 karakter acak berisi huruf dan angka, tanpa karakter ambigu `0`, `O`, `o`, `1`, `I`, `l`. Nilai dikembalikan sebagai `temporary_password` hanya pada respons reset.
  - Reset sandi, penonaktifan, dan penghapusan pengguna memanggil `UserSessionService::invalidate`.
  - Mutasi pengguna dicatat sebagai `create`, `update`, `reset_password`, `activate`, `deactivate`, dan `delete`.
  - Penghapusan akun yang menjabat ketua tim mengosongkan `ketua_id` pada setiap tim yang dipimpin, mencatat `update_ketua` pada objek `Team`, dan menambahkan `" Ketua tim dikosongkan."` pada pesan respons.
- `backend/app/Http/Controllers/Api/TeamController.php`:
  - Mutasi tim dicatat sebagai `create`, `update`, `update_ketua`, `update_members`, `add_member`, `remove_member`, dan `delete`.
  - Penghapusan anggota yang menjabat ketua tetap mengosongkan `ketua_id`; respons memuat `" Ketua tim dikosongkan."` dan audit `remove_member` menyimpan penanda `ketua_dikosongkan`.
- `backend/app/Http/Controllers/Api/SettingController.php`: setiap kunci yang nilainya benar-benar berubah dicatat sebagai `update` pada objek `Setting` beserta nilai lama dan nilai baru. Kunci yang nilainya sama dilewati.
- `backend/app/Http/Controllers/Api/AuthController.php`: pergantian kata sandi sendiri memanggil `UserSessionService::invalidate` dengan pengecualian ID sesi aktif.
- `frontend/src/pages/UsersPage.tsx`: modal kata sandi sementara sekali tampil, tombol salin, status `Tersalin`, pesan kegagalan salin manual, dan peringatan bahwa kata sandi tidak ditampilkan lagi. Reset tidak lagi menyebut sandi default `password`.
- `docs/CATATAN_KEPUTUSAN.md`: bagian 12 mencatat lima keputusan Fase 2 beserta alasannya.
- `backend/tests/Feature/SecurityAndWorkloadContractTest.php`: pengujian pengguna nonaktif diubah dari mengharapkan 403 menjadi 401 dengan pesan `"Akun Anda dinonaktifkan."`. Perubahan ini disengaja karena middleware `user.active` menolak lebih awal sebelum otorisasi peran tercapai.
- `backend/tests/Feature/TeamManagementTest.php`: ekspektasi pesan penghapusan ketua disesuaikan dengan tambahan `" Ketua tim dikosongkan."`.
- `backend/tests/Feature/UserManagementTest.php`: reset sandi manual diuji agar tidak mengembalikan `temporary_password`.

## 2. Keluaran Verifikasi Mentah

### 2.1 Pengujian terarah

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/AccountSecurityTest.php tests/Feature/AuditTrailTest.php
```

Keluaran:

```text
DB_DATABASE=jadwal_bps_test

   PASS  Tests\Feature\AccountSecurityTest
  ✓ inactive user with existing session is rejected                                                              1.59s
  ✓ reset password without input returns random temporary password                                               0.07s
  ✓ reset password removes database sessions                                                                     0.08s
  ✓ deactivating user removes sessions and is audited                                                            0.02s
  ✓ deleting ketua user clears ketua audits and removes sessions                                                 0.04s
  ✓ change password removes other database sessions                                                              0.03s
  ✓ session service keeps current session                                                                        0.03s

   PASS  Tests\Feature\AuditTrailTest
  ✓ user create and update are audited without credentials                                                       0.06s
  ✓ team mutations are audited                                                                                   0.06s
  ✓ setting update audits old and new values                                                                     0.06s

  Tests:    10 passed (71 assertions)
  Duration: 2.15s
```

### 2.2 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
  ✓ admin can update team and change ketua                                                                       0.04s
  ✓ designated ketua becomes member on store and update                                                          0.05s
  ✓ admin can add member and prevent duplicate membership                                                        0.04s
  ✓ admin can remove member and clears ketua if applicable                                                       0.04s
  ✓ admin can soft delete team                                                                                   0.03s

   PASS  Tests\Feature\UserManagementTest
  ✓ unauthenticated cannot access user endpoints                                                                 0.03s
  ✓ non admin cannot access user management                                                                      0.05s
  ✓ admin can view users list with pagination and search                                                         0.05s
  ✓ admin can create user with validation and must change password                                               0.03s
  ✓ admin can view user detail                                                                                   0.03s
  ✓ admin can update user                                                                                        0.04s
  ✓ admin cannot deactivate or delete own account                                                                0.04s
  ✓ admin can toggle user status and inactive user cannot login                                                  0.25s
  ✓ admin can reset user password                                                                                0.04s
  ✓ admin can soft delete user                                                                                   0.04s

  Tests:    120 passed (1252 assertions)
  Duration: 8.86s
```

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
LINES=33
WARNINGS=33
ERRORS=0
```

Jumlah tersebut sama dengan baseline Fase 1, yaitu 33 peringatan dan 0 error. Contoh peringatan yang tersisa:

```text
src/pages/UsersPage.tsx:4:3: warning eslint(no-unused-vars): Identifier 'Users' is imported but never used.
src/pages/UsersPage.tsx:82:5: warning react(set-state-in-effect): Calling setState synchronously within an effect can trigger cascading renders
src/pages/UsersPage.tsx:82:5: warning react-hooks(exhaustive-deps): React Hook useEffect has a missing dependency: 'fetchData'
```

### 2.5 Build frontend

Perintah:

```powershell
npm run build
```

Keluaran:

```text
✓ 2711 modules transformed.
dist/index.html                   0.45 kB │ gzip:   0.29 kB
dist/assets/index-DtSAYcUX.css   54.30 kB │ gzip:  10.13 kB
dist/assets/index-DPcfDxYU.js   934.10 kB │ gzip: 259.21 kB
(!) Some chunks are larger than 500 kB after minification.
✓ built in 634ms
```

Peringatan ukuran chunk sudah ada sebelum Fase 2.

### 2.6 Pemeriksaan larangan

Perintah:

```powershell
Get-ChildItem frontend\src -Recurse -File -Include *.ts,*.tsx | Select-String -Pattern "alert\("
```

Keluaran:

```text
=====ALERT=====
```

Tidak ada hasil.

Perintah:

```powershell
python -c "import pathlib,re; pat=re.compile('[\U0001F300-\U0001FAFF\u2600-\u27BF]'); [print(str(p)+':'+str(i+1)) for p in pathlib.Path('frontend/src').rglob('*') if p.suffix in ('.ts','.tsx') for i,l in enumerate(p.read_text(encoding='utf-8').splitlines()) if pat.search(l)]"
```

Keluaran:

```text
=====EMOJI=====
```

Tidak ada hasil.

### 2.7 Status berkas

Perintah:

```powershell
git status --short
git diff --stat
```

Keluaran:

```text
 M backend/app/Http/Controllers/Api/AuthController.php
 M backend/app/Http/Controllers/Api/SettingController.php
 M backend/app/Http/Controllers/Api/TeamController.php
 M backend/app/Http/Controllers/Api/UserController.php
 M backend/bootstrap/app.php
 M backend/routes/api.php
 M backend/tests/Feature/SecurityAndWorkloadContractTest.php
 M backend/tests/Feature/TeamManagementTest.php
 M backend/tests/Feature/UserManagementTest.php
 M docs/CATATAN_KEPUTUSAN.md
 M frontend/src/pages/UsersPage.tsx
?? backend/app/Http/Middleware/EnsureUserIsActive.php
?? backend/app/Services/AuditLogger.php
?? backend/app/Services/UserSessionService.php
?? backend/tests/Feature/AccountSecurityTest.php
?? backend/tests/Feature/AuditTrailTest.php

 .../app/Http/Controllers/Api/AuthController.php    |  11 ++
 .../app/Http/Controllers/Api/SettingController.php |  75 +++++++----
 .../app/Http/Controllers/Api/TeamController.php    |  73 ++++++++++-
 .../app/Http/Controllers/Api/UserController.php    | 143 +++++++++++++++++++--
 backend/bootstrap/app.php                          |   2 +
 backend/routes/api.php                             |   2 +-
 .../Feature/SecurityAndWorkloadContractTest.php    |   6 +-
 backend/tests/Feature/TeamManagementTest.php       |   2 +-
 backend/tests/Feature/UserManagementTest.php       |   3 +-
 docs/CATATAN_KEPUTUSAN.md                          |  13 ++
 frontend/src/pages/UsersPage.tsx                   |  88 ++++++++++++-
 11 files changed, 366 insertions(+), 52 deletions(-)
```

Tidak ada berkas di luar cakupan Fase 2 yang berubah. `PRD.md` tidak disentuh.

### 2.8 Database

Seluruh perintah pengujian dijalankan dengan `DB_DATABASE=jadwal_bps_test`. Tidak ada perintah `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed` yang dijalankan, baik terhadap `jadwal_bps` maupun `jadwal_bps_test`.

## 3. Hal yang Tidak Dapat Diverifikasi

- Penghapusan baris `sessions` hanya dapat diuji pada driver `database`. Berkas `backend/.env.example` masih memakai `SESSION_DRIVER=cookie`, sehingga pada konfigurasi contoh tersebut `UserSessionService::invalidate` melewati penghapusan baris dan hanya mengandalkan pembatalan sesi oleh framework. Perilaku ini sudah dicatat pada bagian 12 `docs/CATATAN_KEPUTUSAN.md`, tetapi efeknya pada lingkungan produksi yang memakai driver `cookie` belum diuji langsung.
- Modal kata sandi sementara belum diuji melalui peramban pada lebar 360px. Build dan lint lulus, tetapi perilaku visual, fokus, dan penyesuaian tata letak ponsel belum diverifikasi secara manual pada fase ini.
- Tombol salin memakai `navigator.clipboard.writeText`. Keberhasilan penyalinan bergantung pada konteks aman peramban (HTTPS atau `localhost`). Jalur kegagalan sudah menampilkan instruksi salin manual, tetapi penyalinan nyata tidak dapat diuji dari lingkungan ini.
- Perilaku `"Akun Anda dinonaktifkan."` pada antarmuka belum diuji menyeluruh. Interceptor frontend mengalihkan setiap 401 di luar `/login` dan `/me` ke `/login`, sehingga pengguna nonaktif akan melihat halaman login alih-alih pesan khusus. Hal ini dinilai dapat diterima, tetapi pesan khusus tersebut hanya terlihat melalui API, bukan sebagai notifikasi di layar.
- Audit log mutasi pengguna, tim, dan pengaturan sudah tertulis dan teruji di backend, tetapi belum ada tampilan riwayat audit khusus untuk objek-objek tersebut di frontend. Halaman audit yang ada hanya dipakai pada konteks kegiatan dan penugasan.
- Kata sandi sementara tidak diuji terhadap seluruh kombinasi karakter ambigu di luar ekspresi reguler pada pengujian. Pengujian memastikan panjang 12, adanya huruf dan angka, serta tidak adanya `0`, `O`, `o`, `1`, `I`, dan `l`.

## 4. Temuan di Luar Cakupan

- `frontend/src/pages/UsersPage.tsx` masih mengimpor `Users` dari `lucide-react` tanpa memakainya. Peringatan ini sudah ada pada baseline dan tidak diubah agar cakupan Fase 2 tetap terbatas.
- Peringatan `react(set-state-in-effect)` dan `react-hooks(exhaustive-deps)` pada `UsersPage.tsx`, `ActivitiesPage.tsx`, `IssuesPage.tsx`, `CalendarPage.tsx`, dan halaman lain masih tersisa. Semuanya bagian dari 33 peringatan baseline.
- Berkas JavaScript hasil build berukuran sekitar 934 kB dan memicu peringatan ukuran chunk. Peringatan ini sudah ada sebelum Fase 2 dan bukan akibat perubahan fase ini.
- Middleware `user.active` menolak pengguna nonaktif dengan 401, bukan 403. Interceptor frontend memperlakukan 401 sebagai sesi berakhir dan mengalihkan ke halaman login. Bila diinginkan pesan khusus di layar, interceptor perlu dibedakan berdasarkan isi respons, dan itu berada di luar cakupan Fase 2.
- `UserController::store` masih memakai `'password'` sebagai sandi awal bila admin tidak mengisi kata sandi, dengan `must_change_password = true`. Fase 2 hanya mengganti perilaku reset sandi, bukan pembuatan akun, karena permintaan fase menyebut reset sandi sementara.
- `AuthController::changePassword` kini memutus sesi lain, tetapi belum mencatat pergantian kata sandi ke `audit_logs`. Permintaan Fase 2 menyebut audit untuk pengguna, tim, dan pengaturan, sehingga pergantian kata sandi pribadi tidak termasuk.
- Tabel `sessions` hanya ada pada migrasi bawaan Laravel. Bila driver sesi diubah ke `database` pada lingkungan yang belum menjalankan migrasi tersebut, `UserSessionService` tidak akan menemukan tabel. Migrasi tidak dijalankan pada fase ini karena termasuk perubahan skema di luar cakupan.

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps`. Seluruh query berikut tidak mengubah data.

### 5.1 Jejak audit mutasi pengguna, tim, dan pengaturan

```sql
SELECT id, user_id, aksi, objek, objek_id, sebelum, sesudah, created_at
FROM audit_logs
WHERE objek IN ('User', 'Team', 'Setting')
ORDER BY id DESC
LIMIT 20;
```

Hasil yang diharapkan: baris terbaru memuat aksi seperti `create`, `update`, `reset_password`, `deactivate`, `delete`, `update_ketua`, `add_member`, `remove_member`, dan `update` pada `Setting`. Kolom `sebelum` dan `sesudah` tidak boleh memuat kata sandi, hash, atau token.

### 5.2 Memastikan tidak ada kata sandi pada jejak audit

```sql
SELECT id, aksi, objek, objek_id
FROM audit_logs
WHERE JSON_SEARCH(sebelum, 'one', '%password%') IS NOT NULL
   OR JSON_SEARCH(sesudah, 'one', '%password%') IS NOT NULL
   OR JSON_SEARCH(sesudah, 'one', '$2y$%') IS NOT NULL
   OR JSON_SEARCH(sesudah, 'one', '$argon2%') IS NOT NULL
ORDER BY id DESC
LIMIT 20;
```

Hasil yang diharapkan: tidak ada baris. Bila ada baris, berarti ada mutasi yang menuliskan atribut kredensial ke audit log dan perlu diperiksa.

### 5.3 Akun nonaktif yang masih memiliki sesi aktif

```sql
SELECT s.id AS session_id, s.user_id, u.nama, u.email, s.last_activity
FROM sessions s
JOIN users u ON u.id = s.user_id
WHERE u.aktif = 0
ORDER BY s.last_activity DESC
LIMIT 20;
```

Hasil yang diharapkan: tidak ada baris pada lingkungan yang memakai `SESSION_DRIVER=database`. Bila ada baris, sesi tersebut seharusnya sudah diputus saat penonaktifan atau penghapusan akun.

### 5.4 Tim tanpa ketua setelah pengosongan

```sql
SELECT t.id, t.nama, t.ketua_id, t.aktif
FROM teams t
WHERE t.ketua_id IS NULL
  AND t.deleted_at IS NULL
ORDER BY t.id;
```

Hasil yang diharapkan: menampilkan tim aktif yang memang tidak memiliki ketua, termasuk tim yang ketuanya dihapus atau dikeluarkan dari keanggotaan. Query ini dipakai untuk memastikan pengosongan `ketua_id` terjadi pada data nyata, bukan hanya pada pengujian.

### 5.5 Riwayat pengosongan ketua pada audit log

```sql
SELECT id, user_id, aksi, objek, objek_id, sebelum, sesudah, created_at
FROM audit_logs
WHERE objek = 'Team'
  AND aksi IN ('update_ketua', 'remove_member')
  AND JSON_EXTRACT(sesudah, '$.ketua_id') IS NULL
ORDER BY id DESC
LIMIT 20;
```

Hasil yang diharapkan: menampilkan catatan pengosongan ketua, baik karena anggota ketua dikeluarkan maupun karena akun ketua dihapus.
