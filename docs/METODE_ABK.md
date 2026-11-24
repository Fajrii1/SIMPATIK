# Metode Analisis Beban Kerja (ABK)

**Proyek:** Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik BPS Kota Palembang (SIMPATIK)
**Status:** Prototipe demonstrasi
**Tanggal:** 24 November 2026

Dokumen ini menjelaskan cara SIMPATIK menghitung beban kerja pegawai dengan pendekatan Analisis Beban Kerja (ABK). Rumus ini menggantikan rumus lama `Total Points = Volume × Weight` (bobot/poin kegiatan).

> Catatan penting: norma waktu pada versi prototipe ini masih **estimasi** dan **belum divalidasi BPS**. Nilai contoh hanya untuk demonstrasi. Jangan memakai hasil perhitungan sebagai dasar keputusan kepegawaian resmi sebelum norma divalidasi.

---

## 1. Ringkasan Rumus

Beban kerja dihitung dari jumlah pekerjaan yang ditugaskan, bukan dari lama duduk di kantor.

```
Total menit = round(volume × norma_menit)
Total jam   = Total menit / 60
```

Keterangan:

- `volume`: banyaknya satuan pekerjaan yang ditugaskan (mis. 40 dokumen, 12 laporan).
- `norma_menit`: waktu baku menyelesaikan satu satuan pekerjaan, dalam menit, diambil dari master **Jenis Pekerjaan**.
- `Total menit` dibulatkan ke bilangan bulat pada saat penugasan disimpan (dihitung di server, bukan di browser).

Contoh: 40 dokumen pemeriksaan dengan norma 15 menit per dokumen.

```
Total menit = 40 × 15 = 600 menit
Total jam   = 600 / 60 = 10 jam
```

## 2. Master Jenis Pekerjaan (Norma Waktu)

Norma waktu disimpan pada tabel `work_types` dan dikelola admin melalui menu **Norma Waktu** (`/work-types`).

| Kolom | Makna |
|---|---|
| `nama` | Nama jenis pekerjaan, unik. |
| `satuan` | Satuan volume (mis. dokumen, dataset, laporan). |
| `norma_menit` | Waktu baku per satuan, bilangan bulat menit, harus > 0. |
| `jenis_norma` | `estimasi` (belum divalidasi) atau `standar` (mengikuti ketetapan resmi). |
| `sumber` | Rujukan norma, boleh kosong. |
| `aktif` | Hanya jenis aktif yang muncul di form penugasan. |

Jenis pekerjaan yang sudah pernah dipakai pada penugasan **tidak boleh dihapus** (relasi FK `restrict`). Nonaktifkan saja agar tidak muncul di form, sementara riwayat tetap utuh.

Data contoh yang dibuat migrasi (jenis `estimasi`, sumber "Contoh untuk demonstrasi, belum divalidasi BPS"):

| Jenis pekerjaan | Satuan | Norma (menit) |
|---|---|---|
| Pemeriksaan dokumen | dokumen | 15 |
| Pengolahan data | dataset | 30 |
| Penyusunan laporan | laporan | 120 |
| Verifikasi data | data | 10 |

## 3. Snapshot Norma pada Penugasan

Saat penugasan dibuat atau diubah, `work_type_id`, `norma_menit`, dan `total_menit` disalin ke baris `assignments`. Perhitungan beban selalu memakai nilai snapshot ini.

Akibat yang disengaja:

- Mengubah norma pada master **tidak mengubah** beban penugasan lama. Histori tetap konsisten dengan norma saat penugasan dibuat.
- Perubahan norma hanya berlaku untuk penugasan baru atau penugasan yang diubah setelah perubahan tersebut.

## 4. Distribusi ke Hari Kerja

`Total jam` sebuah penugasan disebar **rata** ke seluruh hari kerja (Senin–Jumat, dikurangi hari libur) dalam periode efektif penugasan:

- Periode efektif memakai `assignments.mulai`/`assignments.selesai` bila diisi, atau periode kegiatan (`activities.mulai`/`activities.selesai`) bila tidak.
- Bila penugasan membentang lintas bulan, beban dibagi proporsional menurut jumlah hari kerja pada irisan tiap bulan.

```
Jam per hari = Total jam / jumlah hari kerja periode efektif
```

