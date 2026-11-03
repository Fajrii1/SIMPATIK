<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemainingModulesTest extends TestCase
{
    protected User $admin;

    protected User $ketua;

    protected User $pegawai;

    protected Team $team;

    protected Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test_rm@contoh.test'],
            [
                'nama' => 'Admin Test RM',
                'nip' => '198801012010011991',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketua = User::firstOrCreate(
            ['email' => 'ketua_test_rm@contoh.test'],
            [
                'nama' => 'Ketua Test RM',
                'nip' => '198801012010011992',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawai = User::firstOrCreate(
            ['email' => 'pegawai_test_rm@contoh.test'],
            [
                'nama' => 'Pegawai Test RM',
                'nip' => '198801012010011993',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->team = Team::firstOrCreate(
            ['nama' => 'Tim Uji Modul Akhir'],
            [
                'keterangan' => 'Tim Pengujian Integrasi',
                'ketua_id' => $this->ketua->id,
            ]
        );

        $this->team->members()->syncWithoutDetaching([$this->ketua->id, $this->pegawai->id]);

        $this->activity = Activity::firstOrCreate(
            ['nama' => 'Kegiatan Pengujian Modul Akhir'],
            [
                'team_id' => $this->team->id,
                'jenis' => JenisKegiatan::SURVEI,
                'mulai' => Carbon::now()->startOfMonth()->toDateString(),
                'selesai' => Carbon::now()->endOfMonth()->toDateString(),
                'bobot' => 1.5,
                'keterangan' => 'Pengujian',
            ]
        );
    }

    public function test_dashboard_endpoint_returns_data_for_authenticated_users(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/dashboard');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'periode',
                    'beban_kerja_pribadi',
                    'statistik',
                    'notifikasi',
                ],
            ]);
    }

    public function test_workload_monitoring_endpoints_work(): void
    {
        Sanctum::actingAs($this->admin);
        $currentMonth = Carbon::now()->format('Y-m');

        // Test workload index
        $response = $this->getJson("/api/workload?month={$currentMonth}");
        $response->assertOk()
            ->assertJsonStructure([
                'bulan',
                'ambang_batas',
                'ringkasan',
                'data',
            ]);

        // Test workload user detail
        $detailRes = $this->getJson("/api/workload/users/{$this->pegawai->id}");
        $detailRes->assertOk()
            ->assertJsonStructure([
                'user',
                'ambang_batas',
                'tren_bulanan',
                'penugasan',
            ]);

        // Test workload warnings
        $warningsRes = $this->getJson("/api/workload/warnings?month={$currentMonth}");
        $warningsRes->assertOk()
            ->assertJsonStructure([
                'bulan',
                'total_peringatan',
                'data',
            ]);
    }

    public function test_weekly_issues_crud_and_status_transitions(): void
    {
        Sanctum::actingAs($this->ketua);
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        // 1. Create issue
        $res = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'activity_id' => $this->activity->id,
            'minggu_mulai' => $monday,
            'kendala' => 'Kuesioner rusak terkena air hujan di lapangan',
            'solusi' => 'Mencetak ulang formulir cadangan',
            'pic_id' => $this->pegawai->id,
            'tenggat' => Carbon::now()->addDays(3)->toDateString(),
        ]);

        $res->assertCreated();
        $issueId = $res->json('data.id');

        // 2. View issue
        Sanctum::actingAs($this->pegawai);
        $getRes = $this->getJson("/api/issues/{$issueId}");
        $getRes->assertOk()->assertJsonPath('data.kendala', 'Kuesioner rusak terkena air hujan di lapangan');

        // 3. Update status to diproses
        $statusRes = $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::DIPROSES->value,
            'tindak_lanjut' => 'Formulir sedang dicetak ulang di kantor',
        ]);
        $statusRes->assertOk()->assertJsonPath('data.status', 'diproses');

        // 4. Update status to selesai
        Sanctum::actingAs($this->ketua);
        $closeRes = $this->patchJson("/api/issues/{$issueId}/status", [
            'status' => StatusKendala::SELESAI->value,
            'catatan_penutupan' => 'Kuesioner pengganti sudah diserahkan ke pencacah',
        ]);
        $closeRes->assertOk()->assertJsonPath('data.status', 'selesai');

        // 5. Test carried issues endpoint
        Sanctum::actingAs($this->admin);
        $carriedRes = $this->getJson('/api/issues/carried');
        $carriedRes->assertOk();
    }

    public function test_pegawai_cannot_report_issue_for_unrelated_team_when_setting_allows(): void
    {
        $original = Setting::getVal('pegawai_boleh_isi_kendala', false);
        Setting::setVal('pegawai_boleh_isi_kendala', true);

        $outsideTeam = Team::firstOrCreate(
            ['nama' => 'Tim Luar Kendala Uji'],
            ['keterangan' => 'Tim pembanding kendala', 'ketua_id' => null]
        );

        try {
            Sanctum::actingAs($this->pegawai);
            $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

            // Tim sendiri tetap boleh
            $allowed = $this->postJson('/api/issues', [
                'team_id' => $this->team->id,
                'minggu_mulai' => $monday,
                'kendala' => 'Kendala tim sendiri saat pengaturan mengizinkan.',
            ]);
            $allowed->assertCreated();
            WeeklyIssue::find($allowed->json('data.id'))?->forceDelete();

            // Tim lain harus ditolak walau pengaturan mengizinkan pegawai mengisi kendala
            $this->postJson('/api/issues', [
                'team_id' => $outsideTeam->id,
                'minggu_mulai' => $monday,
                'kendala' => 'Percobaan kendala tim lain.',
            ])->assertForbidden();
        } finally {
            Setting::setVal('pegawai_boleh_isi_kendala', $original);
        }
    }

    public function test_notifications_endpoints_work(): void
    {
        Notification::where('user_id', $this->pegawai->id)->delete();

        $notif = Notification::create([
            'user_id' => $this->pegawai->id,
            'tipe' => 'info',
            'pesan' => 'Jadwal survei diperbarui',
            'tautan' => '/activities',
        ]);

        Sanctum::actingAs($this->pegawai);
        $res = $this->getJson('/api/notifications');
        $res->assertOk();

        $unreadCount = $this->getJson('/api/notifications/unread-count');
        $unreadCount->assertOk()->assertJsonPath('unread_count', 1);

        $readRes = $this->patchJson("/api/notifications/{$notif->id}/read");
        $readRes->assertOk();

        $allReadRes = $this->postJson('/api/notifications/read-all');
        $allReadRes->assertOk();
    }

    public function test_audit_logs_accessible_only_by_admin(): void
    {
        // Admin can view
        Sanctum::actingAs($this->admin);
        $adminRes = $this->getJson('/api/audit-logs');
        $adminRes->assertOk();

        // Pegawai is forbidden
        Sanctum::actingAs($this->pegawai);
        $pegawaiRes = $this->getJson('/api/audit-logs');
        $pegawaiRes->assertForbidden();
    }

    public function test_reports_workload_and_activities(): void
    {
        Sanctum::actingAs($this->admin);
        $wlRes = $this->getJson('/api/reports/workload?format=csv');
        $wlRes->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $actRes = $this->getJson('/api/reports/activities?format=csv');
        $actRes->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_activities_report_enforces_role_based_export_rules(): void
    {
        // Pegawai biasa tanpa tim yang dipimpin -> 403
        Sanctum::actingAs($this->pegawai);
        $this->getJson('/api/reports/activities?format=csv')->assertForbidden();

        // Ketua tim hanya boleh mengekspor timnya sendiri
        Sanctum::actingAs($this->ketua);
        $this->getJson('/api/reports/activities?format=csv')->assertOk();
        $this->getJson("/api/reports/activities?format=csv&team_id={$this->team->id}")->assertOk();

        $otherTeam = Team::firstOrCreate(
            ['nama' => 'Tim Lain Ekspor Uji'],
            ['keterangan' => 'Tim pembanding', 'ketua_id' => null]
        );

        $this->getJson("/api/reports/activities?format=csv&team_id={$otherTeam->id}")->assertForbidden();

        // Pimpinan memiliki akses seluruh kantor
        $pimpinan = User::firstOrCreate(
            ['email' => 'pimpinan_export_rm@contoh.test'],
            [
                'nama' => 'Pimpinan Export RM',
                'nip' => '198801012010011994',
                'jabatan' => 'Kepala BPS',
                'password' => 'password',
                'peran' => PeranPengguna::PIMPINAN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        Sanctum::actingAs($pimpinan);
        $this->getJson('/api/reports/activities?format=csv')->assertOk();
        $this->getJson("/api/reports/activities?format=csv&team_id={$otherTeam->id}")->assertOk();
    }

    /**
     * Uji catatan penutupan wajib diisi saat kendala berstatus selesai.
     */
    public function test_closing_issue_requires_closure_note(): void
    {
        Sanctum::actingAs($this->ketua);
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $created = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => $monday,
            'kendala' => 'Kendala untuk uji catatan penutupan wajib.',
        ]);
        $created->assertCreated();
        $issueId = $created->json('data.id');

        try {
            $this->patchJson("/api/issues/{$issueId}/status", [
                'status' => StatusKendala::SELESAI->value,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('catatan_penutupan');

            $this->patchJson("/api/issues/{$issueId}/status", [
                'status' => StatusKendala::SELESAI->value,
                'catatan_penutupan' => 'Kendala sudah ditangani dan diverifikasi.',
            ])->assertOk()->assertJsonPath('data.status', 'selesai');
        } finally {
            WeeklyIssue::find($issueId)?->forceDelete();
        }
    }

    /**
     * Uji pegawai anggota tim dapat melaporkan kendala saat pengaturan bawaan aktif.
     */
    public function test_team_member_can_report_issue_with_default_setting(): void
    {
        $this->assertTrue(
            filter_var(Setting::getVal('pegawai_boleh_isi_kendala', true), FILTER_VALIDATE_BOOLEAN)
        );

        Sanctum::actingAs($this->pegawai);
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $response = $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => $monday,
            'kendala' => 'Kendala dilaporkan anggota tim saat pengaturan bawaan aktif.',
        ]);

        $response->assertCreated();
        WeeklyIssue::find($response->json('data.id'))?->forceDelete();
    }

    /**
     * Uji pimpinan yang bukan anggota maupun ketua tim tidak boleh melaporkan kendala.
     */
    public function test_non_member_pimpinan_cannot_report_issue(): void
    {
        $pimpinan = User::firstOrCreate(
            ['email' => 'pimpinan_kendala_rm@contoh.test'],
            [
                'nama' => 'Pimpinan Kendala RM',
                'nip' => '198801012010011995',
                'jabatan' => 'Kepala BPS',
                'password' => 'password',
                'peran' => PeranPengguna::PIMPINAN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        Sanctum::actingAs($pimpinan);
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->postJson('/api/issues', [
            'team_id' => $this->team->id,
            'minggu_mulai' => $monday,
            'kendala' => 'Percobaan kendala oleh pimpinan nonanggota.',
        ])->assertForbidden();
    }

    /**
     * Uji ketua tim hanya melihat beban kerja anggota tim yang dipimpinnya.
     */
    public function test_ketua_workload_only_covers_led_team_members(): void
    {
        $otherTeam = Team::firstOrCreate(
            ['nama' => 'Tim Luar Beban Ketua Uji'],
            ['keterangan' => 'Tim pembanding beban kerja', 'ketua_id' => null]
        );

        $outsideUser = User::firstOrCreate(
            ['email' => 'pegawai_luar_ketua_rm@contoh.test'],
            [
                'nama' => 'Pegawai Luar Ketua RM',
                'nip' => '198801012010011996',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );
        $otherTeam->members()->syncWithoutDetaching([$outsideUser->id]);

        Sanctum::actingAs($this->ketua);
        $currentMonth = Carbon::now()->format('Y-m');

        $response = $this->getJson("/api/workload?month={$currentMonth}");
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('user.id')->all();
        $this->assertContains($this->ketua->id, $ids);
        $this->assertContains($this->pegawai->id, $ids);
        $this->assertNotContains($outsideUser->id, $ids);

        // Meminta tim lain tetap dibatasi ke tim yang dipimpin.
        $filtered = $this->getJson("/api/workload?month={$currentMonth}&team_id={$otherTeam->id}");
        $filtered->assertOk();
        $filteredIds = collect($filtered->json('data'))->pluck('user.id')->all();
        $this->assertNotContains($outsideUser->id, $filteredIds);

        $this->getJson("/api/workload/users/{$outsideUser->id}")->assertForbidden();
    }
}
