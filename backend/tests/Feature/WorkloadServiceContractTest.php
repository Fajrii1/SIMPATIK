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
 * Beban kerja dihitung dari jam per hari dikali hari kerja efektif, bukan dari
 * volume atau bobot kegiatan.
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
     * Jam penugasan dihitung per bulan dari hari kerja pada irisan periode.
     */
    public function test_monthly_hours_are_split_by_workdays_across_months(): void
    {
        // Januari 9 hari kerja, Februari 20 hari kerja, Maret 8 hari kerja.
        $hours = $this->service->calculateAssignmentMonthlyHours(2, '2032-01-20', '2032-03-10');

        $this->assertSame(['2032-01', '2032-02', '2032-03'], array_keys($hours));
        $this->assertSame(18.0, $hours['2032-01']['jam']);
        $this->assertSame(40.0, $hours['2032-02']['jam']);
        $this->assertSame(16.0, $hours['2032-03']['jam']);
        $this->assertSame(9, $hours['2032-01']['hari_kerja']);
        $this->assertSame(74.0, array_sum(array_column($hours, 'jam')));
    }

    /**
     * Periode penugasan kosong memakai periode kegiatan (PRD 8.1).
     */
    public function test_assignment_without_dates_falls_back_to_activity_period(): void
    {
        $user = $this->makeUser('Fallback Tanggal');
        $activity = $this->makeActivity('Fallback Tanggal', '2032-02-01', '2032-02-10');

        $this->makeAssignment($activity, $user, 2);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-02');

        $this->assertSame(14.0, $workload['jam_terpakai']);
        $this->assertSame(14.0, $workload['beban']);
        $this->assertSame(140.0, $workload['kapasitas_jam']);
        $this->assertSame(1, $workload['jumlah_penugasan']);
        $this->assertSame('2032-02-01', $workload['rincian'][0]['mulai']);
        $this->assertSame('2032-02-10', $workload['rincian'][0]['selesai']);
        $this->assertEqualsWithDelta(10.00, $workload['persentase'], 0.001);
        $this->assertSame('aman', $workload['indikator']);
    }

    /**
     * Beberapa penugasan pada bulan yang sama dijumlahkan sebagai beban.
     */
    public function test_multiple_assignments_in_the_same_month_are_summed(): void
    {
        $user = $this->makeUser('Banyak Penugasan');

        $first = $this->makeActivity('Jumlah Satu', '2032-04-01', '2032-04-10');
        $second = $this->makeActivity('Jumlah Dua', '2032-04-05', '2032-04-20');
        $third = $this->makeActivity('Jumlah Tiga', '2032-04-15', '2032-04-30');

        $this->makeAssignment($first, $user, 2);
        $this->makeAssignment($second, $user, 2);
        $this->makeAssignment($third, $user, 2);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-04');

        // 7 + 12 + 12 hari kerja, masing-masing 2 jam per hari.
        $this->assertSame(62.0, $workload['jam_terpakai']);
        $this->assertSame(3, $workload['jumlah_penugasan']);
        $this->assertCount(3, $workload['rincian']);
        $this->assertEqualsWithDelta(40.26, $workload['persentase'], 0.001);
        $this->assertSame('aman', $workload['indikator']);
    }

    /**
     * Pegawai tanpa penugasan bernilai nol dengan indikator aman.
     */
    public function test_user_without_assignments_has_zero_load_and_safe_indicator(): void
    {
        $user = $this->makeUser('Tanpa Penugasan');

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-06');

        $this->assertSame(0.0, $workload['jam_terpakai']);
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

        $activeActivity = $this->makeActivity('Soft Delete Aktif', '2032-09-01', '2032-09-10');
        $this->makeAssignment($activeActivity, $user, 2);

        $deletedAssignmentActivity = $this->makeActivity('Soft Delete Penugasan', '2032-09-01', '2032-09-10');
        $deletedAssignment = $this->makeAssignment($deletedAssignmentActivity, $user, 2);
        $deletedAssignment->delete();

        $deletedActivity = $this->makeActivity('Soft Delete Kegiatan', '2032-09-01', '2032-09-10');
        $this->makeAssignment($deletedActivity, $user, 2);
        $deletedActivity->delete();

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-09');

        $this->assertSame(16.0, $workload['jam_terpakai']);
        $this->assertSame(1, $workload['jumlah_penugasan']);
        $this->assertCount(1, $workload['rincian']);
        $this->assertSame($activeActivity->id, $workload['rincian'][0]['activity_id']);
        $this->assertSame(1, $workload['penugasan_bersamaan_maks']);
    }

    /**
     * Penugasan bersamaan memakai puncak harian, bukan jumlah penugasan sebulan.
     */
    public function test_overlapping_assignments_use_daily_peak_not_monthly_count(): void
    {
        $overlapping = $this->makeUser('Tumpang Tindih');
        $sequential = $this->makeUser('Berurutan');

        // Tiga penugasan yang benar-benar bertumpang tindih pada 10 sampai 15 Mei 2032.
        foreach ([1, 2, 3] as $index) {
            $activity = $this->makeActivity("Tumpang Tindih {$index}", '2032-05-10', '2032-05-15');
            $this->makeAssignment($activity, $overlapping, 1);
        }

        // Tiga penugasan berurutan dalam bulan yang sama, tidak ada hari yang bertumpang tindih.
        $sequentialRanges = [
            ['2032-05-01', '2032-05-03'],
            ['2032-05-05', '2032-05-07'],
            ['2032-05-09', '2032-05-11'],
        ];

        foreach ($sequentialRanges as $index => [$mulai, $selesai]) {
            $activity = $this->makeActivity('Berurutan '.($index + 1), $mulai, $selesai);
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
            $this->makeActivity("Kegiatan Besar {$index}", '2032-07-01', '2032-07-10', true);
        }

        // Bawaan 3: tiga kegiatan besar yang bertumpang tindih belum melebihi batas.
        $this->assertEmpty($this->bigActivityWarnings($this->service->getMonthlyWarnings($month), $month));

        $this->makeActivity('Kegiatan Besar 4', '2032-07-05', '2032-07-15', true);

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
     * Perubahan jam kerja per hari langsung mengubah kapasitas, indikator, dan peringatan.
     */
    public function test_daily_work_hours_setting_changes_capacity_and_indicator(): void
    {
        $user = $this->makeUser('Kapasitas Berubah');
        $activity = $this->makeActivity('Kapasitas Berubah', '2032-10-01', '2032-10-31');

        // 4 jam per hari x 21 hari kerja = 84 jam, kapasitas bawaan 7 x 21 = 147 jam.
        $this->makeAssignment($activity, $user, 4);

        $before = $this->service->calculateMonthlyWorkloadForUser($user, '2032-10');

        $this->assertSame(84.0, $before['jam_terpakai']);
        $this->assertSame(147.0, $before['kapasitas_jam']);
        $this->assertSame('aman', $before['indikator']);
        $this->assertEmpty($this->warningsFor($this->service->getMonthlyWarnings('2032-10'), 'beban', $user->id));

        // Kapasitas diturunkan menjadi 4 jam per hari sehingga beban mencapai 100%.
        Setting::setVal('jam_kerja_per_hari', 4);

        $after = $this->service->calculateMonthlyWorkloadForUser($user, '2032-10');

        $this->assertSame(84.0, $after['jam_terpakai']);
        $this->assertSame(84.0, $after['kapasitas_jam']);
        $this->assertSame(4.0, $after['jam_kerja_per_hari']);
        $this->assertEqualsWithDelta(100.00, $after['persentase'], 0.001);
        $this->assertSame('melebihi', $after['indikator']);

        $warnings = $this->warningsFor($this->service->getMonthlyWarnings('2032-10'), 'beban', $user->id);

        $this->assertNotEmpty($warnings);
        $this->assertSame(84.0, (float) $warnings[0]['detail']['jam_terpakai']);
        $this->assertSame(84.0, (float) $warnings[0]['detail']['kapasitas_jam']);
    }

    /**
     * Hari libur mengurangi hari kerja efektif dan kapasitas bulanan.
     */
    public function test_holiday_setting_reduces_workdays_and_hours(): void
    {
        $user = $this->makeUser('Hari Libur');
        $activity = $this->makeActivity('Hari Libur', '2032-03-02', '2032-03-05');

        $this->makeAssignment($activity, $user, 2);
        Setting::setVal('hari_libur', json_encode(['2032-03-03']));

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-03');

        // Rentang 4 hari kerja dikurangi 1 hari libur menjadi 3 hari.
        $this->assertSame(6.0, $workload['jam_terpakai']);
        $this->assertSame(3, $workload['rincian'][0]['hari_kerja']);
        $this->assertSame(22, $workload['hari_kerja']);
        $this->assertSame(154.0, $workload['kapasitas_jam']);
    }

    /**
     * Beban harian di atas jam kerja per hari memunculkan peringatan khusus.
     */
    public function test_daily_load_above_daily_capacity_triggers_warning(): void
    {
        $user = $this->makeUser('Beban Harian');

        $first = $this->makeActivity('Beban Harian Satu', '2032-11-10', '2032-11-12');
        $second = $this->makeActivity('Beban Harian Dua', '2032-11-10', '2032-11-12');

        $this->makeAssignment($first, $user, 7);
        $this->makeAssignment($second, $user, 1);

        $workload = $this->service->calculateMonthlyWorkloadForUser($user, '2032-11');

        $this->assertSame(8.0, $workload['beban_harian_maks']);
        $this->assertCount(3, $workload['hari_melebihi_kapasitas']);

        $warnings = $this->warningsFor($this->service->getMonthlyWarnings('2032-11'), 'melebihi_kapasitas_harian', $user->id);

        $this->assertNotEmpty($warnings);
        $this->assertSame(7.0, (float) $warnings[0]['detail']['kapasitas_harian']);
    }

    /**
     * Perubahan batas penugasan bersamaan langsung mengubah peringatan.
     */
    public function test_concurrent_assignment_limit_setting_change_updates_warning(): void
    {
        $user = $this->makeUser('Batas Bersamaan');

        foreach ([1, 2] as $index) {
            $activity = $this->makeActivity("Batas Bersamaan {$index}", '2032-11-20', '2032-11-22');
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

        $existingActivity = $this->makeActivity('Pratinjau Lama', '2032-08-01', '2032-08-31');
        $this->makeAssignment($existingActivity, $user, 2);

        $newActivity = $this->makeActivity('Pratinjau Baru', '2032-08-10', '2032-08-20');

        $preview = $this->service->previewAssignmentImpact([
            'user_id' => $user->id,
            'activity_id' => $newActivity->id,
            'jam_per_hari' => 3,
            'peran' => 'Pencacah',
            'mulai' => '2032-08-10',
            'selesai' => '2032-08-20',
        ]);

        $this->assertCount(1, $preview['dampak_bulanan']);
        $impact = $preview['dampak_bulanan'][0];

        // Penugasan lama 22 hari x 2 jam = 44 jam; penugasan baru 9 hari x 3 jam = 27 jam.
        $this->assertSame($month, $impact['bulan']);
        $this->assertSame(44.0, $impact['beban_sebelum']);
        $this->assertSame(71.0, $impact['beban_sesudah']);
        $this->assertSame(27.0, $impact['tambahan_jam']);
        $this->assertSame(154.0, $impact['kapasitas_jam']);
        $this->assertSame(1, $impact['jumlah_penugasan_sebelum']);
        $this->assertSame(2, $impact['jumlah_penugasan_sesudah']);
        $this->assertSame(1, $impact['penugasan_bersamaan_sebelum']);
        $this->assertSame(2, $impact['penugasan_bersamaan_sesudah']);

        $saved = $this->makeAssignment($newActivity, $user, 3, '2032-08-10', '2032-08-20');
        $afterSave = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame($impact['beban_sesudah'], $afterSave['jam_terpakai']);
        $this->assertSame($impact['persentase_sesudah'], $afterSave['persentase']);
        $this->assertSame($impact['indikator_sesudah'], $afterSave['indikator']);
        $this->assertSame($impact['jumlah_penugasan_sesudah'], $afterSave['jumlah_penugasan']);
        $this->assertSame($impact['penugasan_bersamaan_sesudah'], $afterSave['penugasan_bersamaan_maks']);

        // Pratinjau perubahan penugasan yang sudah ada juga harus sama dengan hasil setelah disimpan.
        $previewUpdate = $this->service->previewAssignmentImpact([
            'user_id' => $user->id,
            'assignment_id' => $saved->id,
            'activity_id' => $newActivity->id,
            'jam_per_hari' => 4,
            'peran' => 'Pencacah',
            'mulai' => '2032-08-10',
            'selesai' => '2032-08-20',
        ]);

        $saved->update(['jam_per_hari' => 4]);
        $afterUpdate = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame(
            $previewUpdate['dampak_bulanan'][0]['beban_sesudah'],
            $afterUpdate['jam_terpakai']
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
     * Mengubah jam per hari memperbarui beban, sedangkan volume tidak dihitung.
     */
    public function test_changing_daily_hours_updates_load_but_volume_does_not(): void
    {
        $user = $this->makeUser('Jam Per Hari');
        $activity = $this->makeActivity('Jam Per Hari', '2032-12-01', '2032-12-31');
        $assignment = $this->makeAssignment($activity, $user, 2);

        // 23 hari kerja x 2 jam = 46 jam.
        $this->assertSame(46.0, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['jam_terpakai']);

        $assignment->update(['jam_per_hari' => 3]);
        $this->assertSame(69.0, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['jam_terpakai']);

        $assignment->update(['volume' => 999]);
        $this->assertSame(69.0, $this->service->calculateMonthlyWorkloadForUser($user, '2032-12')['jam_terpakai']);
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
        $activity = $this->makeActivity('Konsistensi Endpoint', $monthStart, $monthEnd);
        $this->makeAssignment($activity, $user, 3);

        $expected = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        $this->assertSame(3.0 * $this->service->countWorkdays($monthStart, $monthEnd), $expected['jam_terpakai']);
        $this->assertGreaterThan(0.0, $expected['kapasitas_jam']);

        // 1. WorkloadController::index
        Sanctum::actingAs($this->admin);
        $indexResponse = $this->getJson("/api/workload?month={$month}&per_page=50&search=".urlencode($user->nama));
        $indexResponse->assertOk();

        $indexRow = collect($indexResponse->json('data'))->firstWhere('user.id', $user->id);

        $this->assertNotNull($indexRow, 'Pegawai uji harus muncul pada daftar beban kerja.');
        $this->assertEqualsWithDelta($expected['jam_terpakai'], (float) $indexRow['jam_terpakai'], 0.001);
        $this->assertEqualsWithDelta($expected['kapasitas_jam'], (float) $indexRow['kapasitas_jam'], 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) $indexRow['persentase'], 0.001);
        $this->assertSame($expected['indikator'], $indexRow['indikator']);
        $this->assertSame($expected['jumlah_penugasan'], $indexRow['jumlah_penugasan']);
        $this->assertSame($expected['penugasan_bersamaan_maks'], $indexRow['penugasan_bersamaan_maks']);

        // 2. DashboardController::index (beban pribadi pengguna yang sedang login)
        Sanctum::actingAs($user);
        $dashboardResponse = $this->getJson('/api/dashboard');
        $dashboardResponse->assertOk();

        $this->assertEqualsWithDelta(
            $expected['jam_terpakai'],
            (float) $dashboardResponse->json('data.beban_kerja_pribadi.jam_terpakai'),
            0.001
        );
        $this->assertEqualsWithDelta(
            $expected['kapasitas_jam'],
            (float) $dashboardResponse->json('data.beban_kerja_pribadi.kapasitas_jam'),
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
        $this->assertEqualsWithDelta($expected['jam_terpakai'], (float) $reportRow[4], 0.001);
        $this->assertEqualsWithDelta($expected['kapasitas_jam'], (float) $reportRow[5], 0.001);
        $this->assertEqualsWithDelta($expected['persentase'], (float) rtrim($reportRow[6], '%'), 0.001);
        $this->assertSame((string) $expected['jumlah_penugasan'], $reportRow[8]);
        $this->assertSame((string) $expected['penugasan_bersamaan_maks'], $reportRow[9]);
    }

    /**
     * Endpoint detail beban kerja mengembalikan angka bulan yang sama dengan service.
     */
    public function test_user_detail_endpoint_matches_workload_service(): void
    {
        $month = Carbon::now()->format('Y-m');
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $user = $this->makeUser('Detail Endpoint');
        $activity = $this->makeActivity('Detail Endpoint', $monthStart, $monthEnd);
        $this->makeAssignment($activity, $user, 2);

        $expected = $this->service->calculateMonthlyWorkloadForUser($user, $month);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson("/api/workload/users/{$user->id}?month={$month}");

        $response->assertOk();
        $response->assertJsonPath('bulan', $month);
        $this->assertEqualsWithDelta($expected['jam_terpakai'], (float) $response->json('jam_terpakai'), 0.001);
        $this->assertEqualsWithDelta($expected['kapasitas_jam'], (float) $response->json('kapasitas_jam'), 0.001);
        $this->assertSame($expected['indikator'], $response->json('indikator'));
        $this->assertSame(1, $response->json('penugasan_meta.total'));
        $this->assertSame(2.0, (float) $response->json('penugasan.0.jam_per_hari'));
        $this->assertSame(2.0 * $this->service->countWorkdays($monthStart, $monthEnd), (float) $response->json('penugasan.0.jam_terpakai'));
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
        bool $kegiatanBesar = false
    ): Activity {
        return Activity::create([
            'nama' => "Kegiatan Uji {$label}",
            'jenis' => JenisKegiatan::SURVEI,
            'team_id' => $this->team->id,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'kegiatan_besar' => $kegiatanBesar,
        ]);
    }

    private function makeAssignment(
        Activity $activity,
        User $user,
        float $jamPerHari = 2.0,
        ?string $mulai = null,
        ?string $selesai = null
    ): Assignment {
        return Assignment::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'peran' => 'Pencacah',
            'jam_per_hari' => $jamPerHari,
            'volume' => 10,
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
