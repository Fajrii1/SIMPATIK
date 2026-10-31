# LAPORAN IMPLEMENTASI FASE 3: KEGIATAN, PENUGASAN, PERHITUNGAN BEBAN KERJA, DAN KALENDER
**Proyek:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang  
**Sumber Kebenaran:** `PRD.md` (Bagian 0, 5, 8.1–8.4, 8.6, 12.2 S3–S6, 13, 14, 16 Kriteria 1, 2, 4, 5, 6, 7, 8, 9, 11, 12)  
**Tanggal Selesai:** 31 Oktober 2026  
**Status:** Berhasil Diimplementasikan dan Lulus 100% Pengujian Otomatis  

---

## 1. Ringkasan Eksekutif

Tahap implementasi Fase 3 telah diselesaikan secara menyeluruh tanpa mengubah atau merusak skema database `jadwal_bps` dan data seeder yang telah ada.

Implementasi mencakup empat pilar fungsional utama:
1. **Manajemen Kegiatan Statistik (`ActivityController`)**: CRUD lengkap, filter dinamis (status, tim, tanggal, kata kunci), penandaan selesai (`complete`) dan buka kembali (`reopen`), serta pencatatan jejak audit.
2. **Manajemen Penugasan Pegawai (`AssignmentController`)**: Penugasan pegawai ke kegiatan, validasi periode terhadap kegiatan, validasi pegawai aktif, notifikasi otomatis dalam aplikasi, dan pencatatan jejak audit.
3. **Pusat Perhitungan Beban Kerja (`WorkloadService`)**: Implementasi tunggal dan terpusat rumus beban kerja, distribusi poin bulanan proporsional hari, penentuan indikator beban, deteksi peringatan jadwal menumpuk, serta simulasi pratinjau penugasan tanpa mutasi database (`POST /api/assignments/preview`).
4. **Kalender Kegiatan Lintas Tim (`CalendarController`)**: Penyajian data acara berstandar FullCalendar (React) dengan filter tim, pegawai, status, serta dukungan penayangan kegiatan lintas bulan secara berkesinambungan.

---

## 2. Spesifikasi Endpoint API yang Tersedia

Seluruh endpoint terproteksi oleh middleware autentikasi Sanctum SPA (`auth:sanctum`) dan penegakan kata sandi pertama (`password.changed`).

| Metode | URL Endpoint | Hak Akses | Deskripsi & Fungsi |
|---|---|---|---|
| `GET` | `/api/activities` | Semua Terautentikasi | Daftar kegiatan statistik dengan filter tim, status, tanggal, dan pencarian |
| `POST` | `/api/activities` | Admin, Ketua Tim (timnya) | Membuat kegiatan statistik baru |
| `GET` | `/api/activities/{id}` | Semua Terautentikasi | Detail satu kegiatan beserta penugasan dan tim penanggung jawab |
| `PUT/PATCH` | `/api/activities/{id}` | Admin, Ketua Tim (timnya) | Memperbarui kegiatan (memvalidasi tanggal terhadap penugasan) |
| `DELETE` | `/api/activities/{id}` | Admin, Ketua Tim (timnya) | Menghapus kegiatan secara logis (*soft delete*) |
| `PATCH` | `/api/activities/{id}/complete` | Admin, Ketua Tim (timnya) | Menandai kegiatan selesai (`selesai_manual = true`, `selesai_pada = now`) |
| `PATCH` | `/api/activities/{id}/reopen` | Admin, Ketua Tim (timnya) | Membuka kembali kegiatan (`selesai_manual = false`, `selesai_pada = null`) |
| `GET` | `/api/activities/{id}/assignments` | Semua Terautentikasi | Daftar seluruh penugasan pegawai pada suatu kegiatan |
| `POST` | `/api/activities/{id}/assignments` | Admin, Ketua Tim (timnya) | Menugaskan pegawai ke kegiatan (validasi periode & keaktifan) |
| `GET` | `/api/assignments/{id}` | Semua Terautentikasi | Detail satu penugasan pegawai |
| `PUT/PATCH` | `/api/assignments/{id}` | Admin, Ketua Tim (timnya) | Memperbarui rincian penugasan pegawai |
| `DELETE` | `/api/assignments/{id}` | Admin, Ketua Tim (timnya) | Menghapus penugasan secara logis (*soft delete*) |
| `POST` | `/api/assignments/preview` | Admin, Ketua Tim | Pratinjau dampak penugasan terhadap beban kerja **tanpa mengubah database** |
| `GET` | `/api/calendar` | Semua Terautentikasi | Kalender kegiatan lintas tim berformat FullCalendar React |

