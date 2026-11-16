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
use App\Services\WorkloadService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak Fase 6: form kegiatan (PJ wajib), pratinjau batch, dan pembatasan metrik penugasan.
 */
class ActivityFormAndVisibilityTest extends TestCase
{
    protected User $admin;

    protected User $pimpinan;

    protected User $ketuaA;

    protected User $ketuaB;

    protected User $pegawaiA;

    protected User $pegawaiB;

    protected Team $teamA;

    protected Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_fase6@contoh.test'],
            [
                'nama' => 'Admin Fase 6',
                'nip' => '198701012010011301',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pimpinan = User::firstOrCreate(
            ['email' => 'pimpinan_fase6@contoh.test'],
            [
                'nama' => 'Pimpinan Fase 6',
                'nip' => '198701012010011302',
                'jabatan' => 'Kepala BPS',
                'password' => 'password',
                'peran' => PeranPengguna::PIMPINAN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketuaA = User::firstOrCreate(
            ['email' => 'ketua_a_fase6@contoh.test'],
            [
                'nama' => 'Ketua A Fase 6',
                'nip' => '198701012010011303',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->ketuaB = User::firstOrCreate(
            ['email' => 'ketua_b_fase6@contoh.test'],
            [
                'nama' => 'Ketua B Fase 6',
                'nip' => '198701012010011304',
                'jabatan' => 'Statistisi Ahli Muda',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiA = User::firstOrCreate(
            ['email' => 'pegawai_a_fase6@contoh.test'],
            [
                'nama' => 'Pegawai A Fase 6',
                'nip' => '198701012010011305',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->pegawaiB = User::firstOrCreate(
            ['email' => 'pegawai_b_fase6@contoh.test'],
            [
                'nama' => 'Pegawai B Fase 6',
                'nip' => '198701012010011306',
                'jabatan' => 'Statistisi Pertama',
                'password' => 'password',
                'peran' => PeranPengguna::PEGAWAI,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->teamA = Team::firstOrCreate(
            ['nama' => 'Tim Fase 6 A'],
            ['ketua_id' => $this->ketuaA->id]
        );
        $this->teamA->members()->syncWithoutDetaching([$this->ketuaA->id, $this->pegawaiA->id]);

        $this->teamB = Team::firstOrCreate(
            ['nama' => 'Tim Fase 6 B'],
            ['ketua_id' => $this->ketuaB->id]
        );
        $this->teamB->members()->syncWithoutDetaching([$this->ketuaB->id, $this->pegawaiB->id]);
    }

    /**
     * 6.4: Kegiatan baru wajib memiliki tepat satu Penanggung Jawab.
     */
    public function test_create_activity_requires_single_penanggung_jawab(): void
    {
        Sanctum::actingAs($this->admin);

        // Tanpa penugasan sama sekali.
        $missing = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Tanpa Penugasan Fase 6',
            'jenis' => 'Survei',
            'team_id' => $this->teamA->id,
            'mulai' => '2032-01-05',
        ]);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors('assignments');

        $this->assertStringContainsString(
            'Minimal satu penugasan',
            $missing->json('errors.assignments.0') ?? ''
        );

        // Dengan penugasan tetapi tanpa peran Penanggung Jawab.
        $noPj = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Tanpa PJ Fase 6',
            'jenis' => 'Survei',
            'team_id' => $this->teamA->id,
            'mulai' => '2032-01-05',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Pencacah',
                    'jam_per_hari' => 2,
                    'volume' => 5,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $noPj->assertStatus(422)
            ->assertJsonValidationErrors('assignments');

        $this->assertStringContainsString(
            'Penanggung Jawab',
            $noPj->json('errors.assignments.0') ?? ''
        );

        // Dua Penanggung Jawab ditolak.
        $twoPj = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Dua PJ Fase 6',
            'jenis' => 'Survei',
            'team_id' => $this->teamA->id,
            'mulai' => '2032-01-05',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Penanggung Jawab',
                    'jam_per_hari' => 2,
                    'volume' => 5,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
                [
                    'user_id' => $this->ketuaA->id,
                    'peran' => 'Penanggung Jawab',
                    'jam_per_hari' => 2,
                    'volume' => 5,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $twoPj->assertStatus(422)
            ->assertJsonValidationErrors('assignments');

        // Tepat satu PJ diterima.
        $ok = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Satu PJ Fase 6',
            'jenis' => 'Survei',
            'team_id' => $this->teamA->id,
            'mulai' => '2032-01-05',
            'selesai' => '2032-01-20',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Penanggung Jawab',
                    'jam_per_hari' => 2,
                    'volume' => 10,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
                [
                    'user_id' => $this->ketuaA->id,
                    'peran' => 'Pencacah',
                    'jam_per_hari' => 2,
                    'volume' => 5,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $ok->assertCreated()
            ->assertJsonPath('data.assignments.0.peran', 'Penanggung Jawab');
    }

    /**
     * 6.4: Tanggal selesai boleh dikosongkan dan disamakan dengan mulai.
     */
    public function test_create_activity_defaults_selesai_to_mulai(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/activities', [
            'nama' => 'Kegiatan Tanpa Selesai Fase 6',
            'jenis' => 'Survei',
            'team_id' => $this->teamA->id,
            'mulai' => '2032-02-10',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Penanggung Jawab',
                    'jam_per_hari' => 2,
                    'volume' => 10,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.mulai', '2032-02-10')
            ->assertJsonPath('data.selesai', '2032-02-10');
    }

    /**
     * 6.3: Pratinjau batch hanya untuk admin atau ketua tim dari team_id.
     */
    public function test_preview_batch_authorization(): void
    {
        $payload = [
            'team_id' => $this->teamA->id,
            'mulai' => '2032-03-01',
            'selesai' => '2032-03-20',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Pencacah',
                    'jam_per_hari' => 2,
                    'volume' => 10,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ];

        // Pegawai biasa (bukan ketua tim) ditolak.
        Sanctum::actingAs($this->pegawaiA);
        $this->postJson('/api/assignments/preview-batch', $payload)->assertForbidden();

        // Ketua tim yang tidak memimpin team A ditolak.
        Sanctum::actingAs($this->ketuaB);
        $this->postJson('/api/assignments/preview-batch', $payload)->assertForbidden();

        // Ketua tim yang memimpin team A boleh.
        Sanctum::actingAs($this->ketuaA);
        $this->postJson('/api/assignments/preview-batch', $payload)->assertOk();

        // Admin boleh.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/assignments/preview-batch', $payload)->assertOk();

        // Pimpinan tidak termasuk admin/ketua: ditolak.
        Sanctum::actingAs($this->pimpinan);
        $this->postJson('/api/assignments/preview-batch', $payload)->assertForbidden();
    }

    /**
     * 6.3: Pratinjau batch mengembalikan beban sebelum dan sesudah tanpa menulis ke DB.
     */
    public function test_preview_batch_returns_before_and_after_without_writing(): void
    {
        $existingActivity = $this->createActivity(
            $this->teamA,
            'Kegiatan Eksisting Batch Fase 6',
            '2032-08-01',
            '2032-08-31'
        );

        $this->createAssignment($existingActivity, $this->pegawaiA, 2, '2032-08-05', '2032-08-15');

        $beforeActivities = Activity::count();
        $beforeAssignments = Assignment::count();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/assignments/preview-batch', [
            'team_id' => $this->teamA->id,
            'mulai' => '2032-08-10',
            'selesai' => '2032-08-20',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Pencacah',
                    'jam_per_hari' => 3,
                    'volume' => 20,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $response->assertOk();

        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);

        $pegawaiRow = $rows[0];
        $this->assertSame($this->pegawaiA->id, $pegawaiRow['user_id']);

        $impact = collect($pegawaiRow['dampak_bulanan'])->firstWhere('bulan', '2032-08');
        $this->assertNotNull($impact);

        // Penugasan eksisting: 9 hari kerja x 2 jam = 18 jam.
        // Penugasan baru: 9 hari kerja x 3 jam = 27 jam.
        $this->assertSame(14.0, (float) $impact['beban_sebelum']);
        $this->assertSame(27.0, (float) $impact['tambahan_jam']);
        $this->assertSame(41.0, (float) $impact['beban_sesudah']);
        $this->assertSame(1, $impact['jumlah_penugasan_sebelum']);
        $this->assertSame(2, $impact['jumlah_penugasan_sesudah']);
        $this->assertSame(2, $impact['penugasan_bersamaan_sesudah']);

        // Tidak menulis apa pun ke database.
        $this->assertSame($beforeActivities, Activity::count());
        $this->assertSame($beforeAssignments, Assignment::count());
    }

    /**
     * 6.3: Pratinjau batch memperingatkan bila ambang terlampaui dan antar-penugasan menumpuk.
     */
    public function test_preview_batch_warns_on_threshold_and_concurrency(): void
    {
        Setting::setVal('jam_kerja_per_hari', 0.25);
        Setting::setVal('maks_penugasan_bersamaan', 3);

        $ong1 = $this->createActivity($this->teamA, 'Kegiatan Ongkir 1 Fase 6', '2032-09-01', '2032-09-30');
        $ong2 = $this->createActivity($this->teamA, 'Kegiatan Ongkir 2 Fase 6', '2032-09-05', '2032-09-25');

        $this->createAssignment($ong1, $this->pegawaiA, 1, '2032-09-10', '2032-09-12');
        $this->createAssignment($ong2, $this->pegawaiA, 1, '2032-09-10', '2032-09-12');

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/assignments/preview-batch', [
            'team_id' => $this->teamA->id,
            'mulai' => '2032-09-10',
            'selesai' => '2032-09-15',
            'assignments' => [
                [
                    'user_id' => $this->pegawaiA->id,
                    'peran' => 'Pencacah',
                    'jam_per_hari' => 0.25,
                    'volume' => 20,
                    'satuan' => SatuanPenugasan::DOKUMEN->value,
                ],
            ],
        ]);

        $response->assertOk();

        $warningTypes = collect($response->json('data.rows.0.peringatan') ?? [])
            ->pluck('jenis')
            ->all();

        $this->assertContains('melebihi_kapasitas_harian', $warningTypes);
        $this->assertContains('penugasan_bersamaan', $warningTypes);
    }

    /**
     * 6.5: Metrik penugasan hanya tampil untuk yang berhak.
     */
    public function test_assignment_metrics_are_hidden_for_unrelated_viewers(): void
    {
        $activityA = $this->createActivity($this->teamA, 'Kegiatan Tampil A Fase 6', '2032-10-01', '2032-10-20');
        $assignmentA = $this->createAssignment($activityA, $this->pegawaiA, 25, '2032-10-01', '2032-10-20');

        // Ketua timB (tim lain) melihat metrik kosong.
        Sanctum::actingAs($this->ketuaB);
        $ketuaBResponse = $this->getJson("/api/activities/{$activityA->id}/assignments");
        $ketuaBResponse->assertOk();
        $this->assertNull($ketuaBResponse->json('data.0.volume'));
        $this->assertNull($ketuaBResponse->json('data.0.satuan'));
        $this->assertNull($ketuaBResponse->json('data.0.jam_per_hari'));
        $this->assertFalse($ketuaBResponse->json('data.0.metrics_visible'));

        // Ketua A (tim kegiatan) melihat metrik penuh.
        Sanctum::actingAs($this->ketuaA);
        $ketuaAResponse = $this->getJson("/api/activities/{$activityA->id}/assignments");
        $ketuaAResponse->assertOk();
        $this->assertSame(25.0, (float) $ketuaAResponse->json('data.0.volume'));
        $this->assertSame(2.0, (float) $ketuaAResponse->json('data.0.jam_per_hari'));
        $this->assertTrue($ketuaAResponse->json('data.0.metrics_visible'));

        // Pemilik penugasan melihat metrik penuh.
        Sanctum::actingAs($this->pegawaiA);
        $ownerResponse = $this->getJson("/api/activities/{$activityA->id}/assignments");
        $ownerResponse->assertOk();
        $this->assertSame(25.0, (float) $ownerResponse->json('data.0.volume'));

        // Admin melihat metrik penuh.
        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/activities/{$activityA->id}/assignments");
        $response->assertOk();
        $this->assertSame(25.0, (float) $response->json('data.0.volume'));

        // Pimpinan tidak berwenang membuka data kegiatan.
        Sanctum::actingAs($this->pimpinan);
        $this->getJson("/api/activities/{$activityA->id}/assignments")->assertForbidden();

        // Endpoint detail penugasan menolak pegawai yang tidak berwenang (403).
        Sanctum::actingAs($this->pegawaiB);
        $this->getJson("/api/assignments/{$assignmentA->id}")->assertForbidden();
    }

    /**
     * 6.5: Kalender menyembunyikan volume dari viewer yang tidak berhak.
     */
    public function test_calendar_hides_assignment_metrics_for_unrelated_viewers(): void
    {
        $activity = $this->createActivity($this->teamA, 'Kegiatan Kalender Fase 6', '2032-11-01', '2032-11-10');
        $this->createAssignment($activity, $this->pegawaiA, 15, '2032-11-01', '2032-11-10');

        Sanctum::actingAs($this->pegawaiB);
        $pegawaiBResponse = $this->getJson('/api/calendar?start=2032-11-01&end=2032-11-30');
        $pegawaiBResponse->assertOk();

        $event = collect($pegawaiBResponse->json('events'))->firstWhere('id', (string) $activity->id);
        $this->assertNotNull($event);
        $assignmentPayload = $event['extendedProps']['assigned_users'][0];
        $this->assertNull($assignmentPayload['volume']);
        $this->assertNull($assignmentPayload['satuan']);
        $this->assertFalse($assignmentPayload['metrics_visible']);

        Sanctum::actingAs($this->admin);
        $adminResponse = $this->getJson('/api/calendar?start=2032-11-01&end=2032-11-30');
        $adminResponse->assertOk();
        $adminEvent = collect($adminResponse->json('events'))->firstWhere('id', (string) $activity->id);
        $this->assertSame(15.0, (float) $adminEvent['extendedProps']['assigned_users'][0]['volume']);
        $this->assertTrue($adminEvent['extendedProps']['assigned_users'][0]['metrics_visible']);
    }

    /**
     * 6.5: userDetail menampilkan angka tetapi menyembunyikan metrik lintas tim dari ketua tim lain.
     */
    public function test_user_detail_concurrency_and_metrics_visibility(): void
    {
        $activityA = $this->createActivity($this->teamA, 'Kegiatan Detail A Fase 6', '2033-01-05', '2033-01-20');
        $activityB = $this->createActivity($this->teamB, 'Kegiatan Detail B Fase 6', '2033-01-05', '2033-01-20');

        $this->createAssignment($activityA, $this->pegawaiA, 10, '2033-01-05', '2033-01-15');
        $this->createAssignment($activityB, $this->pegawaiA, 40, '2033-01-05', '2033-01-15');

        Sanctum::actingAs($this->ketuaA);

        $response = $this->getJson("/api/workload/users/{$this->pegawaiA->id}?month=2033-01&per_page=50");
        $response->assertOk();

        $expected = app(WorkloadService::class)->calculateMonthlyWorkloadForUser($this->pegawaiA, '2033-01');
        $this->assertEqualsWithDelta($expected['jam_terpakai'], (float) $response->json('jam_terpakai'), 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) $response->json('persentase'), 0.001);
        $this->assertSame($expected['indikator'], $response->json('indikator'));
        $this->assertSame($expected['jumlah_penugasan'], $response->json('penugasan_meta.total'));

        $penugasan = collect($response->json('penugasan'));
        $teamARow = $penugasan->firstWhere('activity_id', $activityA->id);
        $teamBRow = $penugasan->firstWhere('activity_id', $activityB->id);

        $this->assertNotNull($teamARow);
        $this->assertNotNull($teamBRow);
        $this->assertTrue($teamARow['metrics_visible']);
        $this->assertFalse($teamBRow['metrics_visible']);
        $this->assertSame(2.0, (float) $teamARow['jam_per_hari']);
        $this->assertNull($teamBRow['jam_per_hari']);

        // Pegawai lain ditolak melihat detail.
        Sanctum::actingAs($this->pegawaiB);
        $this->getJson("/api/workload/users/{$this->pegawaiA->id}?month=2033-01")->assertForbidden();
    }

    private function createActivity(
        Team $team,
        string $nama,
        string $mulai,
        string $selesai
    ): Activity {
        return Activity::create([
            'nama' => $nama,
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }

    private function createAssignment(
        Activity $activity,
        User $user,
        float $volume,
        ?string $mulai = null,
        ?string $selesai = null
    ): Assignment {
        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pencacah',
            'jam_per_hari' => 2,
            'volume' => $volume,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }
}
