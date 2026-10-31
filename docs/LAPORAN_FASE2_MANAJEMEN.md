# LAPORAN IMPLEMENTASI FASE 2: MANAJEMEN PENGGUNA, TIM, DAN PENGATURAN
**Proyek:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang  
**Sumber Kebenaran:** `PRD.md` (Bagian 0, 5, 8.4, 12.2 S9, 12.2 S11, 13, 14, 16 Kriteria 14)  
**Tanggal Selesai:** 28 Oktober 2026  
**Status:** Berhasil Diimplementasikan dan Lulus 100% Pengujian Otomatis  

---

## 1. Ringkasan Eksekutif

Tahap implementasi Fase 2 (Manajemen Pengguna, Tim Kerja, Keanggotaan Tim, dan Pengaturan Sistem) telah diselesaikan secara menyeluruh tanpa mengubah atau merusak skema database `jadwal_bps` dan data seeder yang telah ada.

Seluruh endpoint terintegrasi langsung dengan mekanisme autentikasi Laravel Sanctum SPA berbasis cookie dan middleware penegakan kewajiban ganti sandi (`password.changed`) dari Fase 1. Otorisasi server ditegakkan secara ketat menggunakan **Laravel Policy** untuk memastikan kepatuhan mutlak terhadap matriks hak akses PRD Bagian 5.

---

## 2. Fitur & Endpoint Manajemen Pengguna

Seluruh operasi manajemen pengguna berada di bawah pengawasan **khusus peran Admin** (`UserPolicy`). Pengguna dengan peran `pegawai` atau `pimpinan` yang mencoba mengakses endpoint ini ditolak dengan `HTTP 403 Forbidden`.

### A. Daftar Endpoint Pengguna
| Metode | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/users` | Admin | Menampilkan daftar pengguna dengan server-side pagination, pencarian, dan filtering. |
| `POST` | `/api/users` | Admin | Membuat akun pengguna baru. |
| `GET` | `/api/users/{id}` | Admin | Melihat detail profil lengkap satu pengguna beserta timnya. |
| `PUT/PATCH` | `/api/users/{id}` | Admin | Memperbarui data profil, peran, jabatan, atau keanggotaan tim pengguna. |
| `DELETE` | `/api/users/{id}` | Admin | Menonaktifkan / menghapus pengguna secara logis (*soft delete*). |
| `PATCH` | `/api/users/{id}/status` | Admin | Mengubah status aktif/nonaktif akun secara cepat. |
| `PATCH` | `/api/users/{id}/reset-password` | Admin | Mereset kata sandi pengguna secara manual ke kata sandi sementara. |

### B. Parameter Query pada `GET /api/users`
- `page`: Nomor halaman (integer, default 1).
- `per_page`: Jumlah entri per halaman (integer, default 15, maksimal 100).
- `search`: Kata kunci pencarian parsial (*case-insensitive*) pada kolom `nama`, `email`, atau `nip`.
- `peran`: Filter peran pengguna (`admin`, `pegawai`, `pimpinan`).
- `aktif`: Filter boolean status aktif akun (`true`/`false` atau `1`/`0`).
- `team_id`: Filter pengguna yang terdaftar sebagai anggota tim dengan ID tertentu.

### C. Aturan Validasi & Proteksi Bisnis Pengguna
1. **Pencegahan Penghancuran Diri Sendiri (*Self-Destruction Protection*):**
   - Admin yang sedang login ditolak (`HTTP 422`) apabila mencoba menonaktifkan akunnya sendiri (`aktif = false`), mengubah perannya sendiri menjadi non-admin, atau menghapus akunnya sendiri.
2. **Kewajiban Ganti Kata Sandi Pertama Kali:**
   - Setiap akun yang dibuat melalui `POST /api/users` otomatis memiliki atribut `must_change_password = true`.
   - Setiap reset kata sandi melalui `PATCH /api/users/{id}/reset-password` otomatis menyetel kembali `must_change_password = true`.
3. **Pencegahan Kebocoran Kredensial:**
   - Respon API menggunakan `UserResource`, memastikan atribut `password` dan `remember_token` tidak pernah dikembalikan ke klien.
   - Kata sandi selalu di-hash menggunakan algoritma Bcrypt bawaan Laravel (rounds 12).
4. **Pencegahan Login Akun Nonaktif:**
   - Pengguna dengan status `aktif = false` ditolak saat mencoba login dengan respon `HTTP 422` seragam (*Anti-Enumeration*).

---

## 3. Fitur & Endpoint Manajemen Tim Kerja & Keanggotaan

Mengacu pada PRD Bagian 5 & 14, pembacaan data tim terbuka untuk semua pengguna terautentikasi (agar pegawai dan pimpinan dapat melihat struktur tim kerja BPS), sedangkan pembuatan, perubahan, penghapusan tim, dan pengelolaan anggota dibatasi **khusus peran Admin**.

### A. Daftar Endpoint Tim
| Metode | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/teams` | Semua Terautentikasi | Menampilkan daftar seluruh tim kerja, ketua tim, dan anggotanya. |
| `POST` | `/api/teams` | Admin | Membuat tim kerja baru dan menetapkan ketua tim. |
| `GET` | `/api/teams/{id}` | Semua Terautentikasi | Melihat detail satu tim kerja beserta ketua dan anggotanya. |
| `PUT/PATCH` | `/api/teams/{id}` | Admin | Memperbarui nama tim, mengganti ketua tim, atau status tim. |
| `DELETE` | `/api/teams/{id}` | Admin | Menghapus tim kerja secara logis (*soft delete*). |
| `GET` | `/api/teams/{id}/members` | Semua Terautentikasi | Melihat daftar seluruh pegawai yang menjadi anggota tim ini. |
| `POST` | `/api/teams/{id}/members` | Admin | Mendaftarkan seorang pegawai ke dalam tim kerja. |
| `DELETE` | `/api/teams/{id}/members/{userId}` | Admin | Mencabut keanggotaan seorang pegawai dari tim kerja. |

