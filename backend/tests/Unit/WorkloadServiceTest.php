<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\WorkloadService;
use Tests\TestCase;

class WorkloadServiceTest extends TestCase
{
    /**
     * Jam penugasan dibagi per bulan berdasarkan hari kerja pada irisan periode.
     */
    public function test_mandatory_assignment_hours_are_distributed_per_workday(): void
    {
        $service = new WorkloadService;

        // 2 jam per hari; Oktober 2026 memiliki 22 hari kerja, 1-15 November 10 hari kerja.
        $hours = $service->calculateAssignmentMonthlyHours(
            jamPerHari: 2,
            mulai: '2026-10-01',
            selesai: '2026-11-15'
        );

        $this->assertArrayHasKey('2026-10', $hours);
        $this->assertArrayHasKey('2026-11', $hours);

        $this->assertSame(44.0, $hours['2026-10']['jam']);
        $this->assertSame(22, $hours['2026-10']['hari_kerja']);
        $this->assertSame(20.0, $hours['2026-11']['jam']);
        $this->assertSame(10, $hours['2026-11']['hari_kerja']);
        $this->assertSame(64.0, round($hours['2026-10']['jam'] + $hours['2026-11']['jam'], 2));
    }

    /**
     * Penugasan satu bulan penuh dihitung dari seluruh hari kerja bulan tersebut.
     */
    public function test_single_month_assignment_calculation(): void
    {
        $service = new WorkloadService;

        $hours = $service->calculateAssignmentMonthlyHours(
            jamPerHari: 3,
            mulai: '2026-05-01',
            selesai: '2026-05-31'
        );

        $this->assertCount(1, $hours);
        // Mei 2026 memiliki 21 hari kerja; 21 x 3 = 63 jam.
        $this->assertSame(63.0, $hours['2026-05']['jam']);
        $this->assertSame(21, $hours['2026-05']['hari_kerja']);
    }

    /**
     * Hari libur pada pengaturan tidak dihitung sebagai hari kerja.
     */
    public function test_holidays_are_excluded_from_workdays(): void
    {
        Setting::setVal('hari_libur', json_encode(['2026-05-01']));

        $service = new WorkloadService;

        $this->assertSame(20, $service->countWorkdays('2026-05-01', '2026-05-31'));
    }

    /**
     * Jam kerja per hari dari pengaturan dipakai sebagai kapasitas harian.
     */
    public function test_daily_work_hours_setting_is_respected(): void
    {
        Setting::setVal('jam_kerja_per_hari', 8);

        $service = new WorkloadService;

        $this->assertSame(8.0, $service->getDailyWorkHours());
    }

    /**
     * Memverifikasi penentuan kategori indikator beban (PRD Bagian 8.1):
     * - 'aman': < 80%
     * - 'hampir_penuh': 80% - <100%
     * - 'melebihi': >= 100%
     */
    public function test_indicator_category_determination(): void
    {
        $service = new WorkloadService;

        $this->assertEquals('aman', $service->determineIndicator(0.0));
        $this->assertEquals('aman', $service->determineIndicator(79.99));
        $this->assertEquals('hampir_penuh', $service->determineIndicator(80.0));
        $this->assertEquals('hampir_penuh', $service->determineIndicator(99.99));
        $this->assertEquals('melebihi', $service->determineIndicator(100.0));
        $this->assertEquals('melebihi', $service->determineIndicator(150.5));
    }
}
