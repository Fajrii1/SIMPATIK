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

    /**
     * Uji filter pegawai memakai periode efektif penugasan, bukan seluruh periode kegiatan.
     */
    public function test_calendar_filters_by_employee_assignment_period(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $team = Team::first();
        $user = User::where('aktif', true)->orderBy('id')->first();

        $activity = Activity::create([
            'nama' => 'Kegiatan Uji Filter Pegawai Kalender',
            'jenis' => 'Sensus',
            'team_id' => $team->id,
            'mulai' => '2026-09-01',
            'selesai' => '2026-12-31',
            'bobot' => 2.0,
            'keterangan' => 'Keterangan uji filter pegawai',
        ]);

        $assignment = $activity->assignments()->create([
            'user_id' => $user->id,
            'peran' => 'PIC',
            'volume' => 4,
            'satuan' => 'Dokumen',
            'mulai' => '2026-10-01',
            'selesai' => '2026-10-31',
        ]);

        try {
            Sanctum::actingAs($admin);

            // 1. Di dalam periode efektif penugasan -> muncul
            $inside = $this->getJson("/api/calendar?start=2026-10-01&end=2026-10-31&user_id={$user->id}");
            $inside->assertStatus(200);
            $insideIds = collect($inside->json('events'))->pluck('extendedProps.activity_id');
            $this->assertTrue($insideIds->contains($activity->id));

            // 2. Di luar periode efektif penugasan (tetapi masih di dalam kegiatan) -> tidak muncul untuk pegawai itu
            $outside = $this->getJson("/api/calendar?start=2026-11-01&end=2026-11-30&user_id={$user->id}");
            $outside->assertStatus(200);
            $outsideIds = collect($outside->json('events'))->pluck('extendedProps.activity_id');
            $this->assertFalse($outsideIds->contains($activity->id));

            // 3. Kata kunci dan jenis kegiatan
            $search = $this->getJson('/api/calendar?start=2026-10-01&end=2026-10-31&search=Filter Pegawai Kalender');
            $search->assertStatus(200);
            $this->assertTrue(
                collect($search->json('events'))->pluck('extendedProps.activity_id')->contains($activity->id)
            );

            $jenis = $this->getJson('/api/calendar?start=2026-10-01&end=2026-10-31&jenis=Sensus');
            $jenis->assertStatus(200);
            foreach ($jenis->json('events') as $event) {
                $this->assertSame('Sensus', $event['extendedProps']['jenis']);
            }

            // 4. Status dinormalisasi (huruf besar/kecil sama-sama diterima)
            $this->getJson('/api/calendar?start=2026-10-01&end=2026-10-31&status=Belum Mulai')
                ->assertStatus(200);
            $this->getJson('/api/calendar?start=2026-10-01&end=2026-10-31&status=berjalan')
                ->assertStatus(200);

            // 5. Data penugasan efektif tersedia di event
            $detail = $this->getJson('/api/calendar?start=2026-10-01&end=2026-10-31&search=Filter Pegawai Kalender');
            $event = collect($detail->json('events'))->firstWhere('extendedProps.activity_id', $activity->id);
            $this->assertNotNull($event);
            $this->assertSame('Keterangan uji filter pegawai', $event['extendedProps']['keterangan']);
            $this->assertSame(4, (int) $event['extendedProps']['assigned_users'][0]['volume']);
            $this->assertSame('Dokumen', $event['extendedProps']['assigned_users'][0]['satuan']);
            $this->assertSame('2026-10-01', $event['extendedProps']['assigned_users'][0]['mulai']);
            $this->assertSame('2026-10-31', $event['extendedProps']['assigned_users'][0]['selesai']);
            // end tetap eksklusif (1 hari setelah tanggal selesai kegiatan)
            $this->assertSame('2027-01-01', $event['end']);
        } finally {
            $assignment->forceDelete();
            $activity->forceDelete();
        }
    }
}
