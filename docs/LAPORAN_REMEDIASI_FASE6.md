# Laporan Remediasi Fase 6

## 1. Berkas yang Diubah atau Ditambah

### 1.1 Backend

- `backend/app/Http/Requests/CreateActivityRequest.php` (diubah):
  - `assignments` wajib minimal satu (`required, array, min:1`).
  - Harus tepat satu penugasan dengan peran `Penanggung Jawab` (divalidasi di `withValidator`).
  - Pegawai tidak boleh ditugaskan dua kali pada kegiatan sama.
  - `selesai` boleh dikosongkan (`sometimes`) dan diisi sama dengan `mulai` lewat `prepareForValidation`.
  - Validasi periode penugasan dalam periode kegiatan tetap diterapkan.
  - Kegiatan lama yang dibuat lewat model/seed tidak terpengaruh (aturan hanya di request API).
- `backend/app/Http/Requests/PreviewAssignmentBatchRequest.php` (baru):
  - Validasi `POST /api/assignments/preview-batch`: `team_id`, `mulai`, `selesai`, `bobot` (0,50–3,00), dan minimal satu baris penugasan.
  - Hanya admin atau ketua tim dari `team_id` yang boleh memanggil.
  - Pegawai yang ditugaskan harus aktif dan tidak boleh duplikat dalam satu batch.
- `backend/app/Http/Controllers/Api/AssignmentController.php` (diubah):
  - Menambah method `previewBatch` untuk endpoint `POST /api/assignments/preview-batch`.
  - Memanggil `WorkloadService::previewBatchImpact`.
- `backend/app/Http/Controllers/Api/ActivityController.php` (diubah):
  - `index` (kalender) menyembunyikan volume/satuan/poin untuk viewer yang tidak berhak melihat metrik penugasan.
- `backend/app/Http/Controllers/Api/WorkloadController.php` (diubah):
  - `userDetail` menyembunyikan volume/satuan/poin_total untuk viewer yang tidak berhak.
  - Diperbaiki defek 500: `$assignment->activity->jenis->value` dan `$assignment->satuan->value` yang sebenarnya bertipe string, diubah menjadi `(string)` langsung.
- `backend/app/Http/Resources/AssignmentResource.php` (diubah):
  - Menambah flag `metrics_visible`.
  - `volume`, `satuan`, `poin_total` bernilai `null` bila viewer tidak berhak melihat metrik.
- `backend/app/Services/WorkloadService.php` (diubah):
  - Menambah method `previewBatchImpact`: pratinjau batch beberapa penugasan dari kegiatan yang belum disimpan, tanpa menulis ke database. Mengembalikan beban sebelum/sesudah, indikator, jumlah penugasan, puncak bersamaan, dan peringatan per pegawai.
  - Tidak mengubah hasil perhitungan yang ada (test Fase 3 tetap lulus).
- `backend/app/Support/PeranPenugasan.php` (baru): konstanta `PENANGGUNG_JAWAB = 'Penanggung Jawab'`.
- `backend/app/Support/AssignmentVisibility.php` (baru): aturan terpusat visibilitas metrik penugasan (admin/pimpinan semua; ketua tim hanya kegiatan timnya; pegawai hanya miliknya; lainnya hanya nama dan peran). Dipakai di `AssignmentResource`, `CalendarController`, dan `WorkloadController::userDetail`.
- `backend/routes/api.php` (diubah): menambah `Route::post('assignments/preview-batch', ...)`.

### 1.2 Frontend