---

## 3. Rumus dan Arsitektur Perhitungan Beban Kerja (`WorkloadService`)

Mengikuti arahan PRD Bagian 0 aturan 5 dan Bagian 8.1, seluruh logika matematika beban kerja dipusatkan di `backend/app/Services/WorkloadService.php`.

### A. Rumus Poin Total & Distribusi Bulanan
1. **Poin Total Penugasan:**
   $$\text{Poin Total} = \text{volume} \times \text{bobot kegiatan}$$
   - Bawaan `bobot` adalah `1.00`.
   - Dihitung dengan pembulatan 2 desimal (`round(..., 2)`).
2. **Periode Efektif Penugasan:**
   - Memakai `assignments.mulai` dan `assignments.selesai`.
   - Apabila kosong (null), otomatis mewarisi tanggal kegiatan induk (`activities.mulai` dan `activities.selesai`).
   - Tanggal awal dan akhir dihitung secara inklusif:
     $$\text{Total Hari} = \text{Tanggal Selesai} - \text{Tanggal Mulai} + 1$$
3. **Distribusi Poin Bulanan Proporsional Hari:**
   Untuk setiap bulan $M$ yang dilalui rentang penugasan:
   $$\text{Poin Bulan } M = \text{Poin Total} \times \left( \frac{\text{Hari Penugasan di Bulan } M}{\text{Total Hari Penugasan}} \right)$$

### B. Verifikasi Contoh Uji Wajib PRD Bagian 8.1
- **Parameter Uji:**
  - `volume` = 60
  - `bobot` = 1.50
  - `periode` = 1 Oktober 2026 s.d. 15 November 2026
- **Hasil Perhitungan:**
  - $\text{Poin Total} = 60 \times 1.50 = 90.00$
  - $\text{Total Hari} = 31 \text{ hari (Oktober)} + 15 \text{ hari (November)} = 46 \text{ hari}$
  - $\text{Poin Oktober} = 90.00 \times \frac{31}{46} = 60.65217\dots \to \mathbf{60.65}$
  - $\text{Poin November} = 90.00 \times \frac{15}{46} = 29.34782\dots \to \mathbf{29.35}$
  - Total akumulasi: $60.65 + 29.35 = \mathbf{90.00}$ (100% presisi matematis).

### C. Klasifikasi Indikator Beban Kerja
Dihitung dari persentase terhadap `ambang_beban_bulanan` (bawaan 100 poin dari tabel `settings`):
- **Aman (`aman`)**: Persentase $< 80\%$
- **Hampir Penuh (`hampir_penuh`)**: Persentase $80\% \le \text{beban} < 100\%$
- **Melebihi (`melebihi`)**: Persentase $\ge 100\%$

### D. Peringatan Jadwal Menumpuk (PRD Bagian 8.2)
Dihasilkan oleh `getMonthlyWarnings(string $month)`:
1. **Peringatan Beban:** Beban kerja seorang pegawai mencapai atau melebihi ambang batas ($\text{beban} \ge \text{ambang}$).
2. **Penugasan Bersamaan:** Jumlah penugasan aktif seorang pegawai pada bulan tersebut mencapai atau melebihi `maks_penugasan_bersamaan` (bawaan 3).
3. **Kegiatan Besar Bersamaan (Tingkat Kantor):** Jumlah kegiatan bertanda `kegiatan_besar = true` yang berjalan pada bulan tersebut melebihi `maks_kegiatan_besar_bersamaan` (bawaan 3).