### B. Aturan Bisnis & Integritas Relasi Tim
1. **Ketentuan Ketua Tim:**
   - Ketua tim bukan merupakan peran sistem tersendiri, melainkan atribut `ketua_id` pada tabel `teams` yang merujuk pada pengguna dengan peran `pegawai` atau `pimpinan`.
   - Pengguna yang dipilih sebagai ketua tim **wajib berstatus aktif** (`aktif = true`). Pemilihan pengguna nonaktif ditolak dengan `HTTP 422`.
   - Saat ketua tim ditetapkan atau diganti, sistem secara atomik memastikan ketua tim tersebut juga terdaftar dalam daftar anggota tim (`team_members`).
2. **Penanganan Pelepasan Keanggotaan Ketua Tim:**
   - Jika admin mencabut keanggotaan seorang pegawai dari tim (`DELETE /api/teams/{id}/members/{userId}`) dan pegawai tersebut kebetulan sedang menjabat sebagai ketua tim pada tim bersangkutan, sistem secara otomatis mengosongkan nilai `ketua_id` (`null`) dalam satu transaksi database untuk mencegah ketidakkonsistenan.
3. **Pencegahan Duplikasi Keanggotaan:**
   - Menambahkan pengguna yang sudah menjadi anggota tim akan ditolak dengan respon `HTTP 422: Pengguna sudah terdaftar sebagai anggota pada tim ini.`
4. **Keanggotaan Multi-Tim:**
   - Sesuai PRD, satu pegawai dapat terdaftar di lebih dari satu tim kerja.

---

## 4. Fitur & Endpoint Pengaturan Aplikasi

Mengelola konfigurasi ambang batas dan hak akses dinamis sesuai spesifikasi PRD Bagian 0 aturan 7, Bagian 12.2 S11, Bagian 13, dan Bagian 14.

