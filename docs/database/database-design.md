# Desain Basis Data (Data Dictionary)
**Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik — BPS Kota Palembang**  
**Basis Data:** MySQL / MariaDB (XAMPP) | **Nama Basis Data:** `jadwal_bps`  
**Charset:** `utf8mb4` | **Collation:** `utf8mb4_unicode_ci`  
**Sumber Spesifikasi:** `PRD.md` Bagian 13  

---

## 1. Ringkasan Arsitektur Basis Data

Rancangan database terdiri dari 9 tabel yang dinormalisasi hingga 3NF (Third Normal Form) untuk memastikan konsistensi relasi, eliminasi redundansi data, dan kepatuhan penuh terhadap aturan bisnis PRD.

### Karakteristik Umum Seluruh Tabel:
- **Primary Key**: Kolom `id` bertipe `BIGINT UNSIGNED AUTO_INCREMENT`.
- **Timestamps**: Kolom `created_at` dan `updated_at` bertipe `TIMESTAMP NULL`.
- **Soft Deletes**: Kolom `deleted_at` bertipe `TIMESTAMP NULL` diterapkan hanya pada 5 tabel yang bertanda `(SD)`: `users`, `teams`, `activities`, `assignments`, dan `weekly_issues`.
- **Tanpa Redundansi Kolom Terhitung**: Tidak ada tabel/kolom yang menyimpan status kegiatan atau rekap poin beban kerja bulanan, karena seluruh kalkulasi dilakukan di backend (`WorkloadService`).

---

## 2. Kamus Data (Data Dictionary) 9 Tabel

### 2.1 Tabel `users` (Pengguna) — *(SD)*
Menyimpan seluruh data identitas dan akun pengguna sistem (Admin, Pegawai, Pimpinan).

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `nama` | VARCHAR(120) | NOT NULL | - | Nama lengkap pegawai / pengguna |
| `email` | VARCHAR(150) | NOT NULL | - | Alamat email dinas, UNIQUE |
| `password` | VARCHAR(255) | NOT NULL | - | Hash bcrypt kata sandi |
| `peran` | ENUM('admin', 'pegawai', 'pimpinan') | NOT NULL | 'pegawai' | Peran sistem pengguna |
| `nip` | VARCHAR(30) | NULL | NULL | Nomor Induk Pegawai (opsional) |
| `jabatan` | VARCHAR(100) | NULL | NULL | Jabatan struktural/fungsional |
| `aktif` | BOOLEAN | NOT NULL | 1 (true) | Penanda status aktif akun. Jika false, ditolak login |
| `must_change_password` | BOOLEAN | NOT NULL | 1 (true) | Wajib ubah kata sandi pada login awal |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pembuatan baris data |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan baris data |
| `deleted_at` | TIMESTAMP | NULL | NULL | Penanda soft delete |

---

### 2.2 Tabel `teams` (Tim Kerja) — *(SD)*
Menyimpan unit tim kerja statistik di lingkungan BPS Kota Palembang.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `nama` | VARCHAR(100) | NOT NULL | - | Nama tim kerja (misal: Tim Sosial, Tim IPDS) |
| `ketua_id` | BIGINT UNSIGNED | NULL | NULL | Foreign Key ke `users.id` (ON DELETE SET NULL) |
| `aktif` | BOOLEAN | NOT NULL | 1 (true) | Penanda status aktif tim |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pembuatan baris data |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan baris data |
| `deleted_at` | TIMESTAMP | NULL | NULL | Penanda soft delete |

---

### 2.3 Tabel `team_members` (Anggota Tim Kerja)
Tabel pivot many-to-many yang menghubungkan pegawai (`users`) dengan tim kerja (`teams`).

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `team_id` | BIGINT UNSIGNED | NOT NULL | - | Foreign Key ke `teams.id` (ON DELETE CASCADE) |
| `user_id` | BIGINT UNSIGNED | NOT NULL | - | Foreign Key ke `users.id` (ON DELETE CASCADE) |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu penambahan anggota |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan keanggotaan |

**Constraint Tambahan:**
- `UNIQUE KEY unique_team_user (team_id, user_id)`: Menjamin tidak ada pegawai yang terdaftar ganda di tim yang sama.

