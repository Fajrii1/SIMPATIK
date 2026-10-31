<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke endpoint kalender ditolak (401).
     */
    public function test_unauthenticated_cannot_access_calendar(): void
    {
        $this->getJson('/api/calendar')->assertStatus(401);
    }

    /**
     * Uji seluruh pengguna terautentikasi dapat melihat kalender lintas tim (PRD Bagian 5 & 14).
     */
    public function test_authenticated_user_can_view_calendar(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Viewer Kalender',
            'email' => 'viewer_kalender@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($tempPegawai);

            $response = $this->getJson('/api/calendar?start=2026-01-01&end=2026-12-31');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'events' => [
                        '*' => [
                            'id',
                            'title',
                            'start',
                            'end',
                            'allDay',
                            'extendedProps' => [
                                'activity_id',
                                'team_id',
                                'team_nama',
                                'jenis',
                                'status',
                            ],
                        ],
                    ],
                    'meta' => [
                        'start',
                        'end',
                        'total_events',
                    ],
                ]);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji filter kalender berdasarkan tim, rentang tanggal, dan status dinamis.
     */
    public function test_calendar_filters_work_properly(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $this->assertNotNull($team);

        Sanctum::actingAs($admin);

        // 1. Filter berdasarkan tim
        $teamResponse = $this->getJson("/api/calendar?start=2026-01-01&end=2026-12-31&team_id={$team->id}");
        $teamResponse->assertStatus(200);

        foreach ($teamResponse->json('events') as $event) {
            $this->assertEquals($team->id, $event['extendedProps']['team_id']);
        }

        // 2. Filter berdasarkan status
        $statusResponse = $this->getJson('/api/calendar?start=2026-01-01&end=2026-12-31&status=selesai');
        $statusResponse->assertStatus(200);

        foreach ($statusResponse->json('events') as $event) {
            $this->assertEquals('Selesai', $event['extendedProps']['status']);
        }
    }

    /**
     * Uji kegiatan multi-bulan muncul pada rentang kalender yang relevan.
     */
    public function test_multi_month_activity_appears_in_calendar(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();

        // Buat kegiatan lintas bulan: Oktober sampai Desember
        $activity = Activity::create([
            'nama' => 'Kegiatan Multi Bulan Uji Kalender',
            'jenis' => 'Survei',
            'team_id' => $team->id,
            'mulai' => '2026-10-01',
            'selesai' => '2026-12-15',
            'bobot' => 1.0,
        ]);

        try {
            Sanctum::actingAs($admin);

            // Kalender pada bulan November (harus memuat kegiatan ini karena berjalan melintasi November)
            $response = $this->getJson('/api/calendar?start=2026-11-01&end=2026-11-30');
            $response->assertStatus(200);

            $eventIds = collect($response->json('events'))->pluck('extendedProps.activity_id');
            $this->assertTrue($eventIds->contains($activity->id));
        } finally {
            $activity->forceDelete();
        }
    }
}
