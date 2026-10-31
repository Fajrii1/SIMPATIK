# LAPORAN IMPLEMENTASI FASE 1: AUTENTIKASI LARAVEL SANCTUM
**Proyek:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang  
**Sumber Kebenaran:** `PRD.md` (Bagian 0, 5, 8.4, 9.2–9.3, 12.2 S1, 14, 16, 17)  
**Tanggal Selesai:** 28 Oktober 2026  
**Status:** Berhasil Diimplementasikan dan Lulus 100% Pengujian Otomatis  

---

## 1. Ringkasan Eksekutif

Tahap implementasi Fase 1 Autentikasi Backend telah diselesaikan secara menyeluruh tanpa mengubah struktur database yang telah diaudit pada tahap sebelumnya. 

Implementasi menggunakan pendekatan **Laravel Sanctum SPA mode berbasis cookie session**, menyelaraskan backend Laravel REST API dengan frontend React + TypeScript (Vite) sesuai spesifikasi Bagian 9.2 dan 9.3 PRD. Seluruh aturan bisnis autentikasi, perlindungan keamanan, pembatasan percobaan login (rate limiting), penolakan akun nonaktif, serta isolasi alur kewajiban penggantian kata sandi pertama (`must_change_password`) telah terverifikasi melalui pengujian otomatis.

---

## 2. Arsitektur & Konfigurasi Autentikasi Sanctum SPA

Sesuai arahan PRD, aplikasi tidak mencampur mekanisme token Bearer statis dan session cookie secara tidak konsisten. Autentikasi dirancang murni berbasis SPA cookie yang aman dari kerentanan XSS (*Cross-Site Scripting*).

### A. Alur Kerja SPA Cookie
1. **Inisialisasi CSRF:** Frontend memanggil `GET http://localhost:8000/sanctum/csrf-cookie` sebelum login.
2. **Pengiriman Kredensial:** Frontend mengirim `POST http://localhost:8000/api/login` dengan opsi `withCredentials: true` dan menyertakan header `X-XSRF-TOKEN`.
3. **Regenerasi Sesi:** Backend memverifikasi kredensial, memastikan `aktif = true`, memulai sesi Laravel, meregenerasi ID sesi untuk mencegah *Session Fixation*, dan menetapkan cookie sesi `jadwal_bps_session`.
4. **Permintaan Terotentikasi:** Permintaan selanjutnya (`/api/me`, `/api/change-password`, dll.) secara otomatis menyertakan cookie sesi HTTP-only.
5. **Logout:** Backend menghapus otentikasi guard `web`, menginvalisasi sesi, dan meregenerasi CSRF token.

### B. Konfigurasi Lingkungan (`.env` & `config/`)
- **Origin Frontend:** `http://localhost:5173`
- **Domain Sesi:** `SESSION_DOMAIN=localhost`
- **Driver Sesi:** `SESSION_DRIVER=cookie`
- **Sanctum Stateful Domains:** `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost,127.0.0.1`
- **CORS (`config/cors.php`):**
  - `paths => ['api/*', 'sanctum/csrf-cookie']`
  - `allowed_origins => ['http://localhost:5173', 'http://127.0.0.1:5173']`
  - `supports_credentials => true`
- **Sanctum Middleware (`bootstrap/app.php`):**
  - `$middleware->statefulApi()` didaftarkan untuk menyisipkan `EnsureFrontendRequestsAreStateful` ke grup middleware API.
  - `$middleware->alias(['password.changed' => EnsurePasswordChanged::class])`.

---

## 3. Spesifikasi Endpoint API

Semua endpoint berada di bawah prefix `/api` kecuali inisialisasi cookie Sanctum.

### 1. Inisialisasi Cookie CSRF
- **Metode & URL:** `GET /sanctum/csrf-cookie`
- **Akses:** Publik
- **Fungsi:** Mengirimkan cookie `XSRF-TOKEN` ke browser klien.
- **Respon:** HTTP 204 No Content