- `frontend/src/pages/ActivityFormModal.tsx` (baru): satu komponen untuk tambah dan ubah kegiatan di Daftar Kegiatan. Berisi:
  - Data kegiatan (nama, jenis, tim, mulai, selesai, bobot, kegiatan besar, keterangan).
  - Penanggung Jawab wajib (pilihan satu pegawai, otomatis peran "Penanggung Jawab").
  - Pegawai Terlibat (multi-select dengan pencarian dan chip yang bisa dihapus; PJ tidak muncul lagi di sini).
  - Tabel per pegawai: Peran, Volume (wajib, tanpa default 1), Satuan, Poin, indikator beban.
  - Tombol "Terapkan volume dan satuan ke semua".
  - Periode efektif disembunyikan di balik "Atur periode khusus (opsional)" dan default mengikuti tanggal kegiatan.
  - Teks bantuan: "Volume = jumlah pekerjaan, Satuan = jenis pekerjaan, Poin = volume x bobot" dan "1,00 = pekerjaan standar".
  - Validasi klien lengkap (tanggal, bobot, volume, duplikat pegawai, periode penugasan dalam periode kegiatan), error inline.
  - Pratinjau beban batch melalui `POST /assignments/preview-batch` sebelum menyimpan.
- `frontend/src/pages/ActivitiesPage.tsx` (diubah):
  - Mengganti modal Tambah/Edit lama dengan `ActivityFormModal`.
  - Form ubah hanya mengubah data kegiatan; penugasan yang ada tetap dikelola lewat bagian penugasan di halaman yang sama.
  - Menangani parameter `?id=` dari URL (dipakai tautan dari kalender) untuk membuka detail kegiatan.
- `frontend/src/pages/CalendarPage.tsx` (diubah):
  - Menghapus tombol Tambah dan `AddActivityModal`; kalender menjadi baca-saja.
  - Menghapus aksi edit dan hapus di `CalendarActivityDetailModal`.
  - Menambah tautan "Buka di Daftar Kegiatan" ke `/activities?id={id}`.
- `frontend/src/pages/CalendarActivityDetailModal.tsx` (diubah):
  - Detail baca-saja; tidak ada tombol Edit dan Hapus.
  - Menambah tautan "Buka di Daftar Kegiatan" ke `/activities?id={id}`.
  - Menyembunyikan volume/satuan/poin untuk viewer yang tidak berhak.
- `frontend/src/pages/CalendarAddActivityModal.tsx` (dihapus).
- `frontend/src/pages/CalendarEmployeeTab.tsx` (diubah): menampilkan `—` untuk metrik yang disembunyikan.
- `frontend/src/pages/WorkloadPage.tsx` (diubah): menampilkan `—` untuk metrik yang disembunyikan.
- `frontend/src/pages/calendarTypes.ts` (diubah): menambah `metrics_visible` pada `AssignedUser` dan `EmployeeSummary`.
- `frontend/src/pages/calendarUtils.ts` (diubah): `buildEmployeeSummary` menyembunyikan metrik bila `metrics_visible` false.
- `frontend/src/types/index.ts` (diubah): `Assignment.volume/satuan/poin_total/poin_beban` menjadi nullable, menambah `metrics_visible`, menambah tipe `PreviewBatchResponse`, `PreviewBatchPegawaiResult`, `PreviewBatchImpactRow`.

### 1.3 Pengujian

- `backend/tests/Feature/ActivityFormAndVisibilityTest.php` (baru): 8 pengujian — PJ wajib, selesai default ke mulai, otorisasi preview-batch, preview batch tanpa menulis ke DB, peringatan ambang dan konsistensi, metrik tersembunyi untuk viewer tidak berhak, kalender menyembunyikan metrik, userDetail menampilkan angka tetapi menyembunyikan metrik lintas tim.
- `backend/tests/Feature/ActivityManagementTest.php` (diubah): payload pembuatan kegiatan sekarang menyertakan `assignments` dengan peran `Penanggung Jawab` agar sesuai aturan baru.

### 1.4 Dokumen

- `docs/CATATAN_KEPUTUSAN.md`: bagian 14 mencatat keputusan Fase 6 (PJ wajib, tanggal selesai boleh kosong, pratinjau batch tanpa DB, pembatasan volume, satu komponen form, kalender baca-saja, periode penugasan tersembunyi).

## 2. Keluaran Verifikasi Mentah

