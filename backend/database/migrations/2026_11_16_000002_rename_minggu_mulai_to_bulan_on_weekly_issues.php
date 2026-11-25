<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mengubah kendala mingguan menjadi kendala bulanan.
     *
     * Nama tabel dan kelas WeeklyIssue sengaja dipertahankan agar perubahan aman;
     * yang berubah hanya nama kolom periode dan nilai tanggalnya.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE weekly_issues CHANGE minggu_mulai bulan DATE NOT NULL');

        // Isi ulang semua nilai lama dengan tanggal 1 dari bulan yang sama.
        DB::statement("UPDATE weekly_issues SET bulan = DATE_FORMAT(bulan, '%Y-%m-01')");

        // Indeks baru dibuat lebih dulu agar indeks lama tetap menopang foreign key
        // team_id sampai penggantinya tersedia.
        DB::statement('CREATE INDEX idx_weekly_issues_team_month_status ON weekly_issues (team_id, bulan, status)');

        DB::statement('DROP INDEX idx_weekly_issues_team_week_status ON weekly_issues');
    }

    /**
     * Mengembalikan nama kolom dan nilai tanggal ke bentuk mingguan.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE weekly_issues CHANGE bulan minggu_mulai DATE NOT NULL');

        // Kembalikan ke hari Senin pada minggu yang memuat tanggal tersebut.
        DB::statement('UPDATE weekly_issues SET minggu_mulai = DATE_SUB(DATE(minggu_mulai), INTERVAL WEEKDAY(minggu_mulai) DAY)');

        DB::statement('CREATE INDEX idx_weekly_issues_team_week_status ON weekly_issues (team_id, minggu_mulai, status)');

        DB::statement('DROP INDEX idx_weekly_issues_team_month_status ON weekly_issues');
    }
};
