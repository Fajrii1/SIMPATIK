# Diagram Hubungan Entitas (ERD)
**Aplikasi Penjadwalan dan Monitoring Kegiatan Statistik — BPS Kota Palembang**  
**Sumber Spesifikasi:** `PRD.md` Bagian 5, 8, & 13  

---

## 1. Diagram ERD (Mermaid)

```mermaid
erDiagram
    users ||--o{ teams : "memimpin (ketua_id)"
    users ||--|{ team_members : "terdaftar sebagai"
    teams ||--|{ team_members : "memiliki anggota"
    teams ||--o{ activities : "bertanggung jawab atas"
    activities ||--o{ assignments : "terdiri dari"
    users ||--o{ assignments : "menerima penugasan"
    activities ||--o{ weekly_issues : "terkait (opsional)"
    teams ||--o{ weekly_issues : "mengalami kendala"
    users ||--o{ weekly_issues : "melaporkan (pelapor_id)"
    users ||--o{ weekly_issues : "menindaklanjuti (pic_id)"
    users ||--o{ notifications : "menerima notifikasi"
    users ||--o{ audit_logs : "melakukan mutasi (user_id)"

    users {
        bigint id PK
        varchar nama
        varchar email UK
        varchar password
        enum peran
        varchar nip
        varchar jabatan
        boolean aktif
        boolean must_change_password
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    teams {
        bigint id PK
        varchar nama
        bigint ketua_id FK
        boolean aktif
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    team_members {
        bigint id PK
        bigint team_id FK
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }

    activities {
        bigint id PK
        varchar nama
        varchar jenis
        bigint team_id FK
        date mulai
        date selesai
        decimal bobot
        boolean kegiatan_besar
        boolean selesai_manual
        datetime selesai_pada
        text keterangan
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    assignments {
        bigint id PK
        bigint activity_id FK
        bigint user_id FK
        varchar peran
        decimal volume
        varchar satuan
        date mulai
        date selesai
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    weekly_issues {
        bigint id PK
        bigint activity_id FK
        bigint team_id FK
        bigint pelapor_id FK
        date minggu_mulai
        text kendala
        text solusi
        text tindak_lanjut
        bigint pic_id FK
        date tenggat
        enum status
        text catatan_penutupan
        datetime ditutup_pada
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    notifications {
        bigint id PK
        bigint user_id FK
        varchar tipe
        varchar pesan
        varchar tautan
        datetime dibaca_pada
        timestamp created_at
        timestamp updated_at
    }

    audit_logs {
        bigint id PK
        bigint user_id FK
        varchar aksi
        varchar objek
        bigint objek_id
        json sebelum
        json sesudah
        timestamp created_at
        timestamp updated_at
    }

    settings {
        bigint id PK
        varchar kunci UK
        varchar nilai
        timestamp created_at
        timestamp updated_at
    }
```

---

## 2. Penjelasan Relasi dan Kardinalitas

### 2.1 `users` ke `teams` (Kepemimpinan Tim Kerja)
- **Kardinalitas**: `1` ke `0..N` (Satu pengguna dapat menjadi ketua dari nol atau beberapa tim kerja).
- **Foreign Key**: `teams.ketua_id` mereferensikan `users.id` (Nullable).
- **Karakter**: Opsional. Seorang pegawai atau pimpinan dapat diangkat sebagai ketua tim berdasarkan SK Tim Kerja. Ketua tim bukan peran sistem terpisah melainkan atribut kepemimpinan fungsional.
- **Aksi Hapus**: `ON DELETE SET NULL`. Jika user ketua tim terhapus secara fisik, tim tetap berdiri tanpa ketua sementara waktu.