### A. Daftar Endpoint Pengaturan
| Metode | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/settings` | Semua Terautentikasi | Membaca konfigurasi pengaturan sistem. |
| `PUT/PATCH` | `/api/settings` | Admin | Memperbarui nilai pengaturan sistem. |

### B. Kunci Pengaturan yang Dikelola
1. `ambang_beban_bulanan` (Bawaan: `100`, tipe: integer/numeric, batas: min 1).
2. `maks_penugasan_bersamaan` (Bawaan: `3`, tipe: integer, batas: min 1).
3. `maks_kegiatan_besar_bersamaan` (Bawaan: `3`, tipe: integer, batas: min 1).
4. `pegawai_boleh_isi_kendala` (Bawaan: `false`, tipe: boolean).

### C. Fleksibilitas Format Request Pembaruan
Endpoint `PUT /api/settings` mendukung dua format payload yang ramah bagi integrasi frontend:
1. **Format Flat Object:**
   ```json
   {
     "ambang_beban_bulanan": 120,
     "pegawai_boleh_isi_kendala": true
   }
   ```
2. **Format Array Settings:**
   ```json
   {
     "settings": [
       { "kunci": "maks_penugasan_bersamaan", "nilai": 4 }
     ]
   }
   ```

---

## 5. Matriks Hak Akses & Kebijakan Otorisasi Server

| Fitur / Tindakan | Admin | Pimpinan | Ketua Tim | Pegawai Biasa | Mekanisme Penegakan |
|---|:---:|:---:|:---:|:---:|---|
| Lihat Daftar Pengguna (`GET /api/users`) | Ya | Tidak | Tidak | Tidak | `UserPolicy@viewAny` |
| Buat / Ubah / Hapus Pengguna | Ya | Tidak | Tidak | Tidak | `UserPolicy@create,update,delete` |
| Ubah Status / Reset Sandi Pengguna | Ya | Tidak | Tidak | Tidak | `UserPolicy@update` |
| Lihat Tim & Anggota Tim | Ya | Ya | Ya | Ya | `TeamPolicy@viewAny,view` |
| Buat / Ubah / Hapus Tim | Ya | Tidak | Tidak | Tidak | `TeamPolicy@create,update,delete` |
| Tambah / Hapus Anggota Tim | Ya | Tidak | Tidak | Tidak | `TeamPolicy@manageMembers` |
| Lihat Pengaturan Aplikasi | Ya | Ya | Ya | Ya | `SettingPolicy@viewAny` |
| Ubah Pengaturan Aplikasi | Ya | Tidak | Tidak | Tidak | `SettingPolicy@update` |

---

## 6. Hasil Verifikasi dan Pengujian Otomatis

Pengujian dijalankan langsung pada environment pengujian menggunakan database MariaDB lokal `jadwal_bps` tanpa merusak data asli.

### A. Rincian Pengujian Fitur
- **`UserManagementTest` (10 pengujian, 193 assertions):**
  - Akses unauthenticated ditolak 401.
  - Akses non-admin (pegawai & pimpinan) ditolak 403.
  - Admin dapat melihat daftar dengan paginasi dan pencarian.
  - Admin dapat membuat pengguna dengan validasi dan penegakan `must_change_password`.
  - Admin dapat melihat detail pengguna.
  - Admin dapat memperbarui data pengguna.
  - Admin ditolak menonaktifkan atau menghapus akunnya sendiri (422).
  - Admin dapat mengubah status aktif dan verifikasi akun nonaktif ditolak login.
  - Admin dapat mereset kata sandi pengguna secara manual.
  - Admin dapat menghapus (soft delete) pengguna.
- **`TeamManagementTest` (9 pengujian, 91 assertions):**
  - Akses unauthenticated ditolak 401.
  - Semua peran dapat melihat daftar tim dan anggota tim.
  - Non-admin ditolak saat mencoba mutasi tim atau anggota (403).
  - Admin dapat membuat tim dan ketua otomatis masuk anggota.
  - Admin ditolak memilih ketua tim yang tidak aktif (422).
  - Admin dapat memperbarui tim dan mengganti ketua tim.
  - Admin dapat menambah anggota dan duplikasi ditolak (422).
  - Admin dapat mencabut anggota dan status ketua dinolkan jika anggota tersebut ketua tim.
  - Admin dapat menghapus (soft delete) tim.
- **`SettingManagementTest` (5 pengujian, 36 assertions):**
  - Akses unauthenticated ditolak 401.
  - Semua peran dapat melihat pengaturan aplikasi.
  - Non-admin ditolak mengubah pengaturan (403).
  - Admin dapat memperbarui pengaturan dengan validasi angka positif.
  - Admin dapat memperbarui pengaturan via format array.
- **`AuthenticationTest` (16 pengujian, 87 assertions):**
  - Seluruh alur login, logout, me, change-password, rate limit, dan penolakan akun nonaktif tetap 100% lulus.
- **`DatabaseDataIntegrityTest` & `DatabaseSchemaTest` (19 pengujian, 195 assertions):**
  - Integritas skema 9 tabel domain dan integritas data seeder tetap 100% utuh.

### B. Hasil Keseluruhan Test Suite
```
Tests:    60 passed (603 assertions)
Duration: 4.18s
```

### C. Pemeriksaan Format Kode (Laravel Pint)
```
./vendor/bin/pint --test
{"tool":"pint","result":"passed"}
Exit Code: 0
```
Seluruh berkas PHP memenuhi standar konvensi PSR-12 dan aturan Laravel Pint.

---

## 7. Berkas yang Dibuat & Diubah pada Fase 2

1. **`backend/app/Http/Controllers/Controller.php`** — Ditambahkan trait `AuthorizesRequests` untuk mendukung otorisasi `$this->authorize()`.
2. **`backend/app/Http/Controllers/Api/UserController.php`** — Controller manajemen pengguna lengkap.
3. **`backend/app/Http/Controllers/Api/TeamController.php`** — Controller tim kerja dan keanggotaan tim.
4. **`backend/app/Http/Controllers/Api/SettingController.php`** — Controller konfigurasi pengaturan aplikasi.
5. **`backend/app/Policies/UserPolicy.php`** — Kebijakan otorisasi pengguna (khusus admin).
6. **`backend/app/Policies/TeamPolicy.php`** — Kebijakan otorisasi tim kerja.
7. **`backend/app/Policies/SettingPolicy.php`** — Kebijakan otorisasi pengaturan sistem.
8. **`backend/app/Http/Requests/CreateUserRequest.php`** — Form request validasi pembuatan akun.
9. **`backend/app/Http/Requests/UpdateUserRequest.php`** — Form request validasi pembaruan profil pengguna.
10. **`backend/app/Http/Requests/UpdateUserStatusRequest.php`** — Form request validasi status aktif akun.
11. **`backend/app/Http/Requests/ResetUserPasswordRequest.php`** — Form request validasi reset kata sandi sementara.
12. **`backend/app/Http/Requests/CreateTeamRequest.php`** — Form request validasi pembuatan tim.
13. **`backend/app/Http/Requests/UpdateTeamRequest.php`** — Form request validasi pembaruan tim.
14. **`backend/app/Http/Requests/AddTeamMemberRequest.php`** — Form request validasi pendaftaran anggota tim.
15. **`backend/app/Http/Requests/UpdateSettingRequest.php`** — Form request validasi konfigurasi pengaturan.
16. **`backend/app/Http/Resources/TeamResource.php`** — Resource transformasi data tim dan relasi anggotanya.
17. **`backend/app/Http/Resources/SettingResource.php`** — Resource transformasi entitas pengaturan.
18. **`backend/app/Http/Resources/UserResource.php`** — Pembaruan resource untuk mendukung eager load tim kerja.
19. **`backend/app/Http/Requests/LoginRequest.php`** — Penargetan eksplisit guard `web` pada `Auth::guard('web')->attempt()`.
20. **`backend/routes/api.php`** — Pendaftaran 17 rute API baru di bawah middleware `auth:sanctum` dan `password.changed`.
21. **`backend/tests/Feature/UserManagementTest.php`** — Test feature pengguna (10 skenario).
22. **`backend/tests/Feature/TeamManagementTest.php`** — Test feature tim kerja (9 skenario).
23. **`backend/tests/Feature/SettingManagementTest.php`** — Test feature pengaturan (5 skenario).
24. **`docs/CATATAN_KEPUTUSAN.md`** — Pencatatan Bagian 8 terkait arsitektur manajemen pengguna, tim, dan pengaturan.
25. **`docs/LAPORAN_FASE2_MANAJEMEN.md`** — Dokumen laporan komprehensif implementasi Fase 2.

---

## 8. Panduan Integrasi React Frontend

Pada frontend React (Vite):
```typescript
import { apiClient } from './apiClient';

