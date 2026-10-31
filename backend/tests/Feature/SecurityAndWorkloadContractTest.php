<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Enums\SatuanPenugasan;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAndWorkloadContractTest extends TestCase
{
    protected User $admin;
    protected User $ketuaTimA;
    protected User $pegawaiA;
    protected User $pegawaiB;
    protected User $nonaktifUser;
    protected Team $teamA;
    protected Team $teamB;
    protected Activity $activityA;
    protected Activity $activityB;
    protected Assignment $assignmentPegawaiA;
    protected Assignment $assignmentPegawaiB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_sec@contoh.test'],
            [
                'nama' => 'Admin Security',
                'nip' => '198101012005011991',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketuaTimA = User::firstOrCreate(
            ['email' => 'ketua_tim_a@contoh.test'],
            [
                'nama' => 'Ketua Tim A',
                'nip' => '198101012005011992',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiA = User::firstOrCreate(
            ['email' => 'pegawai_a@contoh.test'],
            [
                'nama' => 'Pegawai A',
                'nip' => '198101012005011993',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiB = User::firstOrCreate(
            ['email' => 'pegawai_b@contoh.test'],
            [
                'nama' => 'Pegawai B',
                'nip' => '198101012005011994',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->nonaktifUser = User::firstOrCreate(
            ['email' => 'nonaktif_user@contoh.test'],
            [
                'nama' => 'User Nonaktif',
                'nip' => '198101012005011995',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => false,
                'must_change_password' => false,
            ]
        );

        $this->teamA = Team::firstOrCreate(
            ['nama' => 'Tim Pengujian Security A'],
            ['ketua_id' => $this->ketuaTimA->id]
        );
        $this->teamA->members()->syncWithoutDetaching([$this->ketuaTimA->id, $this->pegawaiA->id]);

        $this->teamB = Team::firstOrCreate(
            ['nama' => 'Tim Pengujian Security B'],
            ['ketua_id' => $this->admin->id]
        );
        $this->teamB->members()->syncWithoutDetaching([$this->pegawaiB->id]);

        $this->activityA = Activity::firstOrCreate(
            ['nama' => 'Kegiatan Pengujian A'],
            [
                'team_id' => $this->teamA->id,
                'jenis' => JenisKegiatan::SURVEI,
                'mulai' => Carbon::now()->startOfMonth()->toDateString(),
                'selesai' => Carbon::now()->endOfMonth()->toDateString(),
                'bobot' => 1.5,
            ]
        );

        $this->activityB = Activity::firstOrCreate(
            ['nama' => 'Kegiatan Pengujian B'],
            [
                'team_id' => $this->teamB->id,
                'jenis' => JenisKegiatan::SURVEI,
                'mulai' => Carbon::now()->startOfMonth()->toDateString(),
                'selesai' => Carbon::now()->endOfMonth()->toDateString(),
                'bobot' => 1.0,
            ]
        );

        $this->assignmentPegawaiA = Assignment::firstOrCreate(
            [
                'activity_id' => $this->activityA->id,
                'user_id' => $this->pegawaiA->id,
            ],
            [
                'peran' => 'Pencacah',
                'volume' => 30,
                'satuan' => SatuanPenugasan::DOKUMEN,
                'mulai' => $this->activityA->mulai,
                'selesai' => $this->activityA->selesai,
            ]
        );

        $this->assignmentPegawaiB = Assignment::firstOrCreate(
            [
                'activity_id' => $this->activityB->id,
                'user_id' => $this->pegawaiB->id,
            ],
            [
                'peran' => 'Pengawas',
                'volume' => 20,
                'satuan' => SatuanPenugasan::DOKUMEN,
                'mulai' => $this->activityB->mulai,
                'selesai' => $this->activityB->selesai,
            ]
        );
    }

    /**
     * Pegawai A tidak boleh melihat detail penugasan Pegawai B.
     */
    public function test_pegawai_cannot_view_other_pegawai_assignment_detail(): void
    {
        Sanctum::actingAs($this->pegawaiA);

        // Pegawai A melihat penugasannya sendiri -> BOLEH
        $resA = $this->getJson("/api/assignments/{$this->assignmentPegawaiA->id}");
        $resA->assertOk();

        // Pegawai A melihat penugasan Pegawai B -> DITOLAK (403)
        $resB = $this->getJson("/api/assignments/{$this->assignmentPegawaiB->id}");
        $resB->assertForbidden();
    }

    /**
     * Ketua Tim A hanya boleh melihat penugasan tim yang dipimpinnya.
     */
    public function test_ketua_tim_can_only_view_led_team_assignments(): void
    {
        Sanctum::actingAs($this->ketuaTimA);

        // Penugasan anggota tim A -> BOLEH
        $resA = $this->getJson("/api/assignments/{$this->assignmentPegawaiA->id}");
        $resA->assertOk();

        // Penugasan anggota tim B -> DITOLAK (403)
        $resB = $this->getJson("/api/assignments/{$this->assignmentPegawaiB->id}");
        $resB->assertForbidden();
    }

    /**
     * Admin boleh melihat penugasan siapapun.
     */
    public function test_admin_can_view_any_assignment(): void
    {
        Sanctum::actingAs($this->admin);

        $resA = $this->getJson("/api/assignments/{$this->assignmentPegawaiA->id}");
        $resA->assertOk();

        $resB = $this->getJson("/api/assignments/{$this->assignmentPegawaiB->id}");
        $resB->assertOk();
    }

    /**
     * Pengguna nonaktif ditolak dari detail penugasan.
     */
    public function test_inactive_user_cannot_access_assignments(): void
    {
        Sanctum::actingAs($this->nonaktifUser);

        $res = $this->getJson("/api/assignments/{$this->assignmentPegawaiA->id}");
        $res->assertForbidden();
    }

    /**
     * Kontrak response peringatan beban kerja konsisten dan lengkap.
     */
    public function test_workload_warnings_contract_and_structure(): void
    {
        Sanctum::actingAs($this->admin);
        $currentMonth = Carbon::now()->format('Y-m');

        $res = $this->getJson("/api/workload/warnings?month={$currentMonth}");
        $res->assertOk();
        $res->assertJsonStructure([
            'bulan',
            'total_peringatan',
            'data' => [
                '*' => [
                    'jenis',
                    'tipe',
                    'tingkat_risiko',
                    'pegawai_id',
                    'pegawai_nama',
                    'user',
                    'bulan',
                    'pesan',
                ],
            ],
        ]);
    }
}