Contoh: penugasan 10 jam pada periode 9–13 November 2026 (5 hari kerja).

```
Jam per hari = 10 / 5 = 2 jam/hari
```

## 5. Kapasitas dan Indikator

```
Kapasitas bulan = jumlah hari kerja bulan itu × jam_kerja_per_hari
Persentase      = (total jam beban bulan itu / kapasitas bulan) × 100
```

- `jam_kerja_per_hari` default 7 jam, diatur pada Pengaturan Sistem.
- Hari libur nasional/cuti bersama yang tercatat pada kalender kerja tidak dihitung sebagai hari kerja.
- Indikator: **Aman** (< 80%), **Hampir Penuh** (80% s.d. < 100%), **Melebihi** (≥ 100%).
- Peringatan kapasitas harian tetap berlaku bila jam/hari turunan melebihi `jam_kerja_per_hari` (7 jam/hari).
- Peringatan penugasan bersamaan tetap memakai puncak tumpang tindih harian, bukan jumlah penugasan sebulan.

## 6. Konsistensi Angka

Satu sumber rumus dipakai bersama: `backend/app/Services/WorkloadService.php`.

- Monitoring beban kerja (`WorkloadController`), Dashboard agenda (`DashboardController`), Kalender (`CalendarController`), dan ekspor laporan membaca hasil dari service yang sama.
- Kolom `jam_per_hari` pada `assignments` tetap ada demi kompatibilitas, tetapi diperlakukan sebagai **nilai turunan** untuk tampilan, bukan masukan perhitungan.
- Angka yang tampil di Monitoring, Dashboard, dan Report untuk periode yang sama harus identik.

## 7. Kompatibilitas Data Lama

Migrasi ABK mengisi data lama agar angka sebelum dan sesudah migrasi sama:

```
total_menit lama = round(jam_per_hari × jumlah hari kerja periode × 60)
```

`work_type_id` data lama dibiarkan `null`. Penugasan lama tetap dihitung dari `total_menit` hasil backfill tersebut. Bila `total_menit` kosong, service memakai jalur lama (`jam_per_hari × hari kerja`) sebagai fallback.

## 8. Batas Prototipe

- Norma waktu masih estimasi dan wajib divalidasi sebelum dipakai resmi.
- Distribusi beban mengasumsikan pekerjaan diselesaikan merata tiap hari kerja.
- Belum memperhitungkan tingkat kesulitan, pengalaman pegawai, atau kerja lembur.
- Volume harus diisi untuk setiap penugasan baru atau yang diubah; mode input jam manual telah dihapus dari antarmuka.

## 9. Prosedur Migrasi ke Database Produksi

Migrasi menambah tabel dan kolom serta mengubah data lama, sehingga wajib dijalankan hati-hati. Jangan memakai `migrate:fresh`, `db:wipe`, `DROP`, atau `TRUNCATE`.

1. **Backup penuh** database sebelum migrasi (`mysqldump`).
2. Catat status migrasi dan jumlah baris tabel terkait:
   - `php artisan migrate:status`
   - `SELECT COUNT(*) FROM assignments;` (dan tabel lain yang terdampak)
3. Periksa rencana migrasi tanpa menulis: `php artisan migrate --pretend`.
4. Jalankan migrasi: `php artisan migrate`.
5. Verifikasi hasil dengan `SELECT`:
   - jumlah baris `work_types` (harus berisi 4 contoh bila sebelumnya kosong),
   - `total_menit` pada `assignments` sudah terisi dan wajar,
   - `work_type_id` data lama `null`.
6. Bila terjadi kegagalan, rollback **hanya batch baru** (`php artisan migrate:rollback --batch=<batch_baru>`), lalu pulihkan dari backup bila perlu.

## 10. Rujukan Kode

- `backend/app/Services/WorkloadService.php` — sumber tunggal rumus ABK.
- `backend/database/migrations/2026_11_24_000001_create_work_types_and_add_abk_to_assignments.php` — tabel, kolom, seed contoh, backfill.
- `backend/app/Http/Controllers/Api/WorkTypeController.php` — CRUD norma waktu.
- `frontend/src/pages/WorkTypesPage.tsx` — halaman admin Norma Waktu.
- `backend/tests/Feature/WorkTypeAndAbkTest.php` — pengujian rumus ABK dan snapshot.