---

### 2.4 Tabel `activities` (Kegiatan Statistik) — *(SD)*
Menyimpan agenda kegiatan sensus, survei, pembinaan, atau pengolahan data statistik.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `nama` | VARCHAR(200) | NOT NULL | - | Nama lengkap kegiatan statistik |
| `jenis` | VARCHAR(60) | NOT NULL | - | Klasifikasi jenis kegiatan (Survei, Sensus, dll) |
| `team_id` | BIGINT UNSIGNED | NOT NULL | - | Foreign Key ke `teams.id` (ON DELETE RESTRICT) |
| `mulai` | DATE | NOT NULL | - | Tanggal mulai kegiatan |
| `selesai` | DATE | NOT NULL | - | Tanggal selesai kegiatan (wajib >= mulai) |
| `bobot` | DECIMAL(5,2) | NOT NULL | 1.00 | Faktor bobot pengali beban penugasan |
| `kegiatan_besar` | BOOLEAN | NOT NULL | 0 (false) | Flag kegiatan prioritas berskala besar |
| `selesai_manual` | BOOLEAN | NOT NULL | 0 (false) | Flag penanda kegiatan telah ditutup manual |
| `selesai_pada` | DATETIME | NULL | NULL | Waktu penandaan selesai manual |
| `keterangan` | TEXT | NULL | NULL | Catatan atau deskripsi kegiatan |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pembuatan kegiatan |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan kegiatan |
| `deleted_at` | TIMESTAMP | NULL | NULL | Penanda soft delete |

**Indeks:**
- `INDEX idx_activities_team_timeline (team_id, mulai, selesai)`: Indeks komposit untuk optimasi kueri kalender dan filter tim.

---

### 2.5 Tabel `assignments` (Penugasan Pegawai) — *(SD)*
Menyimpan alokasi tugas spesifik kepada pegawai pada kegiatan statistik.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `activity_id` | BIGINT UNSIGNED | NOT NULL | - | Foreign Key ke `activities.id` (ON DELETE CASCADE) |
| `user_id` | BIGINT UNSIGNED | NOT NULL | - | Foreign Key ke `users.id` (ON DELETE RESTRICT) |
| `peran` | VARCHAR(60) | NOT NULL | - | Peran spesifik (Pencacah, Pengawas, Editor, dll) |
| `volume` | DECIMAL(10,2) | NOT NULL | - | Target volume penugasan (misal: 60 dokumen) |
| `satuan` | VARCHAR(30) | NOT NULL | - | Satuan volume (Dokumen, Blok Sensus, dll) |
| `mulai` | DATE | NULL | NULL | Tanggal awal penugasan (fallback: activity.mulai) |
| `selesai` | DATE | NULL | NULL | Tanggal akhir penugasan (fallback: activity.selesai) |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pembuatan penugasan |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan penugasan |
| `deleted_at` | TIMESTAMP | NULL | NULL | Penanda soft delete |

**Indeks:**
- `INDEX idx_assignments_user_timeline (user_id, mulai, selesai)`: Indeks komposit untuk kueri agregasi beban kerja bulanan per pegawai.

---

### 2.6 Tabel `weekly_issues` (Kendala Mingguan) — *(SD)*
Mencatat kendala pelaksanaan kegiatan atau koordinasi internal tim untuk bahan evaluasi rapat mingguan.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `activity_id` | BIGINT UNSIGNED | NULL | NULL | FK ke `activities.id` (ON DELETE SET NULL, opsional) |
| `team_id` | BIGINT UNSIGNED | NOT NULL | - | FK ke `teams.id` (ON DELETE RESTRICT) |
| `pelapor_id` | BIGINT UNSIGNED | NOT NULL | - | FK ke `users.id` (ON DELETE RESTRICT) |
| `minggu_mulai` | DATE | NOT NULL | - | Tanggal hari Senin awal minggu kerja |
| `kendala` | TEXT | NOT NULL | - | Deskripsi detail permasalahan yang dihadapi |
| `solusi` | TEXT | NULL | NULL | Usulan jalan keluar permasalahan |
| `tindak_lanjut` | TEXT | NULL | NULL | Rencana aksi atau tindakan riil yang disepakati |
| `pic_id` | BIGINT UNSIGNED | NULL | NULL | FK ke `users.id` (ON DELETE SET NULL, PIC tindak lanjut) |
| `tenggat` | DATE | NULL | NULL | Batas waktu penyelesaian kendala |
| `status` | ENUM('terbuka', 'diproses', 'selesai') | NOT NULL | 'terbuka' | Status progres penanganan kendala |
| `catatan_penutupan` | TEXT | NULL | NULL | Evaluasi atau catatan saat kendala dinyatakan selesai |
| `ditutup_pada` | DATETIME | NULL | NULL | Waktu resmi kendala ditutup |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pencatatan kendala |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan kendala |
| `deleted_at` | TIMESTAMP | NULL | NULL | Penanda soft delete |

