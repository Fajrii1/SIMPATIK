<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use Tests\TestCase;

class DatabaseDataIntegrityTest extends TestCase
{
    /**
     * Memverifikasi keberadaan akun-akun dummy sesuai PRD Bagian 17.
     */
    public function test_users_seeder_data_integrity(): void
    {
        // 1 Admin
        $admin = User::where('email', 'admin@contoh.test')->first();
        $this->assertNotNull($admin);
        $this->assertEquals(PeranPengguna::ADMIN, $admin->peran);
        $this->assertTrue($admin->isAdmin());

        // 1 Kepala (Pimpinan & ketua tim)
        $kepala = User::where('email', 'kepala@contoh.test')->first();
        $this->assertNotNull($kepala);
        $this->assertEquals(PeranPengguna::PIMPINAN, $kepala->peran);
        $this->assertTrue($kepala->isPimpinan());

        // 5 Ketua Tim (Pegawai)
        for ($i = 1; $i <= 5; $i++) {
            $ketua = User::where('email', "ketua{$i}@contoh.test")->first();
            $this->assertNotNull($ketua);
            $this->assertEquals(PeranPengguna::PEGAWAI, $ketua->peran);
        }

        // 10 Pegawai
        for ($i = 1; $i <= 10; $i++) {
            $formattedIndex = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $pegawai = User::where('email', "pegawai{$formattedIndex}@contoh.test")->first();
            $this->assertNotNull($pegawai);
            $this->assertEquals(PeranPengguna::PEGAWAI, $pegawai->peran);
        }

        $this->assertGreaterThanOrEqual(17, User::count());
    }

    /**
     * Memverifikasi data tim kerja statistik sesuai PRD Bagian 17.
     */
    public function test_teams_seeder_data_integrity(): void
    {
        $expectedTeams = [
            'Sosial',
            'Produksi',
            'Distribusi',
            'NWAS',
            'IPDS',
            'Umum',
        ];

        foreach ($expectedTeams as $teamName) {
            $team = Team::where('nama', $teamName)->first();
            $this->assertNotNull($team, "Tim [{$teamName}] harus terdaftar.");
            $this->assertNotNull($team->ketua_id, "Tim [{$teamName}] harus memiliki ketua.");
            $this->assertGreaterThan(0, $team->members()->count(), "Tim [{$teamName}] harus memiliki anggota.");
        }

        // Kepala merangkap ketua Tim Umum
        $pelayanan = Team::where('nama', 'Umum')->first();
        $kepala = User::where('email', 'kepala@contoh.test')->first();
        $this->assertEquals($kepala->id, $pelayanan->ketua_id);
        $this->assertTrue($kepala->isKetuaOf($pelayanan));
    }

    /**
     * Memverifikasi skenario kegiatan statistik sesuai PRD Bagian 17.
     */
    public function test_activities_seeder_data_and_status_logic(): void
    {
        $activities = Activity::all();
        $this->assertGreaterThanOrEqual(14, $activities->count(), 'Jumlah kegiatan harus sekitar 14.');

        // Uji 3 kegiatan besar
        $kegiatanBesar = Activity::where('kegiatan_besar', true)->get();
        $this->assertGreaterThanOrEqual(3, $kegiatanBesar->count(), 'Harus ada minimal 3 kegiatan besar.');

        // Uji kegiatan terlambat otomatis (Sakernas)
        $actTerlambat = Activity::where('nama', 'like', '%Sakernas%')->first();
        $this->assertNotNull($actTerlambat);
        $this->assertEquals('Terlambat', $actTerlambat->status);

        // Uji kegiatan selesai manual (Daerah Dalam Angka)
        $actSelesai = Activity::where('nama', 'like', '%Daerah Dalam Angka%')->first();
        $this->assertNotNull($actSelesai);
        $this->assertTrue($actSelesai->selesai_manual);
        $this->assertEquals('Selesai', $actSelesai->status);

        // Uji kegiatan belum mulai (Kepuasan Konsumen)
        $actBelumMulai = Activity::where('nama', 'like', '%Kepuasan Konsumen%')->first();
        $this->assertNotNull($actBelumMulai);
        $this->assertEquals('Belum mulai', $actBelumMulai->status);
    }

    /**
     * Memverifikasi skenario penugasan pegawai sesuai PRD Bagian 8.1, 8.2, dan 17.
     */
    public function test_assignments_seeder_data_integrity(): void
    {
        $assignments = Assignment::all();
        $this->assertGreaterThanOrEqual(30, $assignments->count(), 'Jumlah penugasan harus sekitar 30.');

        // 1. Kasus uji wajib PRD 8.1 (Susenas: volume 60, bobot 1.5, Okt 1 - Nov 15)
        $p01 = User::where('email', 'pegawai01@contoh.test')->first();
        $susenasAsn = Assignment::where('user_id', $p01->id)
            ->whereHas('activity', fn ($q) => $q->where('nama', 'like', '%Susenas%'))
            ->where('volume', 60.00)
            ->first();

        $this->assertNotNull($susenasAsn);
        $this->assertEquals('2026-10-01', $susenasAsn->mulai->format('Y-m-d'));
        $this->assertEquals('2026-11-15', $susenasAsn->selesai->format('Y-m-d'));
        $this->assertEquals(1.50, $susenasAsn->activity->bobot);

        // 2. Pegawai 03 memiliki 3 penugasan beririsan di bulan Oktober (PRD 8.2)
        $p03 = User::where('email', 'pegawai03@contoh.test')->first();
        $p03Asn = Assignment::where('user_id', $p03->id)->get();
        $this->assertGreaterThanOrEqual(3, $p03Asn->count());

        // 3. Fallback tanggal penugasan kosong ke tanggal kegiatan
        $fallbackAsn = Assignment::whereNull('mulai')->whereNull('selesai')->first();
        $this->assertNotNull($fallbackAsn);
        $this->assertNotNull($fallbackAsn->getEffectiveMulai());
        $this->assertNotNull($fallbackAsn->getEffectiveSelesai());
        $this->assertEquals($fallbackAsn->activity->mulai->format('Y-m-d'), $fallbackAsn->getEffectiveMulai()->format('Y-m-d'));
    }

