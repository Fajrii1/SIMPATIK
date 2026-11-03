# Laporan Remediasi Fase 5

## 1. Berkas yang Diubah atau Ditambah

### 5.1 Perintah artisan `notify:due-issues`

- `backend/app/Console/Commands/NotifyDueIssuesCommand.php` (baru):
  - Signature: `notify:due-issues`. Untuk setiap kendala yang **belum selesai** dan `tenggat`-nya **sudah lewat** (tanggal, bukan hari ini), mengirim notifikasi `kendala_jatuh_tempo` ke PIC (bila ada) dan ketua tim pemilik tim.
  - Maksimal satu notifikasi per kendala per hari per penerima (dedupe berdasarkan `tipe` + `tautan` + tanggal `created_at`). Kendala selesai, kendala tanpa `tenggat`, dan kendala dengan tenggat ke depan diabaikan.
  - Pesan berisi kendala dan tenggat (format `d F Y`), tautan `/issues?id={id}`, tanpa menyimpan rahasia apa pun.
- `backend/routes/console.php`: mendaftarkan `Schedule::command('notify:due-issues')->dailyAt('07:00')->timezone('Asia/Jakarta')`. `php artisan schedule:list` menampilkan `0 0 * * *` karena mesin server memakai UTC (07:00 WIB = 00:00 UTC); waktu efektif adalah 07:00 Asia/Jakarta.
- `README.md`: menambah bagian **Perintah Terjadwal (Scheduler)** — cara menjalankan `notify:due-issues` secara manual dan cara memakai Windows Task Scheduler memanggil `php artisan schedule:run` tiap menit (dengan contoh `simpatik-scheduler.bat` dan langkah pembuatan task).

### 5.2 Notifikasi peringatan jadwal menumpuk otomatis

- `backend/app/Services/WorkloadReminderService.php` (baru):
  - `collectWarnings`: menghitung peringatan **per pegawai** (`jenis = beban` dan `penugasan_bersamaan`; peringatan tingkat kantor `kegiatan_besar_bersamaan` sengaja tidak diikutkan) pada bulan-bulan yang disasar, lewat `WorkloadService::getMonthlyWarnings`.
  - `notifyNewWarnings`: membandingkan peringatan sebelum dan sesudah perubahan; setiap peringatan **baru** memicu notifikasi `peringatan_jadwal` ke ketua tim dari **setiap** tim tempat pegawai itu menjadi anggota.
  - Dedupe: notifikasi tidak dibuat bila sudah ada notifikasi `peringatan_jadwal` **yang belum dibaca** untuk kombinasi pegawai + bulan + jenis yang sama (dicocokkan lewat `tautan` `/workload?month={bulan}&pegawai_id={id}&jenis={jenis}`).
- `backend/app/Http/Controllers/Api/AssignmentController.php`: hook sebelum/sesudah untuk `storeForActivity`, `update`, dan `destroy`. Bulan yang dipantau = gabungan bulan periode penugasan sebelum dan sesudah perubahan; pegawai yang dipantau = user_id lama dan baru.
- `backend/app/Http/Controllers/Api/ActivityController.php`: `store` memanggil hook yang sama, sehingga **kegiatan baru dengan penugasan** juga dinilai. Peringatan dikirim setelah semua penugasan dibuat (masih di dalam transaksi).
- Perilaku sesuai spesifikasi: sebelum/sesudah dihitung lewat `WorkloadService`, hanya peringatan baru yang dikirim, dan tidak ada duplikasi bila notifikasi sejenis belum dibaca.
- Catatan ruang lingkup: perubahan yang bukan lewat penugasan (mis. kegiatan di-reschedule atau `bobot` diubah di form kegiatan pada Fase 6) belum memicu perhitungan ulang. Itu berada di luar cakupan Fase 5 (dibatasi pada "penugasan dibuat/diubah/dihapus").

### 5.3 Ekspor kendala

- `backend/app/Http/Controllers/Api/ReportController.php`: menambah `issuesReport`:
  - `GET /api/reports/issues?format=pdf|csv|xlsx|excel&week_from=YYYY-MM-DD&week_to=YYYY-MM-DD&team_id=X&status=terbuka|diproses|selesai`.
  - Otorisasi sama dengan laporan lain: admin & pimpinan boleh seluruh tim; ketua tim hanya untuk tim yang ia pimpin (lainnya 403); pegawai biasa 403.
  - Filter rentang minggu menormalkan tanggal ke hari Senin awal minggu; filter `status` divalidasi (selain tiga nilai status → 422).
  - CSV (dengan BOM), XLSX (via `SimpleXlsxWriter`), dan PDF (via DomPDF + view baru `resources/views/reports/issues_pdf.blade.php`). Nama berkas `Laporan_Kendala[_dari_sampai]`.