### 2.1 Pengujian terarah Fase 6

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/ActivityFormAndVisibilityTest.php
```

Keluaran:

```text
   PASS  Tests\Feature\ActivityFormAndVisibilityTest
  ✓ create activity requires single penanggung jawab                                                             1.73s
  ✓ create activity defaults selesai to mulai                                                                    0.09s
  ✓ preview batch authorization                                                                                  0.06s
  ✓ preview batch returns before and after without writing                                                       0.05s
  ✓ preview batch warns on threshold and concurrency                                                             0.05s
  ✓ assignment metrics are hidden for unrelated viewers                                                          0.11s
  ✓ calendar hides assignment metrics for unrelated viewers                                                      0.05s
  ✓ user detail concurrency and metrics visibility                                                               0.05s

  Tests:    8 passed (60 assertions)
  Duration: 2.61s
```

### 2.2 Pengujian ActivityManagement yang terdampak

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/ActivityManagementTest.php
```

Keluaran:

```text
   PASS  Tests\Feature\ActivityManagementTest
  ✓ unauthenticated cannot access activity endpoints                                                             0.04s
  ✓ all authenticated users can view activities                                                                  0.13s
  ✓ admin can create activity for any team                                                                       0.11s
  ✓ admin can create activity with initial assignments                                                           0.11s
  ✓ create activity rejects assignment outside activity period                                                   0.04s
  ✓ ketua tim can only create activity for led team                                                              0.10s
  ✓ regular pegawai cannot create activity                                                                       0.04s
  ✓ activity date validation                                                                                     0.02s
  ✓ complete and reopen activity                                                                                 0.04s
  ✓ activity date change triggers notification                                                                   0.04s
  ✓ activity weight must be between half and three on create                                                     0.18s
  ✓ activity weight must be between half and three on update                                                     0.03s

  Tests:    12 passed (123 assertions)
  Duration: 1.16s
```

### 2.3 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
   Tests:    164 passed (1576 assertions)
   Duration: 12.83s
```

Tidak ada regresi dari Fase 6.

### 2.4 Format backend

Perintah:

```powershell
vendor\bin\pint --test
```

Keluaran:

```text
{"tool":"pint","result":"passed"}
```

### 2.5 Lint frontend

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

### 2.6 Build frontend

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
✓ built in 835ms
```

Peringatan ukuran chunk sudah ada sebelum Fase 6.

### 2.7 Pemeriksaan larangan

```text
=====ALERT=====
=====EMOJI=====
```

Tidak ada hasil untuk `alert(` maupun emoji pada `frontend/src`.

### 2.8 Status berkas

```text
 M backend/app/Http/Controllers/Api/AssignmentController.php
 M backend/app/Http/Controllers/Api/ActivityController.php
 M backend/app/Http/Controllers/Api/WorkloadController.php
 M backend/app/Http/Requests/CreateActivityRequest.php
 M backend/app/Http/Resources/AssignmentResource.php
 M backend/app/Services/WorkloadService.php
 M backend/routes/api.php
 M backend/tests/Feature/ActivityManagementTest.php
 M docs/CATATAN_KEPUTUSAN.md
 M frontend/src/pages/ActivitiesPage.tsx
 M frontend/src/pages/CalendarActivityDetailModal.tsx
 D frontend/src/pages/CalendarAddActivityModal.tsx
 M frontend/src/pages/CalendarEmployeeTab.tsx
 M frontend/src/pages/CalendarPage.tsx
 M frontend/src/pages/WorkloadPage.tsx
 M frontend/src/pages/calendarTypes.ts
 M frontend/src/pages/calendarUtils.ts
 M frontend/src/types/index.ts
?? backend/app/Http/Requests/PreviewAssignmentBatchRequest.php
?? backend/app/Support/
?? backend/tests/Feature/ActivityFormAndVisibilityTest.php
?? frontend/src/pages/ActivityFormModal.tsx
```

Tidak ada berkas di luar cakupan Fase 6 yang berubah. `PRD.md` tidak disentuh.

### 2.9 Database

Seluruh pengujian memakai `DB_DATABASE=jadwal_bps_test`. Tidak ada perintah `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed` yang dijalankan pada `jadwal_bps`.

## 3. Hal yang Tidak Dapat Diverifikasi