    /**
     * Memverifikasi skenario kendala mingguan sesuai PRD Bagian 8.5 dan 17.
     */
    public function test_weekly_issues_seeder_data_and_scopes(): void
    {
        $issues = WeeklyIssue::all();
        $this->assertGreaterThanOrEqual(12, $issues->count(), 'Jumlah kendala harus sekitar 12.');

        // Uji query kendala terbawa (Carried Over) dari minggu sebelumnya
        $carriedOver = WeeklyIssue::carriedOver('2026-10-26')->get();
        $this->assertNotEmpty($carriedOver, 'Harus ada kendala yang terbawa dari minggu sebelumnya.');

        // Uji kendala jatuh tempo (Overdue)
        $overdueIssue = WeeklyIssue::where('status', '!=', StatusKendala::SELESAI)
            ->whereNotNull('tenggat')
            ->where('tenggat', '<', '2026-10-28')
            ->first();
        $this->assertNotNull($overdueIssue);
        $this->assertTrue($overdueIssue->isOverdue());

        // Uji kendala selesai dengan catatan penutupan
        $resolvedIssue = WeeklyIssue::where('status', StatusKendala::SELESAI)->first();
        $this->assertNotNull($resolvedIssue);
        $this->assertNotNull($resolvedIssue->catatan_penutupan);
        $this->assertNotNull($resolvedIssue->ditutup_pada);
    }

    /**
     * Memverifikasi pengaturan sistem awal sesuai PRD Bagian 13.
     */
    public function test_settings_seeder_default_values(): void
    {
        $this->assertEquals('100', Setting::getVal('ambang_beban_bulanan'));
        $this->assertEquals('3', Setting::getVal('maks_penugasan_bersamaan'));
        $this->assertEquals('3', Setting::getVal('maks_kegiatan_besar_bersamaan'));
        $this->assertEquals('true', Setting::getVal('pegawai_boleh_isi_kendala'));
    }

    /**
     * Memverifikasi notifikasi dan audit log terisi.
     */
    public function test_notifications_and_audit_logs_exist(): void
    {
        $this->assertGreaterThan(0, Notification::count());
        $this->assertGreaterThan(0, AuditLog::count());

        $unreadNotif = Notification::unread()->first();
        $this->assertNotNull($unreadNotif);
        $this->assertFalse($unreadNotif->isRead());

        $auditLog = AuditLog::first();
        $this->assertNotNull($auditLog);
        $this->assertIsArray($auditLog->sesudah);
    }

    /**
     * Memverifikasi mekanisme Soft Delete pada 5 entitas yang ditentukan.
     */
    public function test_soft_deletes_functionality(): void
    {
        // 1. User soft delete
        $tempUser = User::factory()->create();
        $tempUser->delete();
        $this->assertSoftDeleted('users', ['id' => $tempUser->id]);
        $tempUser->forceDelete();

        // 2. Team soft delete
        $tempTeam = Team::create(['nama' => 'Tim Uji Hapus', 'aktif' => true]);
        $tempTeam->delete();
        $this->assertSoftDeleted('teams', ['id' => $tempTeam->id]);
        $tempTeam->forceDelete();

        // 3. Activity soft delete
        $tempAct = Activity::create([
            'nama' => 'Kegiatan Uji Hapus',
            'jenis' => 'Survei',
            'team_id' => Team::first()->id,
            'mulai' => '2026-10-01',
            'selesai' => '2026-10-10',
        ]);
        $tempAct->delete();
        $this->assertSoftDeleted('activities', ['id' => $tempAct->id]);
        $tempAct->forceDelete();

        // 4. Assignment soft delete
        $tempAsn = Assignment::create([
            'activity_id' => Activity::first()->id,
            'user_id' => User::first()->id,
            'peran' => 'Pencacah Uji',
            'volume' => 10,
            'satuan' => 'Dokumen',
        ]);
        $tempAsn->delete();
        $this->assertSoftDeleted('assignments', ['id' => $tempAsn->id]);
        $tempAsn->forceDelete();

        // 5. WeeklyIssue soft delete
        $tempIssue = WeeklyIssue::create([
            'team_id' => Team::first()->id,
            'pelapor_id' => User::first()->id,
            'minggu_mulai' => '2026-10-26',
            'kendala' => 'Kendala Uji Hapus',
        ]);
        $tempIssue->delete();
        $this->assertSoftDeleted('weekly_issues', ['id' => $tempIssue->id]);
        $tempIssue->forceDelete();
    }
}
