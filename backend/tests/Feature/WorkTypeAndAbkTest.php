<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkType;
use App\Services\WorkloadService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak metode ABK: beban = volume x norma waktu, disebar rata ke hari kerja.
 */
class WorkTypeAndAbkTest extends TestCase
{
    protected WorkloadService $service;

    protected User $admin;

    protected Team $team;

    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkloadService::class);
        $this->admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        $this->team = Team::where('nama', 'Sosial')->firstOrFail();
    }

    public function test_work_type_examples_are_seeded_and_assignment_stores_norma_snapshot(): void
    {
        $this->assertDatabaseCount('work_types', 4);
        $this->assertDatabaseHas('work_types', [
            'nama' => 'Pemeriksaan dokumen',
            'satuan' => 'dokumen',
            'norma_menit' => 15,
            'jenis_norma' => 'estimasi',
        ]);

        $workType = WorkType::where('nama', 'Pemeriksaan dokumen')->firstOrFail();
        $user = $this->makeUser('Snapshot');
        $activity = $this->makeActivity('Snapshot', '2026-11-02', '2026-11-30');

        Sanctum::actingAs($this->admin);
        $response = $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $user->id,
            'peran' => 'Pemeriksa',
            'work_type_id' => $workType->id,
            'volume' => 32,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.work_type_id', $workType->id)
            ->assertJsonPath('data.norma_menit', 15)
            ->assertJsonPath('data.total_menit', 480)
            ->assertJsonPath('data.total_jam', 8)
            ->assertJsonPath('data.satuan', 'dokumen');

        $assignment = Assignment::findOrFail($response->json('data.id'));
        $this->assertSame(480, $assignment->total_menit);
        $this->assertSame(15, $assignment->norma_menit);

        // Perubahan norma master tidak boleh mengubah snapshot penugasan lama.
        $workType->update(['norma_menit' => 30]);

        $assignment->refresh();
        $this->assertSame(15, $assignment->norma_menit);
        $this->assertSame(480, $assignment->total_menit);
    }

    public function test_volume_is_required_for_new_assignment_with_work_type(): void
    {
        $workType = WorkType::firstOrFail();
        $user = $this->makeUser('Tanpa Volume');
        $activity = $this->makeActivity('Tanpa Volume', '2026-11-02', '2026-11-30');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $user->id,
            'peran' => 'Pemeriksa',
            'work_type_id' => $workType->id,
        ])->assertStatus(422)->assertJsonValidationErrors('volume');
    }

    public function test_november_total_47_hours_is_31_97_percent(): void
    {
        $user = $this->makeUser('Nov 47');
        $activity = $this->makeActivity('Nov 47', '2026-11-01', '2026-11-30');
        $workType = $this->makeWorkType('Norma 60 menit', 60);

        $this->makeAbkAssignment($activity, $user, $workType, 47, 2820);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2026-11');

        $this->assertSame(21, $workload['hari_kerja']);
        $this->assertSame(147.0, $workload['kapasitas_jam']);
        $this->assertSame(47.0, $workload['jam_terpakai']);
        $this->assertEqualsWithDelta(31.97, $workload['persentase'], 0.01);
    }

    public function test_daily_five_hours_then_added_work_makes_nine_hours_and_triggers_warning(): void
    {
        $user = $this->makeUser('Harian 9 Jam');
        $norma60 = $this->makeWorkType('Harian 60 menit', 60);
        $norma15 = $this->makeWorkType('Harian 15 menit', 15);

        $baseActivity = $this->makeActivity('Harian Dasar', '2026-11-09', '2026-11-13');
        $this->makeAbkAssignment($baseActivity, $user, $norma60, 25, 1500);

        $before = $this->service->calculateMonthlyWorkloadForUser($user, '2026-11');
        $this->assertEqualsWithDelta(5.0, $before['beban_harian_maks'], 0.001);
        $this->assertSame([], $before['hari_melebihi_kapasitas']);

        // 32 x 15 menit = 480 menit = 8 jam pada 9-10 November (2 hari kerja) = 4 jam/hari.
        $extraActivity = $this->makeActivity('Harian Tambahan', '2026-11-09', '2026-11-10');
        $this->makeAbkAssignment($extraActivity, $user, $norma15, 32, 480);

        $after = $this->service->calculateMonthlyWorkloadForUser($user, '2026-11');

        $this->assertEqualsWithDelta(9.0, $after['beban_harian_maks'], 0.001);
        $this->assertCount(2, $after['hari_melebihi_kapasitas']);
        $this->assertEqualsWithDelta(128.57, round(9 / 7 * 100, 2), 0.01);

        $warnings = $this->service->getMonthlyWarnings('2026-11');
        $dailyWarnings = array_values(array_filter(
            $warnings,
            fn (array $warning) => $warning['jenis'] === 'melebihi_kapasitas_harian'
                && (int) $warning['pegawai_id'] === $user->id
        ));

        $this->assertNotEmpty($dailyWarnings);
        $this->assertEqualsWithDelta(9.0, (float) $dailyWarnings[0]['detail']['hari'][0]['jam'], 0.001);
    }

    public function test_laporan_99_times_120_minutes_is_42_86_percent_each_month(): void
    {
        $user = $this->makeUser('Laporan Triwulan');
        $activity = $this->makeActivity('Laporan Triwulan', '2026-10-01', '2026-12-31');
        $workType = WorkType::where('nama', 'Penyusunan laporan')->firstOrFail();

        $this->makeAbkAssignment($activity, $user, $workType, 99, 11880);

        // 99 x 120 menit = 198 jam, dibagi rata ke 66 hari kerja = 3 jam/hari.
        foreach (['2026-10' => 22, '2026-11' => 21, '2026-12' => 23] as $month => $workdays) {
            $workload = $this->service->calculateMonthlyWorkloadForUser($user, $month);

            $this->assertSame($workdays, $workload['hari_kerja']);
            $this->assertEqualsWithDelta(3.0 * $workdays, $workload['jam_terpakai'], 0.01);
            $this->assertEqualsWithDelta(42.86, $workload['persentase'], 0.01);
        }
    }

    public function test_only_admin_can_manage_work_types_but_ketua_can_read_options(): void
    {
        $ketua = User::where('email', 'ketua1@contoh.test')->firstOrFail();
        $pimpinan = User::where('email', 'kepala@contoh.test')->firstOrFail();
        $pegawai = User::where('email', 'pegawai02@contoh.test')->firstOrFail();

        $payload = [
            'nama' => 'Jenis Uji Otorisasi',
            'satuan' => 'berkas',
            'norma_menit' => 20,
            'jenis_norma' => 'estimasi',
        ];

        Sanctum::actingAs($ketua);
        $this->postJson('/api/work-types', $payload)->assertStatus(403);
        $this->getJson('/api/work-types/options')->assertOk();

        Sanctum::actingAs($pimpinan);
        $this->getJson('/api/work-types/options')->assertStatus(403);

        Sanctum::actingAs($pegawai);
        $this->getJson('/api/work-types/options')->assertStatus(403);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/work-types', $payload)->assertStatus(201);
        $this->getJson('/api/work-types?per_page=50')->assertOk();
    }

    public function test_work_type_used_by_assignment_cannot_be_deleted(): void
    {
        $workType = $this->makeWorkType('Jenis Terpakai', 30);
        $user = $this->makeUser('Terpakai');
        $activity = $this->makeActivity('Terpakai', '2026-11-02', '2026-11-06');

        $this->makeAbkAssignment($activity, $user, $workType, 10, 300);

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/work-types/{$workType->id}")->assertStatus(422);

        $unused = $this->makeWorkType('Jenis Bebas', 30);
        $this->deleteJson("/api/work-types/{$unused->id}")->assertOk();
        $this->assertDatabaseMissing('work_types', ['id' => $unused->id]);
    }

    private function makeWorkType(string $nama, int $normaMenit): WorkType
    {
        return WorkType::create([
            'nama' => $nama,
            'satuan' => 'unit',
            'norma_menit' => $normaMenit,
            'jenis_norma' => 'estimasi',
            'sumber' => 'Uji',
            'aktif' => true,
        ]);
    }

    private function makeAbkAssignment(
        Activity $activity,
        User $user,
        WorkType $workType,
        float $volume,
        int $totalMenit
    ): Assignment {
        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'norma_menit' => $workType->norma_menit,
            'total_menit' => $totalMenit,
            'volume' => $volume,
            'satuan' => $workType->satuan,
        ]);
    }

    private function makeUser(string $label): User
    {
        $this->sequence++;

        $user = User::create([
            'nama' => "Uji ABK {$label} {$this->sequence}",
            'email' => 'uji_abk_'.$this->sequence.'@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '19990101202001'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $this->team->members()->syncWithoutDetaching([$user->id]);

        return $user;
    }

    private function makeActivity(string $label, string $mulai, string $selesai): Activity
    {
        return Activity::create([
            'nama' => "Kegiatan Uji ABK {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }
}