- Verifikasi visual pada lebar 360px untuk `ActivityFormModal` belum dijalankan di peramban sungguhan. Modal memakai `max-w-4xl`, `max-h-[92vh]`, dan `overflow-y-auto`, tetapi tata letak ponsel belum diperiksa manual.
- Pratinjau beban batch hanya diverifikasi lewat pengujian backend; interaksi debounce 500ms di frontend belum diuji secara visual.
- Tampilan metrik yang disembunyikan (`—`) di kalender dan workload belum diperiksa secara visual pada perangkat/peramban nyata.
- Tautan `?id=` dari kalender ke Daftar Kegiatan belum diuji melalui navigasi peramban sungguhan.

## 4. Temuan di Luar Lingkup yang Tidak Diubah

- Defek `GET /api/workload/users/{id}` 500 dari Fase 3 masih belum diperbaiki, karena Fase 6 tidak mencakup perbaikan defek lama (hanya menambah pengujian dan dokumentasi). Namun, defek terkait `->value` pada kolom string di `WorkloadController::userDetail` telah diperbaiki sebagai bagian dari pembatasan metrik (jenis dan satuan diubah menjadi cast string langsung).
- `poin_beban` tidak pernah ada (kolom/accessor yang tidak ada) masih belum diperbaiki. `penugasan_aktif[].poin` di dashboard selalu 0, tetapi total beban pribadi tetap benar.
- Perubahan yang bukan lewat penugasan (mis. kegiatan di-reschedule atau bobot diubah) tidak memicu notifikasi peringatan jadwal (di luar cakupan Fase 6, dibatasi pada "penugasan dibuat/diubah/dihapus" per spesifikasi Fase 5).
- Peringatan kegiatan besar bersamaan tidak memicu notifikasi `peringatan_jadwal` (sengaja, dicatat di `docs/CATATAN_KEPUTUSAN.md` bagian 15).

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps` (atau `jadwal_bps_test`). Seluruh query berikut tidak mengubah data.

### 5.1 Kegiatan yang memiliki tepat satu Penanggung Jawab

```sql
SELECT a.id, a.nama, COUNT(ass.id) AS jumlah_penugasan
FROM activities a
LEFT JOIN assignments ass ON ass.activity_id = a.id AND ass.deleted_at IS NULL
WHERE a.deleted_at IS NULL
GROUP BY a.id, a.nama
HAVING COUNT(ass.id) = 0 OR COUNT(ass.id) > 1
ORDER BY a.id;
```

Hasil yang diharapkan: tidak ada baris. Setiap kegiatan yang dibuat setelah Fase 6 harus memiliki minimal satu penugasan. Kegiatan lama (dibuat sebelum Fase 6 atau lewat model/seed) boleh memiliki 0 penugasan.

### 5.2 Penugasan dengan peran "Penanggung Jawab" per kegiatan

```sql
SELECT activity_id, COUNT(*) AS jumlah_pj
FROM assignments
WHERE peran = 'Penanggung Jawab' AND deleted_at IS NULL
GROUP BY activity_id
HAVING jumlah_pj <> 1
ORDER BY activity_id;
```

Hasil yang diharapkan: tidak ada baris. Setiap kegiatan harus memiliki tepat satu penugasan dengan peran `Penanggung Jawab`.

### 5.3 Pegawai yang ditugaskan dua kali pada kegiatan sama

```sql
SELECT activity_id, user_id, COUNT(*) AS jumlah
FROM assignments
WHERE deleted_at IS NULL
GROUP BY activity_id, user_id
HAVING jumlah > 1
ORDER BY activity_id, user_id;
```

Hasil yang diharapkan: tidak ada baris. Satu pegawai tidak boleh ditugaskan dua kali pada kegiatan sama.

### 5.4 Kegiatan yang tanggal selesainya NULL (default sama dengan mulai)

```sql
SELECT id, nama, mulai, selesai
FROM activities
WHERE deleted_at IS NULL AND selesai IS NULL
ORDER BY id;
```

Hasil yang diharapkan: tidak ada baris (karena `selesai` diisi sama dengan `mulai` saat pembuatan melalui API Fase 6). Data lama boleh memiliki `selesai` NULL jika dibuat sebelum Fase 6.