- `backend/routes/api.php`: menambah `Route::get('reports/issues', [ReportController::class, 'issuesReport']);`.
- `backend/resources/views/reports/issues_pdf.blade.php` (baru): tabel kendala bergaya sama dengan laporan PDF lain (header BPS Kota Palembang, kolom No/Minggu/Tim/Kegiatan/Kendala/PIC/Tenggat/Status, footer tanggal cetak).
- `frontend/src/pages/ReportsPage.tsx`: menambah kartu **Ekspor Kendala Mingguan** (filter Minggu Mulai dari–sampai, Tim, Status; tombol Unduh XLSX / CSV / Cetak PDF). Menghapus impor `Loader2` yang tidak dipakai.

### 5.4 Pengujian

- `backend/tests/Feature/ScheduledIssuesAndExportTest.php` (baru): 13 pengujian — kendala terlambat (PIC + ketua, tanpa PIC, status selesai, tanpa tenggat, masa depan, dedupe hari, PIC = ketua), dan jalur peringatan 2034 (beban baru, dedupe, penugasan bersamaan, notifikasi ganda dari tim ganda, dedupe notifikasi belum dibaca, pembuatan kegiatan dengan banyak penugasan, perubahan volume menaikkan beban → ketua satu kali, penghapusan tidak membuat peringatan baru), serta otorisasi ekspor (403/200 per peran), filter rentang minggu/tim/status, dan format CSV/XLSX/PDF.

## 2. Keluaran Verifikasi Mentah

### 2.1 Pengujian terarah

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/ScheduledIssuesAndExportTest.php
```

Keluaran:

```text
DB_DATABASE=jadwal_bps_test

   PASS  Tests\Feature\ScheduledIssuesAndExportTest
  ✓ due issues command is registered                                                                             1.49s
  ✓ due issues command notifies pic and ketua once per day                                                       0.09s
  ✓ due issues command does not duplicate when pic is ketua                                                      0.04s
  ✓ assignment creation notifies ketua when new beban warning appears                                            0.22s
  ✓ concurrency warning notifies ketua and dedupes                                                               0.20s
  ✓ existing unread notification suppresses duplicate notification                                               0.09s
  ✓ both ketua are notified when pegawai belongs to two teams                                                    0.10s
  ✓ activity creation with assignments notifies each teams ketua                                                 0.11s
  ✓ assignment update that pushes over threshold notifies ketua once                                             0.19s
  ✓ assignment deletion does not create new notification                                                         0.17s
  ✓ export issues enforces role based authorization                                                              0.06s
  ✓ export issues filters week team and status                                                                   0.06s
  ✓ export issues supports all formats                                                                           0.17s

  Tests:    13 passed (58 assertions)
  Duration: 3.10s
```

### 2.2 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
   WARN  Tests\Feature\WorkloadServiceContractTest
  ✓ monthly points are split proportionally across months                                                        0.06s
  … user detail endpoint matches workload service → GET /api/workload/users/{id} gagal 500 dengan pesan "Attemp… 0.07s

  Tests:    1 incomplete, 155 passed (1497 assertions)
  Duration: 12.07s
```

Baseline sebelum Fase 5: 142 lulus + 1 incomplete, 1439 assertion. Fase 5 menambah 13 pengujian lulus dan 58 assertion.

### 2.3 Format backend

Perintah:

```powershell
vendor\bin\pint --test
```

Hasil pertama:

```text
{"tool":"pint","result":"fail","files":[{"path":"app\\Console\\Commands\\NotifyDueIssuesCommand.php","fixers":["no_unused_imports"]},{"path":"app\\Http\\Controllers\\Api\\ActivityController.php","fixers":["class_attributes_separation","braces_position","no_unused_imports","single_line_empty_body"]}]}
```

Perbaikan otomatis dijalankan hanya pada dua berkas Fase 5 (`vendor\bin\pint app\Console\Commands\NotifyDueIssuesCommand.php app\Http\Controllers\Api\ActivityController.php`), lalu pemeriksaan diulang:

```text
{"tool":"pint","result":"passed"}
```

Pengujian Fase 5 dijalankan ulang setelah perbaikan dan tetap lulus (13 tests, 58 assertions).

### 2.4 Lint frontend

Perintah:

```powershell
npx oxlint
```

Ringkasan akhir:

```text
LINES=24
WARNINGS=24
ERRORS=0
```

Jumlah peringatan turun dari 25 menjadi 24 (impor `Loader2` di ReportsPage dihapus). Baseline Fase 1 adalah 33. Tidak ada error.

### 2.5 Build frontend

Perintah:

```powershell
npm run build
```

Keluaran:

```text
> frontend@0.0.0 build
> tsc -b && vite build

dist/index.html                   0.45 kB │ gzip:   0.29 kB
dist/assets/index-BkdRFe-X.css   54.66 kB │ gzip:  10.17 kB
dist/assets/index-D2-QcxUv.js   950.89 kB │ gzip: 262.15 kB
(!) Some chunks are larger than 500 kB after minification.
✓ built in 642ms
```

