# Laporan Remediasi Fase 1

## 1. Ringkasan

Fase 1 memperbaiki temuan audit Fase 0 pada kontrak pengaturan, idempotensi seeder, kontrol sidebar, alur logout, dokumentasi keputusan, tampilan pemisah teks, dan cakupan pengujian. Perubahan dilakukan pada branch `development` tanpa commit, push, pembuatan branch baru, atau perubahan pada `PRD.md`.

Tidak ada perintah yang mengubah data pada database `jadwal_bps`. Seluruh pengujian backend memakai database `jadwal_bps_test`.

## 2. Perubahan yang Dikerjakan

### 2.1 Kontrak pengaturan

- `frontend/src/pages/SettingsPage.tsx` kini membaca `res.data.settings` berupa objek flat, bukan `res.data.data` berupa koleksi resource.
- Nilai angka dinormalisasi dengan `Number` dan nilai boolean menerima `true`, `"true"`, `1`, atau `"1"`.
- Payload `PUT /settings` hanya mengirim empat kunci flat: `ambang_beban_bulanan`, `maks_penugasan_bersamaan`, `maks_kegiatan_besar_bersamaan`, dan `pegawai_boleh_isi_kendala`.
- Setelah penyimpanan berhasil, form disegarkan dari `res.data.settings` sehingga nilai yang tampil sama dengan nilai tersimpan.

### 2.2 Idempotensi seeder

- `backend/database/seeders/TeamSeeder.php` hanya mengubah nama alias menjadi nama kanonik. `ketua_id`, status `aktif`, dan anggota tim lama tidak lagi ditimpa.
- `backend/database/seeders/UserSeeder.php` memakai pemeriksaan keberadaan akun dan `firstOrCreate`, sehingga kata sandi serta `must_change_password` akun yang sudah ada tidak berubah.
- Pada `APP_ENV=production`, `UserSeeder` mewajibkan `SEED_ADMIN_PASSWORD` dan `SEED_KEPALA_PASSWORD`, lalu menetapkan `must_change_password=true` untuk akun baru. Di luar production, akun baru memakai kata sandi pengembangan `password` dengan `must_change_password=false`.
- `backend/database/seeders/DemoDataSeeder.php` memakai `firstOrCreate` untuk akun demo, sehingga kata sandi dan flag akun demo yang sudah ada tetap dipertahankan.
- `backend/.env.example` mencantumkan dua variabel kata sandi seeder baru beserta penjelasannya.

### 2.3 Sidebar dan logout

- `frontend/src/layouts/MainLayout.tsx` memiliki tombol khusus `PanelLeftClose`/`PanelLeftOpen` pada header merek sidebar.
- Tombol dilengkapi `aria-label`, `aria-expanded`, `title`, dan target sentuh minimal 44px.
- Status ciut disimpan pada `localStorage` dengan kunci `simpatik.sidebar.collapsed` dan dipulihkan saat halaman dimuat.
- Klik pada seluruh area sidebar tidak lagi mengubah status ciut.
- Perilaku drawer seluler pada lebar 360px tidak berubah: tombol tutup tetap menutup drawer dan navigasi menutup drawer.
- `frontend/src/context/AuthContext.tsx` menjadi satu-satunya tempat logout. `MainLayout` tidak lagi memanggil `navigate`, dan sesi lokal tetap dibersihkan bila permintaan logout gagal.

### 2.4 Dokumentasi dan tampilan

- `docs/CATATAN_KEPUTUSAN.md` bagian 11 melengkapi keputusan pemilik 1 sampai 8, termasuk deviasi yang disengaja dari PRD.
- Pemisah `|` pada teks antarmuka diganti `•` di Dashboard, Workload, Heatmap, Timeline, kalender bulanan, Login, Activities, dan Users.
- Placeholder kata sandi pada `LoginPage.tsx` diganti menjadi `••••••••`.

### 2.5 Pengujian

- `backend/tests/Feature/SeederTest.php` menambah pengujian idempotensi `DemoDataSeeder`, preservasi kata sandi dan flag `UserSeeder`, preservasi akun demo, serta normalisasi alias tim tanpa menimpa ketua dan status aktif.
- `backend/tests/Feature/TeamManagementTest.php` menambah pengujian eksplisit bahwa ketua yang ditunjuk otomatis menjadi anggota saat pembuatan dan pembaruan tim.
- Pengujian alias tim disesuaikan agar tidak bergantung pada ada atau tidaknya tim kanonik dari `DemoDataSeeder`, sehingga stabil saat dijalankan sendiri maupun dalam rangkaian penuh.

