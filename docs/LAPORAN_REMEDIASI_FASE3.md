# Laporan Remediasi Fase 3

## 1. Berkas yang Diubah atau Ditambah

Fase 3 hanya menambah pengujian. Tidak ada kode produksi yang diubah dan `backend/app/Services/WorkloadService.php` tidak disentuh.

- `backend/tests/Feature/WorkloadServiceContractTest.php` (baru): 13 pengujian yang mencakup:
  1. pembagian poin proporsional hari pada penugasan yang melintasi tiga bulan;
  2. periode penugasan kosong memakai periode kegiatan;
  3. beberapa penugasan pada bulan yang sama dijumlahkan;
  4. pegawai tanpa penugasan bernilai nol dengan indikator `aman`;
  5. penugasan dan kegiatan yang di-soft-delete tidak dihitung;
  6. penugasan bersamaan memakai puncak harian, bukan jumlah penugasan sebulan;
  7. peringatan kegiatan besar bersamaan mengikuti batas pengaturan dan hanya muncul bila melebihi batas;
  8. perubahan `ambang_beban_bulanan` langsung mengubah persentase, indikator, dan peringatan;
  9. perubahan `maks_penugasan_bersamaan` langsung mengubah peringatan;
  10. hasil pratinjau penugasan baru dan penugasan yang diubah sama dengan hasil setelah disimpan;
  11. perubahan volume penugasan atau bobot kegiatan langsung memperbarui beban;
  12. angka beban identik antara `WorkloadController::index`, `DashboardController::index`, dan laporan CSV `ReportController::workloadReport` untuk pegawai dan bulan yang sama;
  13. angka `tren_bulanan` pada `WorkloadController::userDetail` sama dengan service, atau pengujian ditandai belum lengkap bila endpoint gagal.

Skenario yang perlu isolasi memakai bulan tetap pada 2032 agar tidak bercampur dengan data seeder 2026. Pengujian konsistensi antar-endpoint memakai bulan berjalan karena `DashboardController` selalu memakai bulan berjalan.

Berkas Fase 2 sudah di-commit oleh pemilik sebagai `d8048ec`. Pada akhir Fase 3, satu-satunya perubahan yang belum di-commit adalah berkas pengujian baru di atas.

## 2. Keluaran Verifikasi Mentah

### 2.1 Pengujian terarah

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test tests/Feature/WorkloadServiceContractTest.php
```

Keluaran:

```text
DB_DATABASE=jadwal_bps_test

   WARN  Tests\Feature\WorkloadServiceContractTest
  ✓ monthly points are split proportionally across months                                                        8.51s
  ✓ assignment without dates falls back to activity period                                                       0.04s
  ✓ multiple assignments in the same month are summed                                                            0.05s
  ✓ user without assignments has zero load and safe indicator                                                    0.04s
  ✓ soft deleted assignments and activities are not counted                                                      0.03s
  ✓ overlapping assignments use daily peak not monthly count                                                     0.08s
  ✓ big activity warning respects configured limit                                                               0.13s
  ✓ threshold setting change updates indicator and warning                                                       0.09s
  ✓ concurrent assignment limit setting change updates warning                                                   0.12s
  ✓ preview matches saved assignment result                                                                      0.04s
  ✓ changing volume or weight updates load                                                                       0.03s
  ✓ workload numbers are identical across index dashboard and report                                             0.19s
  … user detail endpoint matches workload service → GET /api/workload/users/{id} gagal 500 dengan pesan "Attemp… 0.05s

  Tests:    1 incomplete, 12 passed (97 assertions)
  Duration: 9.54s