`tsc -b` lulus. Peringatan ukuran chunk seperti sebelumnya.

### 2.6 Pemeriksaan larangan

```text
=====ALERT=====
=====EMOJI=====
```

Tidak ada hasil untuk `alert(` maupun emoji pada `frontend/src`, dan tidak ada `alert(` pada `backend/app`.

### 2.7 Scheduler terdaftar

Perintah:

```powershell
php artisan schedule:list
```

Keluaran:

```text
  0 0 * * *  php artisan notify:due-issues ............................................. Next Due: 9 jam dari sekarang
```

Ekspresi cron ditampilkan dalam UTC; 07:00 Asia/Jakarta = 00:00 UTC, sehingga waktu efektif benar. Laravel tidak menjalankan scheduler sendiri; `schedule:run` harus dipanggil tiap menit oleh Task Scheduler (lihat README).

### 2.8 Status berkas

```text
 M README.md
 M backend/app/Http/Controllers/Api/ActivityController.php
 M backend/app/Http/Controllers/Api/AssignmentController.php
 M backend/app/Http/Controllers/Api/ReportController.php
 M backend/routes/api.php
 M backend/routes/console.php
 M frontend/src/pages/ReportsPage.tsx
?? backend/app/Console/
?? backend/app/Services/WorkloadReminderService.php
?? backend/resources/views/reports/issues_pdf.blade.php
?? backend/tests/Feature/ScheduledIssuesAndExportTest.php
?? docs/LAPORAN_REMEDIASI_FASE5.md
```

Fase 4 sudah ter-commit oleh pemilik (`da50c2b`), Fase 3 juga (`17b79a2`). Semua perubahan Fase 5 masih belum di-commit. `git diff --stat`: 7 files changed, 404 insertions(+), 6 deletions(-), plus berkas baru.

### 2.9 Database

Seluruh pengujian memakai `DB_DATABASE=jadwal_bps_test`. Tidak ada perintah `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, maupun `notify:due-issues` yang dijalankan terhadap `jadwal_bps`.

## 3. Hal yang Tidak Dapat Diverifikasi

- Pelaksanaan `schedule:run` oleh Windows Task Scheduler belum diuji; yang diverifikasi hanya bahwa perintah terdaftar dan bisa dipanggil manual di database uji.
- PDF hasil ekspor belum dibuka untuk diperiksa visual; yang diverifikasi hanya respons `content-type: application/pdf` dan pembuatan berkas oleh DomPDF.
- Ekspor kendala pada data seeder demo 2026 belum diuji terhadap PDF besar (ratusan baris).
- Notifikasi `peringatan_jadwal` hanya diperiksa lewat database pengujian, bukan dari alur peramban (badge unread dan daftar notifikasi belum diperiksa visual pada Fase 5).
- Trigger peringatan untuk perubahan selain penugasan (mis. kegiatan di-reschedule atau `bobot` diubah) belum diimplementasi maupun diuji; lihat bagian 4.1.
- Tanggal pada notifikasi jatuh tempo memakai format `d F Y` versi lokal Inggris ("20 January 2026") sesuai locale bawaan `Carbon`; locale Indonesia belum dipaksa karena bergantung konfigurasi app.

## 4. Temuan di Luar Lingkup yang Tidak Diubah

### 4.1 Ruang lingkup 5.2 dibatasi pada penugasan dan pembuatan kegiatan

Peringatan baru hanya dihitung saat **penugasan dibuat/diubah/dihapus** dan saat **kegiatan baru dengan penugasan dibuat**. Perubahan tanggal atau `bobot` kegiatan existing tidak memicu perhitungan ulang (belum diminta pada spesifikasi Fase 5). Bila diinginkan, jalur `ActivityController@update` dapat diberi hook serupa pada fase berikutnya.

### 4.2 Kegiatan besar bersamaan tidak memicu notifikasi peringatan_jadwal

Peringatan tingkat kantor (`kegiatan_besar_bersamaan`) sengaja tidak dikirim karena tidak ada pegawai sebagai subjek notifikasi dan tidak disebut penerima pada spesifikasi. Bila pimpinan/admin ingin diberi tahu, itu perlu keputusan terpisah.

### 4.3 Aktor juga menerima notifikasi bila ia adalah ketua dari tim pegawai terkait

Spesifikasi 5.2 tidak mengecualikan aktor. Yang dibuat justru memberitahu ketua dari setiap tim tempat pegawai menjadi anggota, termasuk jika pembuat perubahan adalah ketua itu sendiri. Pengecualian aktor hanya berlaku pada notifikasi perubahan status di Fase 4 (bagian 4.6), bukan di sini.

### 4.4 Dedupe peringatan_jadwal hanya pada notifikasi yang belum dibaca

Sesuai spesifikasi ("dedupe bila notifikasi serupa (pegawai, bulan, jenis) yang belum dibaca sudah ada"), notifikasi yang sudah dibaca tidak mencegah pengiriman ulang ketika kondisi peringatan tetap ada setelah perubahan berikutnya. Notifikasi `kendala_jatuh_tempo` justru dibatasi sekali per hari walaupun sudah dibaca, sesuai "maksimal satu kali per kendala per hari".

### 4.5 Ekspor kendala menormalkan rentang minggu ke hari Senin

`week_from`/`week_to` dipaksa ke awal minggu (Senin). Input Rabu tetap memetakan ke minggu berjalan. PDF mencetak rentang dalam format mentah yang dikirim; UI menampilkan dua kolom tanggal tanpa konversi tambahan.

### 4.6 Masalah lama yang masih ada (tidak diubah)

- `GET /api/workload/users/{id}` masih 500 (`Attempt to read property "value" on string`; Fase 3), dan `penugasan_aktif[].poin` di dashboard masih 0 karena `poin_beban` tidak ada. Keduanya di luar cakupan Fase 5.
- PRD 8.2 menyebut hitungan bulanan untuk penugasan bersamaan, sementara implementasi memakai puncak harian (keputusan pemilik nomor 4).

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps`. Seluruh query berikut tidak mengubah data.