### 2.2 `users` dan `teams` melalui `team_members` (Keanggotaan Tim)
- **Kardinalitas**: `N` ke `M` (Many-to-Many).
- **Pivot Table**: `team_members` menghubungkan `team_id` dan `user_id`.
- **Constraint**: `UNIQUE KEY (team_id, user_id)`.
- **Karakter**: Satu pegawai dapat tergabung dalam beberapa tim kerja statistik secara bersamaan.
- **Aksi Hapus**: `ON DELETE CASCADE`. Jika tim atau user dihapus fisik, rekaman keanggotaan pada tabel pivot otomatis terhapus.

### 2.3 `teams` ke `activities` (Kepemilikan Kegiatan)
- **Kardinalitas**: `1` ke `0..N` (Satu tim dapat memiliki banyak kegiatan statistik).
- **Foreign Key**: `activities.team_id` mereferensikan `teams.id` (Wajib / NOT NULL).
- **Aksi Hapus**: `ON DELETE RESTRICT`. Tim kerja tidak boleh dihapus jika masih menaungi kegiatan statistik aktif.

### 2.4 `activities` ke `assignments` (Rincian Penugasan Kegiatan)
- **Kardinalitas**: `1` ke `0..N` (Satu kegiatan terdiri atas banyak penugasan personil).
- **Foreign Key**: `assignments.activity_id` mereferensikan `activities.id` (Wajib / NOT NULL).
- **Aksi Hapus**: `ON DELETE CASCADE`. Jika kegiatan dihapus hard delete, seluruh penugasan di dalamnya ikut dihapus.

### 2.5 `users` ke `assignments` (Penerimaan Penugasan Pegawai)
- **Kardinalitas**: `1` ke `0..N` (Satu pegawai dapat menerima banyak penugasan di berbagai kegiatan).
- **Foreign Key**: `assignments.user_id` mereferensikan `users.id` (Wajib / NOT NULL).
- **Aksi Hapus**: `ON DELETE RESTRICT`. Akun pegawai yang memiliki riwayat penugasan tidak boleh dihapus secara permanen.

### 2.6 `activities` ke `weekly_issues` (Kendala Spesifik Kegiatan)
- **Kardinalitas**: `0..1` ke `0..N` (Kendala mingguan dapat terkait ke suatu kegiatan, atau berdiri sendiri).
- **Foreign Key**: `weekly_issues.activity_id` mereferensikan `activities.id` (Opsional / Nullable).
- **Aksi Hapus**: `ON DELETE SET NULL`. Jika kegiatan dihapus, kendala tetap ada dan beralih menjadi kendala umum tim.

### 2.7 `teams` ke `weekly_issues` (Lingkup Kendala Tim)
- **Kardinalitas**: `1` ke `0..N` (Setiap kendala wajib menjadi tanggung jawab salah satu tim kerja).
- **Foreign Key**: `weekly_issues.team_id` mereferensikan `teams.id` (Wajib / NOT NULL).
- **Aksi Hapus**: `ON DELETE RESTRICT`.

### 2.8 `users` ke `weekly_issues` (Pelapor dan PIC Kendala)
- **Pelapor (`pelapor_id`)**: `1` ke `0..N` (Wajib). `ON DELETE RESTRICT`.
- **PIC (`pic_id`)**: `0..1` ke `0..N` (Opsional). `ON DELETE SET NULL`. Jika pegawai yang ditunjuk sebagai PIC terhapus, kendala tetap tersimpan dan kolom PIC menjadi null.

### 2.9 `users` ke `notifications` (Notifikasi Pribadi)
- **Kardinalitas**: `1` ke `0..N`.
- **Foreign Key**: `notifications.user_id` mereferensikan `users.id` (Wajib).
- **Aksi Hapus**: `ON DELETE CASCADE`.

### 2.10 `users` ke `audit_logs` (Pencatatan Jejak Aktor)
- **Kardinalitas**: `0..1` ke `0..N`.
- **Foreign Key**: `audit_logs.user_id` mereferensikan `users.id` (Opsional / Nullable untuk mencatat aksi sistem).
- **Aksi Hapus**: `ON DELETE SET NULL`. Catatan audit wajib kekal dan tidak boleh hilang saat akun pengguna dihapus.