```

Pengujian ke-13 ditandai belum lengkap karena endpoint detail beban kerja gagal 500. Temuan lengkap ada pada bagian 4.

### 2.2 Seluruh pengujian backend

Perintah:

```powershell
$env:DB_DATABASE='jadwal_bps_test'; php artisan test
```

Keluaran akhir:

```text
   WARN  Tests\Feature\WorkloadServiceContractTest
  ✓ monthly points are split proportionally across months                                                        0.05s
  ✓ assignment without dates falls back to activity period                                                       0.03s
  ✓ multiple assignments in the same month are summed                                                            0.05s
  ✓ user without assignments has zero load and safe indicator                                                    0.04s
  ✓ soft deleted assignments and activities are not counted                                                      0.04s
  ✓ overlapping assignments use daily peak not monthly count                                                     0.07s
  ✓ big activity warning respects configured limit                                                               0.11s
  ✓ threshold setting change updates indicator and warning                                                       0.08s
  ✓ concurrent assignment limit setting change updates warning                                                   0.09s
  ✓ preview matches saved assignment result                                                                      0.06s
  ✓ changing volume or weight updates load                                                                       0.03s
  ✓ workload numbers are identical across index dashboard and report                                             0.11s
  … user detail endpoint matches workload service → GET /api/workload/users/{id} gagal 500 dengan pesan "Attemp… 0.07s

  Tests:    1 incomplete, 132 passed (1349 assertions)
  Duration: 9.42s
