# Laporan Remediasi Fase 4

## 1. Berkas yang Diubah atau Ditambah

### 1.1 Backend

- `backend/app/Http/Requests/WeeklyIssueRequest.php`:
  - Pesan validasi seluruhnya dalam bahasa Indonesia.
  - Aturan lintas isian: kegiatan (`activity_id`) harus merupakan kegiatan milik `team_id` yang dipilih; pelanggaran ditolak 422 dengan pesan `"Kegiatan yang dipilih bukan milik tim yang dipilih."`.
  - `pic_id` harus pengguna berstatus aktif yang tercatat sebagai anggota `team_id`; pelanggaran ditolak 422 dengan pesan `"PIC harus merupakan pengguna aktif yang menjadi anggota tim yang dipilih."`.
  - Tenggat tidak dipaksa berada sesudah `minggu_mulai` (lihat bagian 4.2).
- `backend/app/Http/Controllers/Api/WeeklyIssueController.php`:
  - `show()`: mengembalikan semua field kendala dan `history` dari audit log kendala, berisi waktu, aktor, aksi, status lama, status baru, dan catatan penutupan bila ada. Riwayat disusun hanya dari `audit_logs` (`objek = 'WeeklyIssue'`); tidak ada tabel atau kolom baru.
  - `update()`: kendala berstatus `selesai` terkunci — hanya admin atau ketua tim terkait yang boleh mengubah; lainnya ditolak 422 dengan pesan yang jelas. Notifikasi ke PIC baru bila ditunjuk atau diubah (PIC lama tidak menerima).
  - `updateStatus()`: bila kendala sudah `selesai`, hanya admin atau ketua tim yang boleh mengubah status (termasuk membuka kembali); PIC ditolak 422 dengan pesan yang jelas — PIC tidak dapat membuka kembali. Notifikasi perubahan status dikirim ke pelapor, PIC, dan ketua tim tanpa duplikat dan tanpa aktor, dengan tautan `/issues?id={id}`, status baru, dan catatan maksimal 120 karakter.
  - Notifikasi kendala baru dan saat PIC ditunjuk tetap ada, dengan tautan dan pesan dalam pola yang sama.
- `backend/tests/Feature/WeeklyIssueManagementTest.php`: sepuluh pengujian kontrak Fase 4 (4.1, 4.3, 4.4, 4.5, 4.6).

### 1.2 Frontend

- `frontend/src/pages/IssuesPage.tsx`:
  - Formulir tambah kendala kini mencakup Kegiatan Terkait (opsional, kegiatan satu tim), Penanggung Jawab/PIC (opsional, anggota aktif tim), dan Tindak Lanjut (opsional). Solusi tetap opsional.
  - Perubahan tim mengosongkan pilihan kegiatan dan PIC pada formulir tambah maupun ubah dan mengisinya ulang.
  - Tombol "Lihat" membuka modal detail baca saja untuk peran yang boleh melihat kendala: uraian, solusi, tindak lanjut, catatan penutupan, PIC, kegiatan, pelapor, tim, minggu, tenggat, status, waktu ditutup, dan riwayat status. Modal menampilkan keadaan memuat, keadaan error yang bersahabat bila kendala tidak ada atau di luar wewenang, atau konten lengkap.
  - Tautan notifikasi dipakai di halaman ini: bila ada parameter `id` pada URL, modal detail dibuka otomatis. Parameter yang tidak valid menampilkan pesan.
  - Tombol "Ubah" muncul untuk peran yang berhak mengubah isi (admin, ketua tim terkait, pelapor sebelum selesai; khusus selesai hanya admin/ketua). Modal ubah sama dengan formulir tambah.
  - Tombol "Hapus" muncul untuk admin atau ketua tim terkait, memakai dialog konfirmasi (`window.confirm`) sebelum `DELETE /api/issues/{id}`.
  - Kendala selesai dan pengguna bukan admin/ketua: kontrol status dan tombol simpan pada modal Kelola dinonaktifkan disertai penjelasan bahwa hanya admin atau ketua tim yang dapat membuka kembali.
