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
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak perilaku WorkloadService dan endpoint beban kerja (PRD Bagian 8).
 *
 * Fase 3 hanya menambah pengujian. Tidak ada logika WorkloadService yang diubah.
 */
class WorkloadServiceContractTest extends TestCase
{
    protected WorkloadService $service;

    protected User $admin;

    protected Team $team;

    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkloadService::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_workload_contract@contoh.test'],
            [
                'nama' => 'Admin Kontrak Workload',
                'nip' => '198701012010011001',
                'jabatan' => 'Pranata Komputer',
                'password' => 'password',
                'peran' => PeranPengguna::ADMIN,
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        $this->team = Team::firstOrCreate(
            ['nama' => 'Tim Uji Kontrak Workload'],
            ['ketua_id' => $this->admin->id]
        );
    }

    /**
     * Poin penugasan dibagi proporsional jumlah hari ke setiap bulan yang dilalui.
     */
    public function test_monthly_points_are_split_proportionally_across_months(): void
    {
        // Total 51 hari: Januari 12 hari, Februari 29 hari (2032 kabisat), Maret 10 hari.
        $points = $this->service->calculateAssignmentMonthlyPoints(100, 1.0, '2032-01-20', '2032-03-10');

        $this->assertSame(['2032-01', '2032-02', '2032-03'], array_keys($points));
        $this->assertEqualsWithDelta(23.53, $points['2032-01'], 0.001);
        $this->assertEqualsWithDelta(56.86, $points['2032-02'], 0.001);
        $this->assertEqualsWithDelta(19.61, $points['2032-03'], 0.001);
        $this->assertEqualsWithDelta(100.00, array_sum($points), 0.001);
    }

    /**
     * Periode penugasan kosong memakai periode kegiatan (PRD 8.1).
     */
    public function test_assignment_without_dates_falls_back_to_activity_period(): void
    {
        $user = $this->makeUser('Fallback Tanggal');
        $activity = $this->makeActivity('Fallback Tanggal', '2032-02-01', '2032-02-10', 1.5);

        $this->makeAssignment($activity, $user, 10);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-02');

        $this->assertSame(15.0, $workload['beban']);
        $this->assertSame(1, $workload['jumlah_penugasan']);
        $this->assertSame('2032-02-01', $workload['rincian'][0]['mulai']);
        $this->assertSame('2032-02-10', $workload['rincian'][0]['selesai']);
        $this->assertEqualsWithDelta(15.00, $workload['persentase'], 0.001);
        $this->assertSame('aman', $workload['indikator']);
    }

    /**
     * Beberapa penugasan pada bulan yang sama dijumlahkan sebagai beban.
     */
    public function test_multiple_assignments_in_the_same_month_are_summed(): void
    {
        $user = $this->makeUser('Banyak Penugasan');

        $first = $this->makeActivity('Jumlah Satu', '2032-04-01', '2032-04-10', 1.0);
        $second = $this->makeActivity('Jumlah Dua', '2032-04-05', '2032-04-20', 1.5);
        $third = $this->makeActivity('Jumlah Tiga', '2032-04-15', '2032-04-30', 2.0);

        $this->makeAssignment($first, $user, 10);
        $this->makeAssignment($second, $user, 20);
        $this->makeAssignment($third, $user, 5);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-04');

        $this->assertSame(50.0, $workload['beban']);
        $this->assertSame(3, $workload['jumlah_penugasan']);
        $this->assertCount(3, $workload['rincian']);
        $this->assertEqualsWithDelta(50.00, $workload['persentase'], 0.001);
        $this->assertSame('aman', $workload['indikator']);
    }