## 3. Hasil Verifikasi

| Pemeriksaan | Perintah | Hasil |
| --- | --- | --- |
| Test backend | `php artisan test` dengan `DB_DATABASE=jadwal_bps_test` | 110 test lulus, 1179 assertion |
| Format backend | `vendor/bin/pint --test` | Lulus |
| Build frontend | `npm run build` | Lulus, hanya peringatan ukuran chunk yang sudah ada |
| Lint frontend | `npx oxlint` | 33 peringatan, 0 error, sama dengan baseline Fase 0 |
| Pemeriksaan `alert()` | pencarian `alert(` pada `frontend/src` | Tidak ditemukan |
| Pemeriksaan emoji | pencarian pasangan surrogate pada `frontend/src`, `backend/app`, `backend/database`, `docs` | Tidak ditemukan |
| Perubahan data produksi | tidak ada perintah migrasi atau seeder pada `jadwal_bps` | Tidak ada |

Berkas yang berubah pada Fase 1:

- `backend/.env.example`
- `backend/database/seeders/DemoDataSeeder.php`
- `backend/database/seeders/TeamSeeder.php`
- `backend/database/seeders/UserSeeder.php`
- `backend/tests/Feature/SeederTest.php`
- `backend/tests/Feature/TeamManagementTest.php`
- `docs/CATATAN_KEPUTUSAN.md`
- `docs/LAPORAN_REMEDIASI_FASE1.md`
- `frontend/src/context/AuthContext.tsx`
- `frontend/src/layouts/MainLayout.tsx`
- `frontend/src/pages/ActivitiesPage.tsx`
- `frontend/src/pages/CalendarHeatmapTab.tsx`
- `frontend/src/pages/CalendarMonthlyCalendarTab.tsx`
- `frontend/src/pages/CalendarTimelineTab.tsx`
- `frontend/src/pages/DashboardPage.tsx`
- `frontend/src/pages/LoginPage.tsx`
- `frontend/src/pages/SettingsPage.tsx`
- `frontend/src/pages/UsersPage.tsx`
- `frontend/src/pages/WorkloadPage.tsx`

## 4. Query SQL Read-Only untuk Pemeriksaan Manual

Jalankan hanya untuk membaca data pada database `jadwal_bps`. Kedua query tidak mengubah data.

```sql
SELECT kunci, nilai FROM settings WHERE kunci='pegawai_boleh_isi_kendala';
```

```sql
SELECT id, nama, bobot FROM activities WHERE bobot < 0.5 OR bobot > 3;
```

Hasil yang diharapkan:

- Query pertama menampilkan nilai `pegawai_boleh_isi_kendala` yang berlaku saat ini. Nilai `true` berarti pegawai boleh mengisi kendala, `false` berarti tidak boleh.
- Query kedua menampilkan kegiatan yang bobotnya berada di luar rentang 0,50 sampai 3,00. Fase 1 tidak mengubah data kegiatan, sehingga query ini dipakai sebagai bahan verifikasi sebelum aturan bobot diterapkan pada fase berikutnya.

## 5. Catatan dan Batasan

- Seluruh perubahan Fase 1 masih belum di-commit dan belum di-push.
- `SettingsPage.tsx` masih memiliki impor `SettingsIcon` yang tidak terpakai. Ini termasuk 33 peringatan lint yang sudah ada pada baseline dan tidak diubah agar cakupan Fase 1 tetap terbatas.
- Fase 1 tidak menerapkan keputusan pemilik tentang pelapor kendala, pembatasan beban kerja ketua tim, batas bobot 0,50 sampai 3,00, validasi periode penugasan, kewajiban penanggung jawab, pembatasan visibilitas volume, dan akun pimpinan tunggal. Keputusan tersebut sudah dicatat di `docs/CATATAN_KEPUTUSAN.md` bagian 11 untuk dikerjakan pada fase berikutnya.
- Perbaikan berikutnya menunggu perintah `LANJUT FASE 2`.