- `frontend/src/types/index.ts`:
  - `WeeklyIssue.activity` diperbaiki menjadi `kegiatan` (sesuai respons backend), menambah `ditutup_pada`, `history`, dan tipe `WeeklyIssueHistory`.
- `docs/CATATAN_KEPUTUSAN.md`: bagian 13 mencatat keputusan Fase 4.

### 1.3 Di luar berkas di atas, tidak ada yang berubah. Fase 3 tidak disentuh (temuan `userDetail` 500 tetap apa adanya).

## 2. Keluaran Verifikasi Mentah

### 2.1 Pengujian terarah

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/WeeklyIssueManagementTest.php
```

Keluaran:

```text
DB_DATABASE=jadwal_bps_test

   PASS  Tests\Feature\WeeklyIssueManagementTest
  ✓ issue rejects activity from another team                                                                     1.92s
  ✓ issue rejects inactive or non member pic                                                                     0.07s
  ✓ issue detail returns complete fields and history                                                             0.08s
  ✓ issue detail authorization rules                                                                             0.07s
  ✓ issue edit and delete follow authorization rules                                                             0.06s
  ✓ closed issue is locked and pic cannot reopen                                                                 0.08s
  ✓ closed issue rejects ordinary reporter                                                                       0.05s
  ✓ status change notifies participants with capped catatan                                                      0.07s
  ✓ status change does not duplicate notification for same person                                                0.06s
  ✓ reassigning pic notifies the new pic                                                                         0.04s

  Tests:    10 passed (90 assertions)
  Duration: 2.61s
```

### 2.2 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
   WARN  Tests\Feature\WorkloadServiceContractTest
  ✓ monthly points are split proportionally across months                                                        0.02s
  … user detail endpoint matches workload service → GET /api/workload/users/{id} gagal 500 dengan pesan "Attemp… 0.07s

  Tests:    1 incomplete, 142 passed (1439 assertions)
  Duration: 10.22s
```

Baseline Fase 3: 132 lulus + 1 incomplete, 1349 assertion. Fase 4 menambah 10 pengujian lulus dan 90 assertion. Pengujian incomplete tetap karena defek `userDetail` yang belum diperbaiki.

### 2.3 Format backend

Perintah:

```powershell
vendor\bin\pint --test
```

Hasil:

```text
{"tool":"pint","result":"fail","files":[{"path":"app\\Http\\Controllers\\Api\\WeeklyIssueController.php","fixers":["unary_operator_spaces","not_operator_with_successor_space","ordered_imports"]},{"path":"app\\Http\\Requests\\WeeklyIssueRequest.php","fixers":["fully_qualified_strict_types","unary_operator_spaces","not_operator_with_successor_space","ordered_imports"]},{"path":"tests\\Feature\\WeeklyIssueManagementTest.php","fixers":["no_unused_imports"]}]}
```

Perbaikan otomatis dijalankan pada ketiga berkas (`vendor\bin\pint ...`), lalu pemeriksaan diulang:

```text
{"tool":"pint","result":"passed"}
```

Setelah format otomatis, pengujian Fase 4 dijalankan ulang dan lulus (10 tests, 90 assertions).

### 2.4 Lint frontend

Perintah:

```powershell
npx oxlint
```

Ringkasan:

```text
LINES=25
WARNINGS=25
ERRORS=0
```

Jumlah peringatan turun dari 33 menjadi 25 (beberapa impor ikon yang tidak dipakai ikut hilang ketika halaman ditulis ulang). Tidak ada error.

### 2.5 Build frontend

Perintah:

```powershell
npm run build
```

Keluaran:

```text
> frontend@0.0.0 build
> tsc -b && vite build

rendering chunks...
dist/index.html                   0.45 kB │ gzip:   0.29 kB
dist/assets/index-Dqull4S8.css   54.62 kB │ gzip:  10.16 kB
dist/assets/index-CdR1nhkZ.js   946.90 kB │ gzip: 261.63 kB
(!) Some chunks are larger than 500 kB after minification.
✓ built in 1.02s
```

Peringatan ukuran chunk seperti sebelumnya. `tsc -b` lulus.

### 2.6 Pemeriksaan larangan