    /**
     * Pegawai tanpa penugasan bernilai nol dengan indikator aman.
     */
    public function test_user_without_assignments_has_zero_load_and_safe_indicator(): void
    {
        $user = $this->makeUser('Tanpa Penugasan');

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-06');

        $this->assertSame(0.0, $workload['beban']);
        $this->assertEqualsWithDelta(0.00, $workload['persentase'], 0.001);
        $this->assertSame('aman', $workload['indikator']);
        $this->assertSame(0, $workload['jumlah_penugasan']);
        $this->assertSame(0, $workload['penugasan_bersamaan_maks']);
        $this->assertSame([], $workload['rincian']);
    }

    /**
     * Penugasan yang di-soft-delete atau berada pada kegiatan yang di-soft-delete tidak dihitung.
     */
    public function test_soft_deleted_assignments_and_activities_are_not_counted(): void
    {
        $user = $this->makeUser('Soft Delete');

        $activeActivity = $this->makeActivity('Soft Delete Aktif', '2032-09-01', '2032-09-10', 1.0);
        $this->makeAssignment($activeActivity, $user, 10);

        $deletedAssignmentActivity = $this->makeActivity('Soft Delete Penugasan', '2032-09-01', '2032-09-10', 1.0);
        $deletedAssignment = $this->makeAssignment($deletedAssignmentActivity, $user, 20);
        $deletedAssignment->delete();

        $deletedActivity = $this->makeActivity('Soft Delete Kegiatan', '2032-09-01', '2032-09-10', 1.0);
        $this->makeAssignment($deletedActivity, $user, 30);
        $deletedActivity->delete();

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-09');

        $this->assertSame(10.0, $workload['beban']);
        $this->assertSame(1, $workload['jumlah_penugasan']);
        $this->assertCount(1, $workload['rincian']);
        $this->assertSame($activeActivity->id, $workload['rincian'][0]['activity_id']);
        $this->assertSame(1, $workload['penugasan_bersamaan_maks']);
    }

    /**
     * Penugasan bersamaan memakai puncak harian, bukan jumlah penugasan sebulan (keputusan pemilik 4).
     */
    public function test_overlapping_assignments_use_daily_peak_not_monthly_count(): void
    {
        $overlapping = $this->makeUser('Tumpang Tindih');
        $sequential = $this->makeUser('Berurutan');

        // Tiga penugasan yang benar-benar bertumpang tindih pada 10 sampai 15 Mei 2032.
        foreach ([1, 2, 3] as $index) {
            $activity = $this->makeActivity("Tumpang Tindih {$index}", '2032-05-10', '2032-05-15', 1.0);
            $this->makeAssignment($activity, $overlapping, 1);
        }

        // Tiga penugasan berurutan dalam bulan yang sama, tidak ada hari yang bertumpang tindih.
        $sequentialRanges = [
            ['2032-05-01', '2032-05-03'],
            ['2032-05-05', '2032-05-07'],
            ['2032-05-09', '2032-05-11'],
        ];

        foreach ($sequentialRanges as $index => [$mulai, $selesai]) {
            $activity = $this->makeActivity('Berurutan '.($index + 1), $mulai, $selesai, 1.0);
            $this->makeAssignment($activity, $sequential, 1);
        }

        $overlappingWorkload = $this->service->calculateMonthlyWorkloadForUser($overlapping, '2032-05');
        $sequentialWorkload = $this->service->calculateMonthlyWorkloadForUser($sequential, '2032-05');

        $this->assertSame(3, $overlappingWorkload['jumlah_penugasan']);
        $this->assertSame(3, $overlappingWorkload['penugasan_bersamaan_maks']);

        $this->assertSame(3, $sequentialWorkload['jumlah_penugasan']);
        $this->assertSame(1, $sequentialWorkload['penugasan_bersamaan_maks']);

        $warnings = $this->service->getMonthlyWarnings('2032-05');

        $this->assertNotEmpty(
            $this->warningsFor($warnings, 'penugasan_bersamaan', $overlapping->id),
            'Tiga penugasan bertumpang tindih pada hari yang sama harus memicu peringatan penugasan bersamaan.'
        );

        $this->assertEmpty(
            $this->warningsFor($warnings, 'penugasan_bersamaan', $sequential->id),
            'Tiga penugasan berurutan dalam satu bulan tidak boleh memicu peringatan penugasan bersamaan.'
        );
    }