---

### 2. Login Pengguna
- **Metode & URL:** `POST /api/login`
- **Akses:** Publik
- **Form Request:** `App\Http\Requests\LoginRequest`
- **Rate Limit:** 5 kali percobaan gagal per menit per kombinasi `email|IP`.
- **Request Body:**
  ```json
  {
    "email": "admin@contoh.test",
    "password": "password",
    "remember": false
  }
  ```
- **Respon Berhasil (HTTP 200 OK):**
  ```json
  {
    "message": "Login berhasil.",
    "user": {
      "id": 1,
      "nama": "Administrator BPS",
      "email": "admin@contoh.test",
      "peran": "admin",
      "nip": "198001012005011001",
      "jabatan": "Pranata Komputer Ahli Muda",
      "aktif": true,
      "must_change_password": false,
      "created_at": "2026-10-28T09:48:30.000000Z",
      "updated_at": "2026-10-28T09:48:30.000000Z"
    }
  }
  ```
- **Respon Kredensial Salah / Akun Nonaktif / Email Tidak Ada (HTTP 422 Unprocessable Content):**
  ```json
  {
    "message": "Email atau kata sandi salah.",
    "errors": {
      "email": [
        "Email atau kata sandi salah."
      ]
    }
  }
  ```
- **Respon Terlalu Banyak Percobaan (HTTP 429 Too Many Requests):**
  ```json
  {
    "message": "Terlalu banyak percobaan login. Silakan coba lagi dalam 60 detik.",
    "errors": {
      "email": [
        "Terlalu banyak percobaan login. Silakan coba lagi dalam 60 detik."
      ]
    }
  }
  ```

---

### 3. Profil Pengguna yang Sedang Login
- **Metode & URL:** `GET /api/me`
- **Akses:** Terautentikasi (`auth:sanctum`), wajib telah mengganti kata sandi (`password.changed`).
- **Respon Berhasil (HTTP 200 OK):**
  ```json
  {
    "user": {
      "id": 1,
      "nama": "Administrator BPS",
      "email": "admin@contoh.test",
      "peran": "admin",
      "nip": "198001012005011001",
      "jabatan": "Pranata Komputer Ahli Muda",
      "aktif": true,
      "must_change_password": false,
      "created_at": "2026-10-28T09:48:30.000000Z",
      "updated_at": "2026-10-28T09:48:30.000000Z"
    }
  }
  ```
- **Respon Belum Mengganti Sandi Pertama (HTTP 403 Forbidden):**
  ```json
  {
    "message": "Anda harus mengganti kata sandi terlebih dahulu.",
    "must_change_password": true
  }
  ```
- **Respon Tanpa Autentikasi (HTTP 401 Unauthorized):**
  ```json
  {
    "message": "Unauthenticated."
  }
  ```

---

### 4. Ubah Kata Sandi
- **Metode & URL:** `POST /api/change-password`
- **Akses:** Terautentikasi (`auth:sanctum`). *Catatan: Pengguna dengan `must_change_password = true` diizinkan mengakses endpoint ini.*
- **Form Request:** `App\Http\Requests\ChangePasswordRequest`
- **Aturan Validasi:**
  - `current_password`: `required|string|current_password`
  - `password`: `required|string|min:8|confirmed|different:current_password`
  - `password_confirmation`: `required|string`
- **Request Body:**
  ```json
  {
    "current_password": "password",
    "password": "kataSandiBaru#2026",
    "password_confirmation": "kataSandiBaru#2026"
  }
  ```
- **Respon Berhasil (HTTP 200 OK):**
  ```json
  {
    "message": "Kata sandi berhasil diubah.",
    "user": {
      "id": 2,
      "nama": "Kepala BPS Palembang",
      "email": "kepala@contoh.test",
      "peran": "pimpinan",
      "nip": "197505121998031002",
      "jabatan": "Kepala BPS Kota Palembang",
      "aktif": true,
      "must_change_password": false,
      "created_at": "2026-10-28T09:48:30.000000Z",
      "updated_at": "2026-10-28T10:15:00.000000Z"
    }
  }
  ```
