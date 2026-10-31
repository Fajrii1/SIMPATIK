<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    protected string $connection = 'mysql';

    /**
     * Memverifikasi keberadaan kesembilan tabel utama PRD di database jadwal_bps.
     */
    public function test_all_nine_domain_tables_exist(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedTables = [
            'users',
            'teams',
            'team_members',
            'activities',
            'assignments',
            'weekly_issues',
            'notifications',
            'audit_logs',
            'settings',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                $schema->hasTable($table),
                "Tabel [{$table}] harus ada pada skema database."
            );
        }
    }

    /**
     * Memverifikasi struktur kolom tabel users sesuai PRD Bagian 13.
     */
    public function test_users_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = [
            'id', 'nama', 'email', 'password', 'peran', 'nip', 'jabatan',
            'aktif', 'must_change_password', 'remember_token',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('users', $col),
                "Kolom [{$col}] pada tabel users harus ada."
            );
        }
    }

    /**
     * Memverifikasi struktur kolom tabel teams sesuai PRD Bagian 13.
     */
    public function test_teams_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = [
            'id', 'nama', 'ketua_id', 'aktif',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('teams', $col),
                "Kolom [{$col}] pada tabel teams harus ada."
            );
        }
    }

    /**
     * Memverifikasi struktur kolom tabel team_members sesuai PRD Bagian 13.
     */
    public function test_team_members_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = ['id', 'team_id', 'user_id', 'created_at', 'updated_at'];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('team_members', $col),
                "Kolom [{$col}] pada tabel team_members harus ada."
            );
        }

        // Pastikan TIDAK memiliki deleted_at
        $this->assertFalse(
            $schema->hasColumn('team_members', 'deleted_at'),
            'Tabel team_members TIDAK boleh memiliki kolom deleted_at.'
        );
    }

    /**
     * Memverifikasi struktur kolom tabel activities sesuai PRD Bagian 13.
     */
    public function test_activities_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = [
            'id', 'nama', 'jenis', 'team_id', 'mulai', 'selesai',
            'bobot', 'kegiatan_besar', 'selesai_manual', 'selesai_pada',
            'keterangan', 'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('activities', $col),
                "Kolom [{$col}] pada tabel activities harus ada."
            );
        }

        // Pastikan TIDAK memiliki kolom status permanen (PRD 8.3)
        $this->assertFalse(
            $schema->hasColumn('activities', 'status'),
            'Tabel activities TIDAK boleh menyimpan kolom status statis.'
        );
    }

    /**
     * Memverifikasi struktur kolom tabel assignments sesuai PRD Bagian 13.
     */
    public function test_assignments_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = [
            'id', 'activity_id', 'user_id', 'peran', 'volume', 'satuan',
            'mulai', 'selesai', 'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('assignments', $col),
                "Kolom [{$col}] pada tabel assignments harus ada."
            );
        }

        // Pastikan TIDAK menyimpan poin atau beban terhitung (PRD 8.1)
        $this->assertFalse(
            $schema->hasColumn('assignments', 'poin'),
            'Tabel assignments TIDAK boleh menyimpan kolom poin terhitung.'
        );
        $this->assertFalse(
            $schema->hasColumn('assignments', 'beban'),
            'Tabel assignments TIDAK boleh menyimpan kolom beban terhitung.'
        );
    }

    /**
     * Memverifikasi struktur kolom tabel weekly_issues sesuai PRD Bagian 13.
     */
    public function test_weekly_issues_table_has_expected_columns(): void
    {
        $schema = Schema::connection($this->connection);

        $expectedColumns = [
            'id', 'activity_id', 'team_id', 'pelapor_id', 'minggu_mulai',
            'kendala', 'solusi', 'tindak_lanjut', 'pic_id', 'tenggat',
            'status', 'catatan_penutupan', 'ditutup_pada',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $schema->hasColumn('weekly_issues', $col),
                "Kolom [{$col}] pada tabel weekly_issues harus ada."
            );
        }
    }

    /**
     * Memverifikasi struktur kolom notifications, audit_logs, settings (Non-SD).
     */
    public function test_non_soft_delete_tables_do_not_have_deleted_at(): void
    {
        $schema = Schema::connection($this->connection);

        $nonSdTables = ['notifications', 'audit_logs', 'settings'];

        foreach ($nonSdTables as $table) {
            $this->assertFalse(
                $schema->hasColumn($table, 'deleted_at'),
                "Tabel [{$table}] TIDAK boleh memiliki kolom deleted_at."
            );
        }
    }

    /**
     * Memverifikasi indeks gabungan yang diwajibkan PRD Bagian 13.
     */
    public function test_mandatory_composite_indexes_exist(): void
    {
        $indexes = [
            'activities' => 'idx_activities_team_timeline',
            'assignments' => 'idx_assignments_user_timeline',
            'weekly_issues' => 'idx_weekly_issues_team_week_status',
            'notifications' => 'idx_notifications_user_read',
            'audit_logs' => 'idx_audit_logs_object',
        ];

        foreach ($indexes as $table => $indexName) {
            $rawIndexes = DB::connection($this->connection)->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            $this->assertNotEmpty(
                $rawIndexes,
                "Indeks [{$indexName}] pada tabel [{$table}] harus ada."
            );
        }
    }

    /**
     * Memverifikasi constraint unik pada users.email, team_members(team_id, user_id), dan settings.kunci.
     */
    public function test_unique_constraints_exist(): void
    {
        // users.email unique
        $emailIdx = DB::connection($this->connection)->select("SHOW INDEX FROM `users` WHERE Column_name = 'email' AND Non_unique = 0");
        $this->assertNotEmpty($emailIdx, 'Kolom email pada tabel users harus unik.');

        // team_members(team_id, user_id) unique
        $teamMemberIdx = DB::connection($this->connection)->select("SHOW INDEX FROM `team_members` WHERE Key_name = 'unique_team_user'");
        $this->assertNotEmpty($teamMemberIdx, 'Kombinasi (team_id, user_id) pada tabel team_members harus unik.');

        // settings.kunci unique
        $settingIdx = DB::connection($this->connection)->select("SHOW INDEX FROM `settings` WHERE Column_name = 'kunci' AND Non_unique = 0");
        $this->assertNotEmpty($settingIdx, 'Kolom kunci pada tabel settings harus unik.');
    }
}