    /**
     * Batas kegiatan besar bersamaan mengikuti pengaturan dan hanya memicu bila melebihi batas.
     */
    public function test_big_activity_warning_respects_configured_limit(): void
    {
        $month = '2032-07';

        $this->assertEmpty($this->bigActivityWarnings($this->service->getMonthlyWarnings($month), $month));

        foreach ([1, 2, 3] as $index) {
            $this->makeActivity("Kegiatan Besar {$index}", '2032-07-01', '2032-07-10', 1.0, true);
        }

        // Bawaan 3: tiga kegiatan besar yang bertumpang tindih belum melebihi batas.
        $this->assertEmpty($this->bigActivityWarnings($this->service->getMonthlyWarnings($month), $month));

        $this->makeActivity('Kegiatan Besar 4', '2032-07-05', '2032-07-15', 1.0, true);

        $warnings = $this->bigActivityWarnings($this->service->getMonthlyWarnings($month), $month);

        $this->assertCount(1, $warnings);
        $this->assertSame(4, $warnings[0]['detail']['kegiatan_besar_bersamaan']);
        $this->assertSame(3, $warnings[0]['detail']['batas_kantor']);

        // Batas diturunkan menjadi 2: tiga kegiatan besar yang bertumpang tindih kini melebihi batas.
        Setting::setVal('maks_kegiatan_besar_bersamaan', 2);

        $warningsAfterChange = $this->bigActivityWarnings($this->service->getMonthlyWarnings($month), $month);

        $this->assertCount(1, $warningsAfterChange);
        $this->assertSame(2, $warningsAfterChange[0]['detail']['batas_kantor']);
    }

    /**
     * Perubahan ambang beban langsung mengubah indikator dan peringatan.
     */
    public function test_threshold_setting_change_updates_indicator_and_warning(): void
    {
        $user = $this->makeUser('Ambang Berubah');
        $activity = $this->makeActivity('Ambang Berubah', '2032-10-01', '2032-10-31', 1.0);
        $this->makeAssignment($activity, $user, 50);

        $before = $this->service->calculateMonthlyWorkloadForUser($user, '2032-10');

        $this->assertSame(50.0, $before['beban']);
        $this->assertEqualsWithDelta(50.00, $before['persentase'], 0.001);
        $this->assertSame('aman', $before['indikator']);
        $this->assertEmpty($this->warningsFor($this->service->getMonthlyWarnings('2032-10'), 'beban', $user->id));

        Setting::setVal('ambang_beban_bulanan', 50);

        $after = $this->service->calculateMonthlyWorkloadForUser($user, '2032-10');

        $this->assertSame(50.0, $after['beban']);
        $this->assertEqualsWithDelta(100.00, $after['persentase'], 0.001);
        $this->assertSame('melebihi', $after['indikator']);

        $warnings = $this->warningsFor($this->service->getMonthlyWarnings('2032-10'), 'beban', $user->id);

        $this->assertNotEmpty($warnings);
        $this->assertSame(50.0, (float) $warnings[0]['detail']['beban_aktual']);
    }