**Indeks:**
- `INDEX idx_weekly_issues_team_week_status (team_id, minggu_mulai, status)`: Optimasi kueri daftar mingguan dan pendeteksian isu terbawa (*carried over*).

---

### 2.7 Tabel `notifications` (Notifikasi Internal)
Menyimpan pemberitahuan in-app bagi pegawai terkait jadwal, penugasan, dan kendala jatuh tempo.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `user_id` | BIGINT UNSIGNED | NOT NULL | - | FK ke `users.id` (ON DELETE CASCADE) |
| `tipe` | VARCHAR(40) | NOT NULL | - | Kategori notifikasi (penugasan, jadwal, kendala) |
| `pesan` | VARCHAR(255) | NOT NULL | - | Isi ringkas pesan notifikasi |
| `tautan` | VARCHAR(255) | NULL | NULL | URL tujuan internal aplikasi |
| `dibaca_pada` | DATETIME | NULL | NULL | Waktu saat notifikasi diklik/dibaca |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu notifikasi terbit |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan status |

**Indeks:**
- `INDEX idx_notifications_user_read (user_id, dibaca_pada)`: Mempercepat kueri jumlah notifikasi belum dibaca (*unread count*).

---

### 2.8 Tabel `audit_logs` (Riwayat Jejak Perubahan)
Mencatat seluruh mutasi data penting (kegiatan, penugasan) secara kronologis demi akuntabilitas sistem.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `user_id` | BIGINT UNSIGNED | NULL | NULL | FK ke `users.id` (ON DELETE SET NULL, aktor perubahan) |
| `aksi` | VARCHAR(40) | NOT NULL | - | Jenis operasi (`create`, `update`, `delete`, dll) |
| `objek` | VARCHAR(60) | NOT NULL | - | Nama entitas model (`Activity`, `Assignment`, dll) |
| `objek_id` | BIGINT UNSIGNED | NOT NULL | - | Primary key dari record yang termutasi |
| `sebelum` | JSON | NULL | NULL | Snapshot atribut data sebelum perubahan |
| `sesudah` | JSON | NULL | NULL | Snapshot atribut data sesudah perubahan |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pencatatan jejak audit |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan |

**Indeks:**
- `INDEX idx_audit_logs_object (objek, objek_id)`: Mempercepat pengambilan riwayat mutasi pada form detail kegiatan/penugasan.

---

### 2.9 Tabel `settings` (Pengaturan Sistem)
Menyimpan konfigurasi parameter global yang dapat diatur oleh administrator secara dinamis tanpa mengubah source code.

| Nama Kolom | Tipe Data & Panjang | Nullable | Default | Keterangan & Constraint |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | AUTO_INCREMENT | Primary Key |
| `kunci` | VARCHAR(60) | NOT NULL | - | Nama parameter konfigurasi, UNIQUE |
| `nilai` | VARCHAR(255) | NOT NULL | - | Nilai teks konfigurasi |
| `created_at` | TIMESTAMP | NULL | NULL | Waktu pencatatan konfigurasi |
| `updated_at` | TIMESTAMP | NULL | NULL | Waktu pembaruan nilai konfigurasi |

**Data Awal Wajib (Seeder):**
1. `ambang_beban_bulanan` = `'100'`
2. `maks_penugasan_bersamaan` = `'3'`
3. `maks_kegiatan_besar_bersamaan` = `'3'`
4. `pegawai_boleh_isi_kendala` = `'false'`