- **Respon Validasi Gagal (HTTP 422 Unprocessable Content):**
  Contoh jika kata sandi lama salah:
  ```json
  {
    "message": "Kata sandi saat ini tidak cocok.",
    "errors": {
      "current_password": [
        "Kata sandi saat ini tidak cocok."
      ]
    }
  }
  ```

---

### 5. Keluar (Logout)
- **Metode & URL:** `POST /api/logout`
- **Akses:** Terautentikasi (`auth:sanctum`)
- **Respon Berhasil (HTTP 200 OK):**
  ```json
  {
    "message": "Berhasil keluar."
  }
  ```

---

## 4. Mekanisme Keamanan & Integritas Bisnis

| Aspek Keamanan | Implementasi Teknis | Referensi PRD |
|---|---|---|
| **Pencegahan User Enumeration** | `LoginRequest` menggunakan `Auth::attempt($credentials + ['aktif' => true])`. Jika pengguna tidak terdaftar, kata sandi salah, atau akun nonaktif, pesan respon identik: *"Email atau kata sandi salah."* (HTTP 422). | PRD Bagian 12.2 S1 |
| **Penolakan Akun Nonaktif** | Kriteria `'aktif' => true` diperiksa langsung saat percobaan autentikasi. Akun berstatus `aktif = false` ditolak secara mutlak. | PRD Bagian 8.4 & 16 (Kriteria 15) |
| **Kewajiban Ganti Kata Sandi** | Middleware `EnsurePasswordChanged` (`password.changed`) mengintersepsi semua rute terproteksi kecuali `/api/change-password` dan `/api/logout`, mengembalikan HTTP 403 jika `must_change_password = true`. | PRD Bagian 5 & 16 (Kriteria 15) |
| **Pembatasan Percobaan (Rate Limiting)** | Menggunakan Laravel `RateLimiter` dengan batas 5 kali kegagalan per 60 detik per `email|IP`. Mengembalikan HTTP 429 Too Many Requests jika terlampaui. | PRD Bagian 12.2 S1 |
| **Penyembunyian Kredensial** | Kolom `password` dan `remember_token` disembunyikan via `$hidden` pada model `User` dan tidak pernah dimuat di `UserResource`. Kata sandi selalu di-hash menggunakan algoritma Bcrypt (12 rounds). | PRD Bagian 18 |
| **Proteksi Akses Tanpa Sesi** | Seluruh rute terproteksi dibungkus middleware `auth:sanctum` yang mengembalikan HTTP 401 jika unauthenticated. | PRD Bagian 16 (Kriteria 17) |

---

## 5. Dokumentasi Kredensial Dummy untuk Lingkungan Pengujian Lokal

> [!WARNING]
> Kredensial dummy di bawah ini **HANYA BERLAKU UNTUK LINGKUNGAN PENGEMBANGAN LOKAL**. Jangan pernah menggunakan kredensial dummy di lingkungan *staging* atau produksi.

Seluruh akun dummy yang dihasilkan oleh `UserSeeder` memiliki kata sandi awal: `password`.