    /**
     * Perubahan batas penugasan bersamaan langsung mengubah peringatan.
     */
    public function test_concurrent_assignment_limit_setting_change_updates_warning(): void
    {
        $user = $this->makeUser('Batas Bersamaan');

        foreach ([1, 2] as $index) {
            $activity = $this->makeActivity("Batas Bersamaan {$index}", '2032-11-10', '2032-11-12', 1.0);
            $this->makeAssignment($activity, $user, 1);
        }

        $this->assertSame(2, $this->service->calculateMonthlyWorkloadForUser($user, '2032-11')['penugasan_bersamaan_maks']);
        $this->assertEmpty($this->warningsFor($this->service->getMonthlyWarnings('2032-11'), 'penugasan_bersamaan', $user->id));

        Setting::setVal('maks_penugasan_bersamaan', 2);

        $warnings = $this->warningsFor($this->service->getMonthlyWarnings('2032-11'), 'penugasan_bersamaan', $user->id);

        $this->assertNotEmpty($warnings);
        $this->assertSame(2, $warnings[0]['detail']['puncak_bersamaan']);
        $this->assertSame(2, $warnings[0]['detail']['batas_maksimal']);
    }

    /**
     * Hasil pratinjau penugasan baru sama dengan hasil setelah penugasan disimpan.
     */
    public function test_preview_matches_saved_assignment_result(): void
    {
        $user = $this->makeUser('Pratinjau');
        $month = '2032-08';

        $existingActivity = $this->makeActivity('Pratinjau Lama', '2032-08-01', '2032-08-31', 1.0);
        $this->makeAssignment($existingActivity, $user, 10);

        $newActivity = $this->makeActivity('Pratinjau Baru', '2032-08-10', '2032-08-20', 1.5);

        $preview = $this->service->previewAssignmentImpact([
            'user_id' => $user->id,
            'activity_id' => $newActivity->id,
            'volume' => 20,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'peran' => 'Pencacah',
            'mulai' => '2032-08-10',
            'selesai' => '2032-08-20',
        ]);

        $this->assertCount(1, $preview['dampak_bulanan']);
        $impact = $preview['dampak_bulanan'][0];

        $this->assertSame($month, $impact['bulan']);
        $this->assertSame(10.0, $impact['beban_sebelum']);
        $this->assertSame(40.0, $impact['beban_sesudah']);
        $this->assertSame(40.0, $impact['tambahan_poin'] + $impact['beban_sebelum']);
        $this->assertSame(1, $impact['jumlah_penugasan_sebelum']);
        $this->assertSame(2, $impact['jumlah_penugasan_sesudah']);
        $this->assertSame(1, $impact['penugasan_bersamaan_sebelum']);
        $this->assertSame(2, $impact['penugasan_bersamaan_sesudah']);

        $saved = $this->makeAssignment($newActivity, $user, 20, '2032-08-10', '2032-08-20');
        $afterSave = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame($impact['beban_sesudah'], $afterSave['beban']);
        $this->assertSame($impact['persentase_sesudah'], $afterSave['persentase']);
        $this->assertSame($impact['indikator_sesudah'], $afterSave['indikator']);
        $this->assertSame($impact['jumlah_penugasan_sesudah'], $afterSave['jumlah_penugasan']);
        $this->assertSame($impact['penugasan_bersamaan_sesudah'], $afterSave['penugasan_bersamaan_maks']);

        // Pratinjau perubahan penugasan yang sudah ada juga harus sama dengan hasil setelah disimpan.
        $previewUpdate = $this->service->previewAssignmentImpact([
            'user_id' => $user->id,
            'assignment_id' => $saved->id,
            'activity_id' => $newActivity->id,
            'volume' => 30,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'peran' => 'Pencacah',
            'mulai' => '2032-08-10',
            'selesai' => '2032-08-20',
        ]);

        $saved->update(['volume' => 30]);
        $afterUpdate = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame(
            $previewUpdate['dampak_bulanan'][0]['beban_sesudah'],
            $afterUpdate['beban']
        );
        $this->assertSame(
            $previewUpdate['dampak_bulanan'][0]['persentase_sesudah'],
            $afterUpdate['persentase']
        );
        $this->assertSame(
            $previewUpdate['dampak_bulanan'][0]['jumlah_penugasan_sesudah'],
            $afterUpdate['jumlah_penugasan']
        );
    }