// Mengambil daftar pengguna dengan filter dan paginasi
export async function getUsers(params?: { search?: string; peran?: string; aktif?: boolean; page?: number }) {
  const response = await apiClient.get('/api/users', { params });
  return response.data; // { data: [...], links: {...}, meta: {...} }
}

// Mengambil daftar tim kerja
export async function getTeams(params?: { search?: string; aktif?: boolean }) {
  const response = await apiClient.get('/api/teams', { params });
  return response.data.data;
}

// Mengambil konfigurasi pengaturan sistem
export async function getSettings() {
  const response = await apiClient.get('/api/settings');
  return response.data.settings; // { ambang_beban_bulanan: 100, ... }
}

// Memperbarui pengaturan (khusus Admin)
export async function updateSettings(settings: Record<string, any>) {
  const response = await apiClient.put('/api/settings', settings);
  return response.data;
}
```

---

## 9. Rekomendasi Fase Selanjutnya

Mengacu pada PRD Bagian 20 (Fase 3):
1. **Fase 3: Kegiatan, Penugasan, dan Kalender:**
   - CRUD Kegiatan (`/api/activities`, `/api/activities/{id}`) untuk Admin dan Ketua Tim (khusus timnya).
   - Penandaan kegiatan selesai atau buka kembali (`PATCH /activities/{id}/complete`, `PATCH /activities/{id}/reopen`).
   - CRUD Penugasan (`/api/activities/{id}/assignments`, `/api/assignments/{id}`).
   - Endpoint Kalender lintas tim (`/api/calendar?start&end&team_id&user_id&status`).
   - Pratinjau dampak penugasan terhadap beban pegawai sebelum disimpan (`POST /api/assignments/preview`).

