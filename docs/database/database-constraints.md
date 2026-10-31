# Spesifikasi Constraint, Indeks, dan Integritas Basis Data
**Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik — BPS Kota Palembang**  
**Sumber Spesifikasi:** `PRD.md` Bagian 8, 13, & 16  

---

## 1. Daftar Foreign Key & Aturan Integritas Referensial

| Tabel Asal | Kolom FK | Tabel Tujuan | Kolom Tujuan | Sifat | ON DELETE | ON UPDATE | Alasan Desain |
|---|---|---|---|---|---|---|---|
| `teams` | `ketua_id` | `users` | `id` | Opsional | `SET NULL` | `CASCADE` | Tim tidak boleh bubar hanya karena user ketua tim terhapus secara fisik. |
| `team_members` | `team_id` | `teams` | `id` | Wajib | `CASCADE` | `CASCADE` | Tabel pivot relasi, jika tim terhapus maka asosiasi ikut terhapus. |
| `team_members` | `user_id` | `users` | `id` | Wajib | `CASCADE` | `CASCADE` | Jika user terhapus permanen, keanggotaan tim otomatis gugur. |
| `activities` | `team_id` | `teams` | `id` | Wajib | `RESTRICT` | `CASCADE` | Mencegah penghapusan tim yang memiliki riwayat kegiatan aktif. |
| `assignments` | `activity_id` | `activities` | `id` | Wajib | `CASCADE` | `CASCADE` | Jika kegiatan dihapus hard delete, alokasi penugasan di dalamnya ikut terhapus. |
| `assignments` | `user_id` | `users` | `id` | Wajib | `RESTRICT` | `CASCADE` | Mencegah penghapusan akun pegawai yang memiliki rekam penugasan. |
| `weekly_issues` | `activity_id` | `activities` | `id` | Opsional | `SET NULL` | `CASCADE` | Kendala dapat berupa isu independen tim jika kegiatan dihapus. |
| `weekly_issues` | `team_id` | `teams` | `id` | Wajib | `RESTRICT` | `CASCADE` | Kendala wajib memiliki unit tim penanggung jawab. |
| `weekly_issues` | `pelapor_id` | `users` | `id` | Wajib | `RESTRICT` | `CASCADE` | Integritas pencatatan identitas pelapor kendala. |
| `weekly_issues` | `pic_id` | `users` | `id` | Opsional | `SET NULL` | `CASCADE` | Jika pegawai PIC dihapus, kendala tetap tersimpan dan PIC menjadi null. |
| `notifications` | `user_id` | `users` | `id` | Wajib | `CASCADE` | `CASCADE` | Notifikasi melekat pada pengguna individual. |
| `audit_logs` | `user_id` | `users` | `id` | Opsional | `SET NULL` | `CASCADE` | Log audit harus abadi demi hukum & transparansi kendati akun dihapus. |

---

## 2. Unique Constraints

1. **`users.email`**:
   - Kolom `email` memiliki constraint `UNIQUE KEY (email)`.
   - Menghindari pendaftaran duplikat akun. Penonaktifan akun menggunakan `aktif = false` sehingga email akun nonaktif tetap terlindungi.
2. **`team_members(team_id, user_id)`**:
   - Constraint unik gabungan `UNIQUE KEY unique_team_member (team_id, user_id)`.
   - Menjamin integritas relasi: satu pegawai tidak boleh didaftarkan ganda pada tim yang sama.
3. **`settings.kunci`**:
   - Constraint `UNIQUE KEY (kunci)`.
   - Menjamin satu kunci parameter konfigurasi hanya ada satu baris di sistem.

---

## 3. Spesifikasi Indeks Basis Data

Indeks disusun untuk mengoptimasi kueri analitik, kalender, filtering dashboard, dan agregasi beban kerja sesuai PRD Bagian 13.

| Tabel | Nama Indeks | Kolom | Tujuan & Optimasi Kueri |
|---|---|---|---|
| `activities` | `idx_activities_team_timeline` | `(team_id, mulai, selesai)` | Kueri penyaringan kalender kegiatan lintas tim dan filter jadwal beririsan. |
| `assignments` | `idx_assignments_user_timeline` | `(user_id, mulai, selesai)` | Perhitungan beban kerja bulanan per pegawai, pencarian irisan tanggal penugasan. |
| `weekly_issues` | `idx_weekly_issues_team_week_status` | `(team_id, minggu_mulai, status)` | Pengambilan kendala mingguan tim dan deteksi isu "terbawa" (*carried-over*). |
| `notifications` | `idx_notifications_user_read` | `(user_id, dibaca_pada)` | Optimasi kueri polling lonceng notifikasi dan *unread counter* pegawai. |
| `audit_logs` | `idx_audit_logs_object` | `(objek, objek_id)` | Pemuatan tab riwayat mutasi pada form detail kegiatan dan penugasan. |

---

## 4. Pembagian Tanggung Jawab Integritas (Database vs Laravel Application Layer)

Untuk memastikan portabilitas maksimal antara MySQL 8.0 dan MariaDB 10.4+ pada XAMPP lokal, berikut pembagian tanggung jawab validasi integritas:

| Aturan Integritas Data | Level Database (Constraint) | Level Aplikasi Laravel (Form Request / Policy / Service) |
|---|---|---|
| **Format & Keunikan Email** | `VARCHAR(150)`, `UNIQUE` | `email:rfc,dns`, `unique:users,email` |
| **Pilihan Nilai Peran** | `ENUM('admin', 'pegawai', 'pimpinan')` | `Rule::enum(PeranPengguna::class)` |
| **Pilihan Status Kendala** | `ENUM('terbuka', 'diproses', 'selesai')` | `Rule::enum(StatusKendala::class)` |
| **Keunikan Anggota Tim** | `UNIQUE(team_id, user_id)` | `Rule::unique('team_members')->where(...)` |
| **Tanggal Selesai >= Mulai Kegiatan** | CHECK constraint dasar pada MySQL 8+ | Validasi Form Request: `'selesai' => 'required\|date\|after_or_equal:mulai'` |
| **Periode Efektif Penugasan di Dalam Kegiatan** | Tidak dapat diekspresikan via SQL standard FK sederhana | Validasi `AssignmentRequest`: `mulai >= activity.mulai` dan `selesai <= activity.selesai` |
| **Pegawai yang Ditugaskan Harus Aktif** | Relasi FK ke `users.id` | Validasi `AssignmentRequest`: Memeriksa kolom `users.aktif == true` |
| **`minggu_mulai` Wajib Hari Senin** | Format `DATE` | Validasi Form Request: Memeriksa nilai hari tanggal = Monday (`date_format:Y-m-d`, `Carbon::parse($val)->isMonday()`) |
| **Kunci Pengaturan Global** | `VARCHAR(60)`, `UNIQUE` | Validasi Form Request dan enum kunci pengaturan |