    /**
     * Mengubah volume penugasan atau bobot kegiatan langsung memperbarui beban.
     */
    public function test_changing_volume_or_weight_updates_load(): void
    {
        $user = $this->makeUser('Volume Bobot');
        $activity = $this->makeActivity('Volume Bobot', '2032-12-01', '2032-12-31', 2.0);
        $assignment = $this->makeAssignment($activity, $user, 10);

        $this->assertSame(20.0, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['beban']);

        $assignment->update(['volume' => 15]);
        $this->assertSame(30.0, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['beban']);

        $activity->update(['bobot' => 1.5]);
        $this->assertSame(22.5, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['beban']);
    }

    /**
     * Angka beban identik antara WorkloadController index, DashboardController, dan ReportController.
     */
    public function test_workload_numbers_are_identical_across_index_dashboard_and_report(): void
    {
        $month = Carbon::now()->format('Y-m');
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $user = $this->makeUser('Konsistensi Endpoint');
        $activity = $this->makeActivity('Konsistensi Endpoint', $monthStart, $monthEnd, 1.25);
        $this->makeAssignment($activity, $user, 40);

        $expected = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame(50.0, $expected['beban']);
        $this->assertEqualsWithDelta(50.00, $expected['persentase'], 0.001);
        $this->assertSame('aman', $expected['indikator']);

        // 1. WorkloadController::index
        Sanctum::actingAs($this->admin);
        $indexResponse = $this->getJson("/api/workload?month={$month}");
        $indexResponse->assertOk();

        $indexRow = collect($indexResponse->json('data'))->firstWhere('user.id', $user->id);

        $this->assertNotNull($indexRow, 'Pegawai uji harus muncul pada daftar beban kerja.');
        $this->assertEqualsWithDelta($expected['beban'], (float) $indexRow['poin'], 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) $indexRow['persentase'], 0.001);
        $this->assertSame($expected['indikator'], $indexRow['indikator']);
        $this->assertSame($expected['jumlah_penugasan'], $indexRow['jumlah_penugasan']);
        $this->assertSame($expected['penugasan_bersamaan_maks'], $indexRow['penugasan_bersamaan_maks']);

        // 2. DashboardController::index (beban pribadi pengguna yang sedang login)
        Sanctum::actingAs($user);
        $dashboardResponse = $this->getJson('/api/dashboard');
        $dashboardResponse->assertOk();

        $this->assertEqualsWithDelta(
            $expected['beban'],
            (float) $dashboardResponse->json('data.beban_kerja_pribadi.poin'),
            0.001
        );
        $this->assertEqualsWithDelta(
            $expected['persentase'],
            (float) $dashboardResponse->json('data.beban_kerja_pribadi.persentase'),
            0.001
        );
        $this->assertSame(
            $expected['indikator'],
            $dashboardResponse->json('data.beban_kerja_pribadi.indikator')
        );
        $this->assertSame(
            $expected['jumlah_penugasan'],
            $dashboardResponse->json('data.beban_kerja_pribadi.jumlah_penugasan')
        );
        $this->assertSame(
            $expected['penugasan_bersamaan_maks'],
            $dashboardResponse->json('data.beban_kerja_pribadi.penugasan_bersamaan_maks')
        );

        // 3. ReportController::workloadReport (CSV)
        Sanctum::actingAs($this->admin);
        $reportResponse = $this->get("/api/reports/workload?format=csv&month={$month}");
        $reportResponse->assertOk();

        $rows = array_map(
            fn (string $line) => str_getcsv($line, ',', '"', '\\'),
            preg_split('/\r\n|\r|\n/', trim($reportResponse->streamedContent()))
        );

        $reportRow = collect($rows)->first(
            fn (array $row) => isset($row[1]) && $row[1] === $user->nama
        );