| No | Nama | Email | Peran | NIP | `must_change_password` | Keterangan |
|:--:|---|---|---|---|:--:|---|
| 1 | Administrator BPS | `admin@contoh.test` | `admin` | `198001012005011001` | `false` | Akun admin utama, siap login langsung |
| 2 | Kepala BPS Palembang | `kepala@contoh.test` | `pimpinan` | `197505121998031002` | `true` | Pimpinan & Ketua Tim 06, wajib ganti sandi saat login pertama |
| 3 | Ketua Tim 01 | `ketua1@contoh.test` | `pegawai` | `198501012008011001` | `true` | Ketua Tim Statistik Sosial |
| 4 | Ketua Tim 02 | `ketua2@contoh.test` | `pegawai` | `198501022008011002` | `true` | Ketua Tim Statistik Produksi |
| 5 | Ketua Tim 03 | `ketua3@contoh.test` | `pegawai` | `198501032008011003` | `true` | Ketua Tim Statistik Distribusi |
| 6 | Ketua Tim 04 | `ketua4@contoh.test` | `pegawai` | `198501042008011004` | `true` | Ketua Tim Nerwilis |
| 7 | Ketua Tim 05 | `ketua5@contoh.test` | `pegawai` | `198501052008011005` | `true` | Ketua Tim IPDS |
| 8–17 | Pegawai 01 s.d. 10 | `pegawai01@contoh.test` s/d `pegawai10@contoh.test` | `pegawai` | `19900201...` | `true` | Anggota tim, wajib ganti sandi saat login pertama |

---

## 6. Hasil Verifikasi dan Pengujian Otomatis

### A. Pengujian Fitur Autentikasi (`AuthenticationTest`)
Berkas pengujian dibuat di `backend/tests/Feature/AuthenticationTest.php` mencakup 16 skenario pengujian menyeluruh:

1. `test_login_validation_requires_email_and_password` — Validasi kolom kosong (422) [PASS]
2. `test_login_validation_requires_valid_email_format` — Validasi format email tidak valid (422) [PASS]
3. `test_login_successful_with_valid_credentials` — Login berhasil dengan struktur respon lengkap (200) [PASS]
4. `test_login_fails_with_invalid_password` — Kata sandi salah ditolak (422) [PASS]
5. `test_login_fails_with_unregistered_email_without_leaking_existence` — Email tidak terdaftar ditolak dengan pesan identik (422) [PASS]
6. `test_login_rejected_for_inactive_user` — Pengguna `aktif = false` ditolak (422) [PASS]
7. `test_login_rate_limiting_triggers_after_max_attempts` — Pembatasan percobaan login pada upaya ke-6 (429) [PASS]
8. `test_unauthenticated_access_to_protected_endpoints_is_rejected` — Akses tanpa login ke `/me`, `/logout`, `/change-password` ditolak (401) [PASS]
9. `test_authenticated_user_can_access_me_endpoint` — Pengguna terautentikasi dapat mengakses `/me` (200) [PASS]
10. `test_user_with_must_change_password_is_blocked_from_me_endpoint` — Pengguna `must_change_password = true` diblokir dari `/me` (403) [PASS]
11. `test_must_change_password_flow_and_subsequent_access` — Alur lengkap: blokir `/me` -> akses ganti sandi -> sandi ter-hash -> `must_change_password = false` -> akses `/me` terbuka (200) [PASS]
12. `test_change_password_fails_if_current_password_is_incorrect` — Kata sandi saat ini salah ditolak (422) [PASS]
13. `test_change_password_fails_if_new_password_is_too_short` — Kata sandi baru < 8 karakter ditolak (422) [PASS]
14. `test_change_password_fails_if_confirmation_does_not_match` — Konfirmasi kata sandi tidak cocok ditolak (422) [PASS]
15. `test_change_password_fails_if_new_password_same_as_current` — Kata sandi baru sama dengan yang lama ditolak (422) [PASS]
16. `test_authenticated_user_can_logout` — Logout berhasil (200) [PASS]

### B. Hasil Keseluruhan Test Suite
```
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AuthenticationTest (16 tests, 87 assertions)
   PASS  Tests\Feature\DatabaseDataIntegrityTest (8 tests, 107 assertions)
   PASS  Tests\Feature\DatabaseSchemaTest (11 tests, 88 assertions)
   PASS  Tests\Feature\ExampleTest (1 test, 1 assertion)

  Tests:    36 passed (283 assertions)
  Duration: 3.11s
```

