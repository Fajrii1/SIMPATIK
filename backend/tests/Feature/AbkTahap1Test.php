<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\PeranPengguna;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkType;
use App\Services\WorkloadService;
use App\Support\PeranPenugasan;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Perbaikan teknis Tahap 1: konsistensi kalender kerja, batas norma,
 * penolakan periode tanpa hari kerja, bentrok, dan satuan dari master.
 */
class AbkTahap1Test extends TestCase
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

    public function test_calendar_meta_exposes_same_holiday_and_daily_hours_source(): void
    {
        Setting::setVal('jam_kerja_per_hari', '7');
        Setting::setVal('hari_libur', json_encode(['2026-11-03']));

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/calendar?start=2026-11-01&end=2026-11-30')
            ->assertOk()
            ->assertJsonPath('meta.hari_libur.0', '2026-11-03')
            ->assertJsonPath('meta.jam_kerja_per_hari', 7);
    }

    public function test_holiday_is_excluded_from_workdays_and_peak_concurrency(): void
    {
        Setting::setVal('hari_libur', json_encode(['2026-11-03']));

        $activity = $this->makeActivity('Libur Bersamaan', '2026-11-02', '2026-11-04');
        $workType = $this->makeWorkType('Libur 15 menit', 'dokumen', 15);
        $userA = $this->makeUser('Libur A');
        $userB = $this->makeUser('Libur B');

        $this->makeAssignment($activity, $userA, $workType, '2026-11-02', '2026-11-03');
        $this->makeAssignment($activity, $userB, $workType, '2026-11-03', '2026-11-04');

        // Senin dan Rabu efektif; Selasa 3 November adalah hari libur.
        $this->assertSame(2, $this->service->countWorkdays('2026-11-02', '2026-11-04'));

        $assignments = Assignment::with('activity')
            ->where('activity_id', $activity->id)
            ->get();

        $peak = $this->service->calculatePeakConcurrentAssignments(
            $assignments,
            Carbon::parse('2026-11-01')->startOfDay(),
            Carbon::parse('2026-11-30')->startOfDay()
        );

        $this->assertSame(1, $peak);
    }

    public function test_assignment_on_weekend_only_is_rejected(): void
    {
        $activity = $this->makeActivity('Akhir Pekan', '2026-11-07', '2026-11-08');
        $workType = $this->makeWorkType('Akhir Pekan 15 menit', 'dokumen', 15);
        $user = $this->makeUser('Akhir Pekan');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'volume' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mulai');
    }

    public function test_assignment_on_holiday_only_is_rejected(): void
    {
        Setting::setVal('hari_libur', json_encode(['2026-11-02']));

        $activity = $this->makeActivity('Hari Libur', '2026-11-02', '2026-11-02');
        $workType = $this->makeWorkType('Hari Libur 15 menit', 'dokumen', 15);
        $user = $this->makeUser('Hari Libur');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/activities/{$activity->id}/assignments", [
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'volume' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mulai');
    }

    public function test_update_assignment_to_weekend_only_is_rejected(): void
    {
        $activity = $this->makeActivity('Ubah Akhir Pekan', '2026-11-02', '2026-11-08');
        $workType = $this->makeWorkType('Ubah 15 menit', 'dokumen', 15);
        $user = $this->makeUser('Ubah Akhir Pekan');
        $assignment = $this->makeAssignment($activity, $user, $workType, '2026-11-02', '2026-11-06');

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/assignments/{$assignment->id}", [
            'mulai' => '2026-11-07',
            'selesai' => '2026-11-08',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mulai');
    }

    public function test_batch_activity_creation_rejects_assignment_without_workday(): void
    {
        $workType = $this->makeWorkType('Batch 15 menit', 'dokumen', 15);
        $user = $this->makeUser('Batch Akhir Pekan');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Batch Akhir Pekan',
            'jenis' => JenisKegiatan::SURVEI->value,
            'team_id' => $this->team->id,
            'mulai' => '2026-11-07',
            'selesai' => '2026-11-08',
            'assignments' => [
                [
                    'user_id' => $user->id,
                    'peran' => PeranPenugasan::PENANGGUNG_JAWAB,
                    'work_type_id' => $workType->id,
                    'volume' => 10,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assignments.0.mulai');
    }

    public function test_update_activity_to_weekend_only_is_rejected_when_assignment_follows_period(): void
    {
        $activity = $this->makeActivity('Ubah Kegiatan', '2026-11-02', '2026-11-06');
        $workType = $this->makeWorkType('Ubah Kegiatan 15 menit', 'dokumen', 15);
        $user = $this->makeUser('Ubah Kegiatan');

        // Penugasan tanpa tanggal sendiri mengikuti periode kegiatan.
        Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'norma_menit' => $workType->norma_menit,
            'total_menit' => 120,
            'volume' => 8,
            'satuan' => $workType->satuan,
            'jam_per_hari' => 2,
        ]);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/activities/{$activity->id}", [
            'mulai' => '2026-11-07',
            'selesai' => '2026-11-08',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mulai');
    }

    public function test_norma_validation_matches_database_capacity(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'nama' => 'Norma Melebihi Batas',
            'satuan' => 'dokumen',
            'norma_menit' => 65536,
            'jenis_norma' => 'estimasi',
        ];

        $this->postJson('/api/work-types', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('norma_menit');

        $payload['norma_menit'] = 65535;

        $this->postJson('/api/work-types', $payload)->assertStatus(201);
    }

    public function test_batch_activity_uses_custom_unit_from_work_type_master(): void
    {
        $workType = $this->makeWorkType('Survei Rumah Tangga', 'rumah tangga', 20);
        $user = $this->makeUser('Satuan Master');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Satuan Master',
            'jenis' => JenisKegiatan::SURVEI->value,
            'team_id' => $this->team->id,
            'mulai' => '2026-11-02',
            'selesai' => '2026-11-06',
            'assignments' => [
                [
                    'user_id' => $user->id,
                    'peran' => PeranPenugasan::PENANGGUNG_JAWAB,
                    'work_type_id' => $workType->id,
                    'volume' => 10,
                ],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('assignments', [
            'user_id' => $user->id,
            'work_type_id' => $workType->id,
            'satuan' => 'rumah tangga',
            'total_menit' => 200,
        ]);
    }

    public function test_preview_accepts_custom_unit_from_work_type_master(): void
    {
        $activity = $this->makeActivity('Pratinjau Satuan', '2026-11-02', '2026-11-06');
        $workType = $this->makeWorkType('Pratinjau Rumah Tangga', 'rumah tangga', 20);
        $user = $this->makeUser('Pratinjau Satuan');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/assignments/preview', [
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'volume' => 10,
            'satuan' => 'rumah tangga',
        ])->assertOk();

        $this->postJson('/api/assignments/preview-batch', [
            'team_id' => $this->team->id,
            'mulai' => '2026-11-02',
            'selesai' => '2026-11-06',
            'assignments' => [
                [
                    'user_id' => $user->id,
                    'peran' => 'Pelaksana',
                    'work_type_id' => $workType->id,
                    'volume' => 10,
                    'satuan' => 'rumah tangga',
                ],
            ],
        ])->assertOk();
    }

    public function test_holiday_reduces_workdays_without_changing_total_hours(): void
    {
        Setting::setVal('hari_libur', json_encode(['2026-11-03']));

        $activity = $this->makeActivity('Total Tetap', '2026-11-02', '2026-11-06');
        $workType = $this->makeWorkType('Total Tetap 10 menit', 'dokumen', 10);
        $user = $this->makeUser('Total Tetap');

        Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'norma_menit' => 10,
            'total_menit' => 600,
            'volume' => 60,
            'satuan' => 'dokumen',
            'jam_per_hari' => 2.5,
        ]);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2026-11');

        $this->assertSame(20, $workload['hari_kerja']);
        $this->assertSame(4, $workload['rincian'][0]['hari_kerja']);
        $this->assertEqualsWithDelta(10.0, $workload['jam_terpakai'], 0.01);
        $this->assertEqualsWithDelta(2.5, $workload['beban_harian_maks'], 0.001);
        $this->assertEqualsWithDelta(2.5, $workload['rincian'][0]['jam_per_hari'], 0.001);
        $this->assertSame([], $workload['hari_melebihi_kapasitas']);
    }

    private function makeWorkType(string $nama, string $satuan, int $normaMenit): WorkType
    {
        return WorkType::create([
            'nama' => $nama,
            'satuan' => $satuan,
            'norma_menit' => $normaMenit,
            'jenis_norma' => 'estimasi',
            'sumber' => 'Uji Tahap 1',
            'aktif' => true,
        ]);
    }

    private function makeAssignment(
        Activity $activity,
        User $user,
        WorkType $workType,
        string $mulai,
        string $selesai
    ): Assignment {
        $totalMenit = 120;

        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pelaksana',
            'work_type_id' => $workType->id,
            'norma_menit' => $workType->norma_menit,
            'total_menit' => $totalMenit,
            'volume' => $totalMenit / $workType->norma_menit,
            'satuan' => $workType->satuan,
            'jam_per_hari' => 2,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }

    private function makeUser(string $label): User
    {
        $this->sequence++;

        $user = User::create([
            'nama' => "Uji Tahap 1 {$label} {$this->sequence}",
            'email' => 'uji_tahap1_'.$this->sequence.'@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '19990101202101'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
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
            'nama' => "Kegiatan Uji Tahap 1 {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }
}