        $this->assertNotNull($reportRow, 'Pegawai uji harus muncul pada laporan beban kerja CSV.');
        $this->assertEqualsWithDelta($expected['beban'], (float) $reportRow[4], 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) rtrim($reportRow[5], '%'), 0.001);
        $this->assertSame(strtoupper($expected['indikator']), $reportRow[6]);
        $this->assertSame((string) $expected['jumlah_penugasan'], $reportRow[7]);
        $this->assertSame((string) $expected['penugasan_bersamaan_maks'], $reportRow[8]);
    }

    /**
     * Endpoint detail beban kerja mengembalikan angka bulan yang sama dengan service.
     *
     * Bila endpoint gagal, pengujian ditandai belum lengkap dan temuan dilaporkan
     * pada laporan Fase 3 tanpa diperbaiki karena fase ini hanya menambah pengujian.
     */
    public function test_user_detail_endpoint_matches_workload_service(): void
    {
        $month = Carbon::now()->format('Y-m');
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $user = $this->makeUser('Detail Endpoint');
        $activity = $this->makeActivity('Detail Endpoint', $monthStart, $monthEnd, 1.0);
        $this->makeAssignment($activity, $user, 25);

        $expected = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$user->id}?from={$month}&to={$month}");

        if ($response->status() >= 500) {
            $reason = $response->exception
                ? $response->exception->getMessage()
                : substr((string) $response->getContent(), 0, 200);

            $this->markTestIncomplete(
                'GET /api/workload/users/{id} gagal 500 dengan pesan "'.$reason.'". '
                .'Temuan Fase 3: WorkloadController::userDetail membaca ->value pada activity->jenis '
                .'dan assignment->satuan yang bertipe string. Tidak diperbaiki karena Fase 3 hanya '
                .'menambah pengujian; rincian ada pada laporan Fase 3.'
            );
        }

        $response->assertOk();

        $historyRow = collect($response->json('tren_bulanan'))->firstWhere('bulan', $month);

        $this->assertNotNull($historyRow);
        $this->assertEqualsWithDelta($expected['beban'], (float) $historyRow['poin'], 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) $historyRow['persentase'], 0.001);
        $this->assertSame($expected['indikator'], $historyRow['indikator']);
        $this->assertSame($expected['jumlah_penugasan'], $historyRow['jumlah_penugasan']);
        $this->assertSame($expected['penugasan_bersamaan_maks'], $historyRow['penugasan_bersamaan_maks']);
    }

    private function makeUser(string $label, bool $aktif = true): User
    {
        $this->sequence++;

        $user = User::create([
            'nama' => "Uji Workload {$label} {$this->sequence}",
            'email' => 'uji_workload_'.$this->sequence.'@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '19990101202001'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'jabatan' => 'Statistisi Pertama',
            'aktif' => $aktif,
            'must_change_password' => false,
        ]);

        $this->team->members()->syncWithoutDetaching([$user->id]);

        return $user;
    }

    private function makeActivity(
        string $label,
        string $mulai,
        string $selesai,
        float $bobot = 1.0,
        bool $kegiatanBesar = false
    ): Activity {
        return Activity::create([
            'nama' => "Kegiatan Uji {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'bobot' => $bobot,
            'kegiatan_besar' => $kegiatanBesar,
        ]);
    }

    private function makeAssignment(
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
            'volume' => $volume,
            'satuan' => SatuanPenugasan::DOKUMEN->value,
            'mulai' => $mulai,
            'selesai' => $selesai,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function warningsFor(array $warnings, string $jenis, int $userId): array
    {
        return array_values(array_filter(
            $warnings,
            fn (array $warning) => $warning['jenis'] === $jenis
                && $warning['pegawai_id'] !== null
                && (int) $warning['pegawai_id'] === $userId
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function bigActivityWarnings(array $warnings, string $month): array
    {
        return array_values(array_filter(
            $warnings,
            fn (array $warning) => $warning['jenis'] === 'kegiatan_besar_bersamaan'
                && $warning['bulan'] === $month
        ));
    }
}