### C. Pemeriksaan Format Kode (Laravel Pint)
```
./vendor/bin/pint --test
{"tool":"pint","result":"passed"}
Exit code: 0
```
Seluruh berkas PHP memenuhi standar PSR-12 dan konvensi penulisan Laravel Pint.

---

## 7. Panduan Menjalankan Backend & Integrasi React Frontend

### A. Menjalankan Backend Laravel
1. Pastikan MariaDB / MySQL pada XAMPP aktif di port 3306.
2. Jalankan perintah server lokal di direktori `backend/`:
   ```bash
   php artisan serve
   ```
   Backend akan berjalan di `http://localhost:8000`.

### B. Konfigurasi Axios pada Frontend React
Pada frontend React (Vite):
```typescript
import axios from 'axios';

export const apiClient = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true, // Wajib agar cookie sesi dikirim bolak-balik
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Contoh fungsi login
export async function login(email: string, password: string) {
  // 1. Ambil cookie CSRF terlebih dahulu
  await apiClient.get('/sanctum/csrf-cookie');

  // 2. Kirim kredensial login
  const response = await apiClient.post('/api/login', { email, password });
  return response.data;
}

// Contoh fungsi cek pengguna aktif (me)
export async function fetchCurrentUser() {
  const response = await apiClient.get('/api/me');
  return response.data;
}
```

### C. Alur Navigasi Frontend saat `must_change_password`
1. Setelah login berhasil, periksa nilai `user.must_change_password`.
2. Jika bernilai `true`, arahkan pengguna secara otomatis ke rute `/change-password`.
3. Interceptor response Axios dapat menangkap HTTP 403 dengan properti `must_change_password: true` dan membelokkan navigasi jika pengguna mencoba membuka dashboard sebelum kewajiban dipenuhi.

---

## 8. Daftar Berkas yang Dibuat / Diubah

1. **`backend/app/Http/Controllers/Api/AuthController.php`** — Controller autentikasi (login, logout, me, changePassword).
2. **`backend/app/Http/Requests/LoginRequest.php`** — Form request validasi login dengan rate limiting.
3. **`backend/app/Http/Requests/ChangePasswordRequest.php`** — Form request validasi perubahan kata sandi.
4. **`backend/app/Http/Resources/UserResource.php`** — Resource transformasi data profil pengguna yang aman.
5. **`backend/app/Http/Middleware/EnsurePasswordChanged.php`** — Middleware penegakan kewajiban ganti sandi pertama.
6. **`backend/bootstrap/app.php`** — Pendaftaran `$middleware->statefulApi()` dan alias `password.changed`.
7. **`backend/routes/api.php`** — Pendaftaran seluruh rute autentikasi.
8. **`backend/.env`** — Pembaruan `SANCTUM_STATEFUL_DOMAINS` mencakup domain localhost dan 127.0.0.1.
9. **`backend/tests/Feature/AuthenticationTest.php`** — Pengujian otomatis lengkap (16 pengujian).
10. **`docs/CATATAN_KEPUTUSAN.md`** — Penambahan Bagian 7 terkait arsitektur autentikasi Sanctum SPA.
11. **`docs/LAPORAN_FASE1_AUTENTIKASI.md`** — Dokumentasi komprehensif implementasi Fase 1.

---

## 9. Rekomendasi Fase Selanjutnya

Sesuai urutan pengerjaan PRD Bagian 20 (Fase 2):
1. **Fase 2: Manajemen Pengguna & Tim:**
   - Endpoint CRUD Pengguna: `GET /api/users`, `POST /api/users`, `GET /api/users/{id}`, `PUT /api/users/{id}` (khusus Admin).
   - Pengaturan keanggotaan tim dan penetapan ketua tim (`teams`, `team_members`).
   - Endpoint pengaturan sistem (`/api/settings`).
   - Penerapan Policy otorisasi (`UserPolicy`, `TeamPolicy`) berdasarkan peran pengguna (`admin`, `pimpinan`, `pegawai`).