```text
=====ALERT=====
=====EMOJI=====
```

Tidak ada hasil untuk `alert(` maupun emoji pada `frontend/src`.

### 2.7 Status berkas

```text
 M backend/app/Http/Controllers/Api/WeeklyIssueController.php
 M backend/app/Http/Requests/WeeklyIssueRequest.php
 M docs/CATATAN_KEPUTUSAN.md
 M frontend/src/pages/IssuesPage.tsx
 M frontend/src/types/index.ts
?? backend/tests/Feature/WeeklyIssueManagementTest.php
```

Sebelum laporan ini ditambahkan, satu-satunya berkas tak terlacak adalah pengujian Fase 4. Seluruh perubahan Fase 4 masih belum di-commit. Fase 2 dan 3 sudah ter-commit oleh pemilik (`d8048ec`).

### 2.8 Database

Seluruh pengujian memakai `DB_DATABASE=jadwal_bps_test`. `migrate:fresh`, `migrate:refresh`, `db:wipe`, maupun `db:seed` tidak dijalankan. Tidak ada perubahan skema.

## 3. Hal yang Tidak Dapat Diverifikasi

- Verifikasi visual pada lebar 360px belum dijalankan di peramban sungguhan. Modal memakai `max-h-[90vh]` dan `overflow-y-auto` serta grid satu kolom pada ponsel, tetapi tata letak belum diperiksa manual pada perangkat/peramban.
- Dialog konfirmasi penghapusan memakai `window.confirm` dan belum diuji interaksi nyata di peramban.
- Tampilan layar untuk kendala yang terkunci (kontrol dinonaktifkan dan catatan peringatan) belum diverifikasi secara visual.
- Toast/antrean notifikasi di header belum diperiksa mengarah ke `/issues?id={id}`; only link backend sudah berubah dan frontend membuka detail via parameter `id`.
- `PUT /api/issues/{id}` mengikuti aturan otorisasi controller saat ini (admin/ketua/pelapor, khusus selesai beralih admin/ketua). Pengujian memastikan respons 403/422, tetapi tidak menguji notifikasi untuk perubahan `activity_id` selain ke PIC baru.
- Pesan pada notifikasi status memakai label status ("Selesai", "Diproses", "Terbuka"), bukan nilai enum mentah; hal ini disengaja dan dicatat sementara tidak ada teks lain yang dijanjikan.

## 4. Temuan di Luar Lingkup yang Tidak Diubah

### 4.1 Defek lama `GET /api/workload/users/{id}` (dari Fase 3)

Endpoint detail beban kerja masih 500 karena membaca `->value` pada kolom string. Pengujian terkait tetap `incomplete` dan tidak diperbaiki karena berada di luar Fase 4.

### 4.2 Tenggat tidak dipaksa mengikuti minggu mulai

PRD 8.5 mengandalkan konsep jatuh tempo untuk kendala yang lewat tenggat dan tidak melarang tenggat sebelum `minggu_mulai`. Memaksa `tenggat >= minggu_mulai` akan menolak kendala yang dikenali terlambat sejak dicatat. Karena itu aturan tersebut sengaja tidak diterapkan; dicatat pada `docs/CATATAN_KEPUTUSAN.md` bagian 13.

### 4.3 `poin_beban` tidak pernah ada (dari Fase 2/3)

`Penugasan_aktif[].poin` pada dashboard selalu 0. Tidak berubah pada Fase 4 dan tetap dilaporkan sebagai temuan.

### 4.4 Tipe `WeeklyIssue.activity` diperbaiki menjadi `kegiatan`

Respons backend sejak awal menamai kunci `kegiatan`, sementara tipe frontend lama menulis `activity?`. Keduanya tidak pernah dipakai bersamaan; penelusuran menunjukkan tidak ada pemakaian yang rusak. Perbaikan tipe ini termasuk dalam cakupan kebutuhan detail kendala (tipe `history` dan `ditutup_pada` ikut didefinisikan).

### 4.5 Notifikasi konten kendala

Notifikasi hanya dikirim saat perubahan status dan penunjukan PIC, sesuai cakupan Fase 4. Penyuntingan isi kendala tidak memicu notifikasi — tidak diperluas.