### 5.1 Notifikasi jatuh tempo yang terkirim hari ini

```sql
SELECT n.user_id,
       u.nama AS penerima,
       n.tautan,
       n.pesan,
       n.created_at
FROM notifications n
JOIN users u ON u.id = n.user_id
WHERE n.tipe = 'kendala_jatuh_tempo'
  AND DATE(n.created_at) = CURDATE()
ORDER BY n.id DESC;
```

Hasil yang diharapkan: maksimal satu baris per (penerima, kendala) karena perintah ini idempoten dalam satu hari.

### 5.2 Notifikasi peringatan_jadwal yang belum dibaca

```sql
SELECT n.user_id,
       u.nama AS penerima,
       n.tautan,
       n.pesan,
       n.created_at
FROM notifications n
JOIN users u ON u.id = n.user_id
WHERE n.tipe = 'peringatan_jadwal'
  AND n.dibaca_pada IS NULL
ORDER BY n.created_at DESC
LIMIT 50;
```

Hasil yang diharapkan: paling banyak satu notifikasi belum dibaca per kombinasi (penerima, pegawai, bulan, jenis) karena dedupe dijalankan sebelum membuat notifikasi baru.

### 5.3 Kendala terlambat belum selesai berikut PIC dan ketua tim

```sql
SELECT wi.id,
       wi.minggu_mulai,
       wi.tenggat,
       wi.status,
       wi.pic_id,
       t.nama AS tim,
       t.ketua_id
FROM weekly_issues wi
JOIN teams t ON t.id = wi.team_id
WHERE wi.status <> 'selesai'
  AND wi.tenggat IS NOT NULL
  AND wi.tenggat < CURDATE()
  AND wi.deleted_at IS NULL
ORDER BY wi.tenggat
LIMIT 50;
```

Hasil yang diharapkan: daftar inilah yang disasar `notify:due-issues`. Bila ada baris tetapi tidak ada notifikasi terkait di 5.1, perintah belum dijalankan atau hari ini belum sampai jadwal 07:00.

### 5.4 Hitung kendala per tim pada rentang minggu (pembanding ekspor)

```sql
SELECT team_id,
       COUNT(*) AS jumlah
FROM weekly_issues
WHERE deleted_at IS NULL
  AND status = 'selesai'
  AND minggu_mulai >= '2026-01-05'
  AND minggu_mulai <= '2026-12-31'
GROUP BY team_id
ORDER BY team_id;
```

Hasil yang diharapkan: jumlah kendala selesai per tim; dapat dibandingkan dengan baris pada CSV/PDF hasil ekspor dengan filter `status=selesai&week_from=2026-01-05&week_to=2026-12-31`.

### 5.5 PIC yang tidak aktif atau bukan anggota tim (data lama)

```sql
SELECT wi.id, wi.team_id, wi.pic_id
FROM weekly_issues wi
WHERE wi.pic_id IS NOT NULL
  AND wi.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM team_members tm
      JOIN users u ON u.id = tm.user_id
      WHERE tm.team_id = wi.team_id
        AND tm.user_id = wi.pic_id
        AND u.aktif = 1
  );
```

Hasil yang diharapkan: baris boleh ada untuk data lama (dibuat sebelum Fase 4/5), karena aturan PIC anggota aktif hanya ditegakkan untuk penulisan baru. Kendala yang dibuat sesudah Fase 4 tidak boleh muncul.