---

## 4. Pratinjau Dampak Penugasan (`POST /api/assignments/preview`)

Mendukung fitur form penugasan S5 (PRD Bagian 12.2 S5):
- Menghitung dampak sebelum dan sesudah rencana penugasan pada seluruh bulan yang terdampak.
- Menampilkan beban sebelum, beban sesudah, persentase sebelum/sesudah, indikator sebelum/sesudah, dan potensi peringatan baru.
- **Sifat Atomik Tanpa Efek Samping:** Pengujian otomatis memastikan jumlah entri pada tabel `assignments` tidak bertambah sama sekali setelah pemanggilan preview.

---

## 5. Kalender Kegiatan Lintas Tim (`GET /api/calendar`)

Mendukung tampilan kalender FullCalendar S3 (PRD Bagian 12.2 S3):
- Mengembalikan array `events` dengan struktur:
  - `id`: ID kegiatan
  - `title`: Nama kegiatan
  - `start`: Tanggal mulai (`YYYY-MM-DD`)
  - `end`: Tanggal selesai eksklusif untuk kalender seharian penuh (`YYYY-MM-DD` $+ 1$ hari)
  - `allDay`: `true`
  - `extendedProps`: metadata lengkap mencakup `team_id`, `team_nama`, `jenis`, `bobot`, `kegiatan_besar`, `status`, dan daftar pegawai yang ditugaskan.
- Kegiatan yang melintasi pergantian bulan/tahun tetap muncul pada seluruh filter rentang yang beririsan.

---

## 6. Jejak Audit dan Notifikasi Otomatis

1. **Jejak Audit (`AuditLog`):**
   - Setiap penambahan, pembaruan, penghapusan, penyelesaian, dan pembukaan kembali kegiatan atau penugasan dicatat di `audit_logs` dengan snapshot JSON data `sebelum` dan `sesudah`.
2. **Notifikasi Dalam Aplikasi (`Notification`):**
   - Saat pegawai ditugaskan: tipe `penugasan_baru`.
   - Saat penugasan diubah: tipe `penugasan_diubah`.
   - Saat penugasan dihapus: tipe `penugasan_dihapus`.
   - Saat tanggal kegiatan diperbarui: tipe `jadwal_berubah` dikirimkan ke seluruh pegawai yang terlibat pada kegiatan tersebut.

---

## 7. Hasil Pengujian Otomatis

Seluruh pengujian dijalankan pada lingkungan pengujian MariaDB lokal:

```
   PASS  Tests\Unit\ExampleTest (1 test)
   PASS  Tests\Unit\WorkloadServiceTest (3 tests, 13 assertions)
   PASS  Tests\Feature\ActivityManagementTest (8 tests, 153 assertions)
   PASS  Tests\Feature\AssignmentManagementTest (6 tests, 75 assertions)
   PASS  Tests\Feature\AuthenticationTest (16 tests, 87 assertions)
   PASS  Tests\Feature\CalendarTest (4 tests, 171 assertions)
   PASS  Tests\Feature\DatabaseDataIntegrityTest (8 tests, 107 assertions)
   PASS  Tests\Feature\DatabaseSchemaTest (11 tests, 88 assertions)
   PASS  Tests\Feature\ExampleTest (1 test, 1 assertion)
   PASS  Tests\Feature\SettingManagementTest (5 tests, 36 assertions)
   PASS  Tests\Feature\TeamManagementTest (9 tests, 91 assertions)
   PASS  Tests\Feature\UserManagementTest (10 tests, 193 assertions)

  Tests:    81 passed (1015 assertions)
  Duration: 4.99s
```