### 4.6 Konflik antara PRD 8.4 dan kebutuhan form

PRD 8.4 menyebut periode penugasan harus di dalam periode kegiatan, tetapi untuk kendala tidak ada periode serupa. `activity_id` kendala hanya dibatasi oleh tim yang sama, bukan oleh rentang tanggal — sesuai kebutuhan fase ini.

### 4.7 Pengurangan peringatan lint

Jumlah peringatan oxlint turun dari 33 menjadi 25. Tidak ada peringatan baru; tidak ada aturan yang dilemahkan.

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps`. Seluruh query berikut tidak mengubah data.

### 5.1 Riwayat kendala dari audit log

Ganti nilai `a.objek_id = 123` dengan ID kendala yang diperiksa.

```sql
SELECT a.id,
       a.user_id,
       u.nama AS aktor,
       a.aksi,
       JSON_UNQUOTE(JSON_EXTRACT(a.sebelum, '$.status')) AS status_lama,
       JSON_UNQUOTE(JSON_EXTRACT(a.sesudah, '$.status')) AS status_baru,
       JSON_UNQUOTE(JSON_EXTRACT(a.sesudah, '$.catatan_penutupan')) AS catatan,
       a.created_at AS waktu
FROM audit_logs a
LEFT JOIN users u ON u.id = a.user_id
WHERE a.objek = 'WeeklyIssue'
  AND a.objek_id = 123
ORDER BY a.id;
```

Hasil yang diharapkan: entri `create` dengan `status_lama` kosong dan `status_baru` `terbuka`, diikuti entri `update_status` (dan `update`) sesuai mutasi. Kolom `catatan` terisi hanya pada penutupan.

### 5.2 Penerima notifikasi perubahan status untuk satu kendala

```sql
SELECT n.user_id,
       u.nama,
       COUNT(*) AS jumlah_notifikasi
FROM notifications n
JOIN users u ON u.id = n.user_id
WHERE n.tipe = 'kendala_status'
  AND n.tautan = '/issues?id=123'
GROUP BY n.user_id, u.nama;
```

Hasil yang diharapkan: tepat satu baris terhitung 1 untuk setiap penerima (pelapor, PIC, ketua tim), tanpa baris untuk aktor. Aktor tidak boleh muncul.

### 5.3 PIC yang bukan anggota tim atau tidak aktif

```sql
SELECT wi.id, wi.pic_id, wi.team_id, t.nama AS tim
FROM weekly_issues wi
JOIN teams t ON t.id = wi.team_id
WHERE wi.deleted_at IS NULL
  AND wi.pic_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM team_members tm
      WHERE tm.team_id = wi.team_id
        AND tm.user_id = wi.pic_id
  );
```

Hasil yang diharapkan: tidak ada baris untuk kendala yang dibuat setelah Fase 4. Kendala lama boleh saja memuat PIC yang sudah dikeluarkan dari tim; memeriksa `deleted_at IS NULL` saja cukup.

### 5.4 Kendala selesai yang masih berubah seusai ditutup

```sql
SELECT wi.id, wi.status, wi.ditutup_pada, wi.updated_at
FROM weekly_issues wi
WHERE wi.status = 'selesai'
  AND wi.deleted_at IS NULL
  AND wi.ditutup_pada IS NOT NULL
  AND wi.updated_at > wi.ditutup_pada;
```

Hasil yang diharapkan: seharusnya kosong, kecuali admin atau ketua tim membuka kembali lalu menutup kembali dengan `ditutup_pada` yang diperbarui. Bila ada baris, periksa audit log dengan query 5.1.

### 5.5 Pesan notifikasi yang melebihi batas catatan 120 karakter

```sql
SELECT id, user_id, pesan
FROM notifications
WHERE tipe = 'kendala_status'
  AND CHAR_LENGTH(SUBSTRING_INDEX(pesan, 'Catatan: ', -1)) > 120;
```

Hasil yang diharapkan: tidak ada baris. `SUBSTRING_INDEX` mengambil bagian setelah "Catatan: " bila ada.
