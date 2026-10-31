<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
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
}