### Pemeriksaan Standar Kode (Laravel Pint)
```
./vendor/bin/pint --test
{"tool":"pint","result":"passed"}
Exit Code: 0
```
Seluruh berkas PHP memenuhi standar konvensi PSR-12 dan aturan Laravel Pint.

---

## 8. Berkas yang Dibuat & Diperbarui pada Fase 3

1. **`backend/app/Enums/JenisKegiatan.php`** — Enum konstanta jenis kegiatan statistik.
2. **`backend/app/Enums/SatuanPenugasan.php`** — Enum konstanta satuan volume penugasan.
3. **`backend/app/Services/WorkloadService.php`** — Service utama perhitungan beban kerja, proporsi hari, indikator, peringatan, dan pratinjau.
4. **`backend/app/Policies/ActivityPolicy.php`** — Kebijakan otorisasi kegiatan statistik.
5. **`backend/app/Policies/AssignmentPolicy.php`** — Kebijakan otorisasi penugasan pegawai.
6. **`backend/app/Http/Requests/CreateActivityRequest.php`** — Validasi form pembuatan kegiatan.
7. **`backend/app/Http/Requests/UpdateActivityRequest.php`** — Validasi pembaruan kegiatan dan tanggal.
8. **`backend/app/Http/Requests/CreateAssignmentRequest.php`** — Validasi pembuatan penugasan.
9. **`backend/app/Http/Requests/UpdateAssignmentRequest.php`** — Validasi pembaruan penugasan.
10. **`backend/app/Http/Requests/PreviewAssignmentRequest.php`** — Validasi input simulasi pratinjau beban.
11. **`backend/app/Http/Resources/ActivityResource.php`** — Transformasi JSON data kegiatan dan status dinamis.
12. **`backend/app/Http/Resources/AssignmentResource.php`** — Transformasi JSON data penugasan.
13. **`backend/app/Http/Controllers/Api/ActivityController.php`** — Controller manajemen kegiatan.
14. **`backend/app/Http/Controllers/Api/AssignmentController.php`** — Controller penugasan & pratinjau.
15. **`backend/app/Http/Controllers/Api/CalendarController.php`** — Controller data kalender FullCalendar.
16. **`backend/routes/api.php`** — Pendaftaran 14 route baru untuk kegiatan, penugasan, dan kalender.
17. **`backend/tests/Unit/WorkloadServiceTest.php`** — Unit test perhitungan beban kerja & rumus PRD 8.1.
18. **`backend/tests/Feature/ActivityManagementTest.php`** — Feature test otorisasi dan CRUD kegiatan.
19. **`backend/tests/Feature/AssignmentManagementTest.php`** — Feature test penugasan dan pratinjau tanpa mutasi DB.
20. **`backend/tests/Feature/CalendarTest.php`** — Feature test filter kalender lintas tim.
21. **`docs/CATATAN_KEPUTUSAN.md`** — Penambahan Bagian 9 tentang arsitektur Fase 3.
22. **`docs/LAPORAN_FASE3_KEGIATAN_PENUGASAN.md`** — Dokumen laporan komprehensif ini.

---

## 9. Rekomendasi Fase Selanjutnya

Sesuai urutan pengerjaan PRD Bagian 19 (Fase 4 & 5):
1. **Fase 4: Endpoint Monitoring Beban Kerja:**
   - `GET /api/workload?month=YYYY-MM&team_id=` (rekap beban bulanan seluruh pegawai).
   - `GET /api/workload/users/{id}?from&to` (rincian penugasan dan tren beban pegawai per bulan).
   - `GET /api/workload/warnings?month=YYYY-MM` (daftar peringatan jadwal menumpuk bulan terpilih).
2. **Fase 5: Kendala Mingguan dan Daftar Terbawa:**
   - CRUD kendala mingguan (`/api/issues`).
   - Endpoint kendala terbawa dari minggu lalu (`/api/issues/carried?week=YYYY-MM-DD`).
   - Perubahan status kendala (`terbuka`, `diproses`, `selesai`) dan penutupan kendala.

