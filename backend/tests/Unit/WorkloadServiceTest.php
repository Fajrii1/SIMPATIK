<?php

namespace Tests\Unit;

use App\Services\WorkloadService;
use Tests\TestCase;

class WorkloadServiceTest extends TestCase
{
    /**
     * Memverifikasi contoh uji wajib PRD Bagian 8.1:
     * - Penugasan volume 60, bobot 1.5, periode 1 Oktober sampai 15 November.
     * - Poin total: 90.
     * - Total hari: 46 (Oktober 31, November 15).
     * - Poin Oktober = 90 * 31 / 46 = 60.65
     * - Poin November = 90 * 15 / 46 = 29.35
     */
    public function test_mandatory_prd_8_1_example_calculation(): void
    {
        $service = new WorkloadService;

        $points = $service->calculateAssignmentMonthlyPoints(
            volume: 60,
            bobot: 1.5,
            mulai: '2026-10-01',
            selesai: '2026-11-15'
        );

        $this->assertArrayHasKey('2026-10', $points);
        $this->assertArrayHasKey('2026-11', $points);

        $this->assertEquals(60.65, $points['2026-10']);
        $this->assertEquals(29.35, $points['2026-11']);
        $this->assertEquals(90.00, round($points['2026-10'] + $points['2026-11'], 2));
    }

    /**
     * Memverifikasi kalkulasi penugasan satu bulan penuh.
     */
    public function test_single_month_assignment_calculation(): void
    {
        $service = new WorkloadService;

        $points = $service->calculateAssignmentMonthlyPoints(
            volume: 50,
            bobot: 1.0,
            mulai: '2026-05-01',
            selesai: '2026-05-31'
        );

        $this->assertCount(1, $points);
        $this->assertEquals(50.00, $points['2026-05']);
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