```

Baseline sebelum Fase 3 adalah 120 lulus dan 1252 assertion. Fase 3 menambah 12 pengujian lulus dan 97 assertion.

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

Jumlah peringatan tidak bertambah dari baseline 33 dan tidak ada error.

### 2.5 Build frontend

Perintah:

```powershell
npm run build
```

Keluaran:

```text
dist/index.html                   0.45 kB │ gzip:   0.29 kB
dist/assets/index-DtSAYcUX.css   54.30 kB │ gzip:  10.13 kB
dist/assets/index-DPcfDxYU.js   934.10 kB │ gzip: 259.21 kB
(!) Some chunks are larger than 500 kB after minification.
✓ built in 808ms
```

Peringatan ukuran chunk sudah ada sebelum Fase 3.

### 2.6 Pemeriksaan larangan

```text
=====ALERT=====
=====EMOJI=====
```

Tidak ada hasil untuk `alert(` maupun emoji pada `frontend/src`.

### 2.7 Status berkas

```text
?? backend/tests/Feature/WorkloadServiceContractTest.php
?? docs/LAPORAN_REMEDIASI_FASE3.md
```

Tidak ada berkas lain yang berubah. Selain laporan fase itu sendiri (dokumen ini), satu-satunya berkas kode yang ditambah adalah pengujian baru. `git diff --stat` dan `git diff --cached --stat` kosong. Riwayat terakhir:

```text
d8048ec perubahan fase 2 keamanan akun dan audit log
b86573e perubahan fase 1 bug dan seeder
0e4135a perbaikan minor penulisan pengaturan menjadi profil & keamanan akun
```

### 2.8 Database

Seluruh perintah pengujian memakai `DB_DATABASE=jadwal_bps_test`. Tidak ada perintah `migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed` yang dijalankan pada `jadwal_bps`.

Satu berkas uji sementara dipakai untuk memastikan penyebab kegagalan endpoint (`TempProbeWorkloadTest.php`) dan sudah dihapus setelah probe. Berkas keluaran probe di `storage/logs/probe_poin.txt` juga sudah dihapus. Probe hanya berjalan pada database uji.

## 3. Hal yang Tidak Dapat Diverifikasi

- Pengujian ke-13 belum lengkap karena `GET /api/workload/users/{id}` gagal 500. Angka `tren_bulanan` pada endpoint tersebut belum dapat dibandingkan dengan service sampai temuan pada bagian 4 diperbaiki.
- Seluruh pengujian memakai model dan service langsung. Tampilan angka di antarmuka (WorkloadPage, DashboardPage, ReportsPage) tidak diuji karena Fase 3 hanya mencakup backend. Aturan PRD 8.1 "hitung dua desimal, tampilkan satu desimal" hanya diverifikasi pada nilai API; pembulatan tampilan di frontend tidak diverifikasi.
- Ekspor PDF beban kerja tidak diuji isinya. Pengujian hanya memeriksa CSV karena CSV dapat diurai tanpa dependensi tambahan.
- Peringatan kegiatan besar bersamaan diuji pada bulan 2032 agar tidak bercampur dengan kegiatan besar seeder 2026. Perilaku pada data seeder nyata tidak diuji ulang.
- Perhitungan pada penugasan yang periodenya tidak berada dalam periode kegiatan tidak diuji. Validasi tersebut sudah ditegakkan di lapisan request, bukan di `WorkloadService`, dan berada di luar cakupan Fase 3.

## 4. Temuan di Luar Lingkup yang Tidak Diubah

### 4.1 `GET /api/workload/users/{id}` gagal 500

`WorkloadController::userDetail` membaca properti `->value` pada kolom yang bertipe string:

- baris `'jenis_kegiatan' => $assignment->activity->jenis->value`
- baris `'satuan' => $assignment->satuan->value`

`Activity::casts()` tidak men-cast `jenis`, dan `Assignment::casts()` tidak men-cast `satuan`, sehingga keduanya berupa string. Pengujian menangkap pesan:

```text
Attempt to read property "value" on string
```

Akibatnya endpoint detail beban kerja tidak dapat dipakai selama ada penugasan pada rentang bulan yang diminta. Pengujian `test_user_detail_endpoint_matches_workload_service` ditandai `incomplete` dengan pesan temuan ini dan tidak diperbaiki karena Fase 3 hanya menambah pengujian.

### 4.2 `poin_beban` tidak pernah ada

`DashboardController::index` memakai `'poin' => (float) $as->poin_beban` dan `WorkloadController::userDetail` memakai `'poin_total' => (float) $assignment->poin_beban`. Kolom `poin_beban` tidak ada di tabel `assignments`, tidak ada accessor di `Assignment`, dan tidak didefinisikan di tempat lain (hasil pencarian `poin_beban` hanya dua baris pemakaian itu).

Probe pada database uji memberi hasil:

```json
{
    "status": 200,
    "penugasan_aktif": [
        {
            "id": 32,
            "kegiatan_id": 15,
            "kegiatan": "Kegiatan Probe",
            "tim": "Tim Probe",
            "peran": "Pencacah",
            "mulai": "2026-11-01",
            "selesai": "2026-11-30",
            "poin": 0,
            "status_kegiatan": "Berjalan"
        }
    ],
    "beban_pribadi": {
        "poin": 50,
        "persentase": 50,
        "indikator": "aman",
        "jumlah_penugasan": 1,
        "penugasan_bersamaan_maks": 1,
        "ambang_batas": 100
    }
}
```

Penugasan bernilai 40 volume dengan bobot kegiatan 1,25 seharusnya 50 poin, tetapi `penugasan_aktif[].poin` bernilai 0. Total beban pribadi tetap benar karena dihitung `WorkloadService`. Temuan ini tidak diubah karena berada di luar cakupan Fase 3 dan tidak memengaruhi total beban yang diuji.

### 4.3 Perbedaan penafsiran PRD 8.2 dengan keputusan pemilik

PRD 8.2 menyebut peringatan penugasan bersamaan sebagai jumlah penugasan yang periodenya beririsan dengan bulan. Kode dan keputusan pemilik nomor 4 memakai puncak tumpang tindih harian. Pengujian mengikuti keputusan pemilik, bukan teks PRD, dan membuktikan tiga penugasan berurutan dalam satu bulan tidak memicu peringatan. Penyimpangan ini sudah tercatat di `docs/CATATAN_KEPUTUSAN.md`.

### 4.4 Pembulatan per bulan dapat berbeda satu sen dari total

`calculateAssignmentMonthlyPoints` membulatkan poin setiap bulan ke dua desimal secara terpisah. Untuk periode tertentu, jumlah pembulatan bulanan bisa berbeda satu sen dari `round(volume * bobot, 2)`. Pengujian proporsional memakai rentang yang jumlahnya tetap 100,00 sehingga tidak memicu selisih. Perilaku ini tidak diubah dan tidak diuji sebagai kegagalan karena PRD meminta dua desimal per bulan.

### 4.5 Beban komputasi peringatan

`getMonthlyWarnings` memanggil `calculateMonthlyWorkloadForUser` untuk setiap pengguna aktif (pola N+1). Ini bukan kesalahan hasil, hanya catatan performa, dan tidak diubah pada fase pengujian.

### 4.6 Peringatan beban pada batas ambang

Kode memberi peringatan beban bila `beban >= ambang`, sesuai kalimat PRD "mencapai atau melebihi ambang". Pengujian ambang menetapkan beban 50 dan ambang 50, lalu memastikan peringatan muncul. Perilaku ini benar dan tidak memerlukan perubahan.

## 5. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps`. Seluruh query berikut tidak mengubah data.

### 5.1 Penugasan aktif pada satu bulan beserta poin totalnya

```sql
SELECT a.id,
       a.user_id,
       a.activity_id,
       a.volume,
       act.bobot,
       ROUND(a.volume * act.bobot, 2) AS poin_total,
       COALESCE(a.mulai, act.mulai) AS mulai_efektif,
       COALESCE(a.selesai, act.selesai) AS selesai_efektif
FROM assignments a
JOIN activities act ON act.id = a.activity_id
WHERE a.deleted_at IS NULL
  AND act.deleted_at IS NULL
  AND COALESCE(a.mulai, act.mulai) <= '2026-11-30'
  AND COALESCE(a.selesai, act.selesai) >= '2026-11-01'
ORDER BY a.user_id, mulai_efektif;
```

Hasil yang diharapkan: daftar penugasan yang beririsan dengan November 2026. Nilai `poin_total` dapat dibandingkan dengan rincian beban pada aplikasi.

### 5.2 Penugasan aktif pada kegiatan yang sudah di-soft-delete

```sql
SELECT a.id, a.user_id, a.activity_id, act.nama, act.deleted_at
FROM assignments a
JOIN activities act ON act.id = a.activity_id
WHERE a.deleted_at IS NULL
  AND act.deleted_at IS NOT NULL
ORDER BY a.id;
```

Hasil yang diharapkan: baris boleh ada, tetapi penugasan seperti ini tidak boleh ikut dihitung pada beban kerja. Bila aplikasi menghitungnya, ada masalah pada filter kegiatan.

### 5.3 Periode penugasan di luar periode kegiatan

```sql
SELECT a.id,
       a.user_id,
       a.activity_id,
       a.mulai,
       a.selesai,
       act.mulai AS kegiatan_mulai,
       act.selesai AS kegiatan_selesai
FROM assignments a
JOIN activities act ON act.id = a.activity_id
WHERE a.deleted_at IS NULL
  AND act.deleted_at IS NULL
  AND (
        (a.mulai IS NOT NULL AND a.mulai < act.mulai)
        OR (a.selesai IS NOT NULL AND a.selesai > act.selesai)
      )
ORDER BY a.id;
```

Hasil yang diharapkan: tidak ada baris. Bila ada, validasi PRD 8.4 belum ditegakkan pada data lama.

### 5.4 Puncak penugasan bersamaan harian seorang pegawai

Ganti nilai `a.user_id = 6` dan rentang tanggal sesuai kebutuhan.

```sql
WITH RECURSIVE days AS (
    SELECT DATE('2026-11-01') AS d
    UNION ALL
    SELECT DATE_ADD(d, INTERVAL 1 DAY)
    FROM days
    WHERE d < '2026-11-30'
)
SELECT days.d AS tanggal, COUNT(*) AS jumlah_penugasan
FROM days
JOIN assignments a
  ON a.deleted_at IS NULL
 AND COALESCE(a.mulai, (SELECT mulai FROM activities WHERE id = a.activity_id)) <= days.d
 AND COALESCE(a.selesai, (SELECT selesai FROM activities WHERE id = a.activity_id)) >= days.d
JOIN activities act
  ON act.id = a.activity_id
 AND act.deleted_at IS NULL
WHERE a.user_id = 6
GROUP BY days.d
ORDER BY jumlah_penugasan DESC, days.d
LIMIT 10;
```

Hasil yang diharapkan: baris teratas menunjukkan puncak jumlah penugasan yang benar-benar berjalan bersamaan pada hari yang sama. Angka ini harus sama dengan `penugasan_bersamaan_maks` pada aplikasi.

### 5.5 Pegawai aktif tanpa penugasan pada satu bulan

```sql
SELECT u.id, u.nama, u.email
FROM users u
WHERE u.aktif = 1
  AND u.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM assignments a
      JOIN activities act ON act.id = a.activity_id
      WHERE a.user_id = u.id
        AND a.deleted_at IS NULL
        AND act.deleted_at IS NULL
        AND COALESCE(a.mulai, act.mulai) <= '2026-11-30'
        AND COALESCE(a.selesai, act.selesai) >= '2026-11-01'
  )
ORDER BY u.nama;
```

Hasil yang diharapkan: pegawai pada daftar ini harus tampil dengan beban 0 dan indikator `aman` pada aplikasi.
