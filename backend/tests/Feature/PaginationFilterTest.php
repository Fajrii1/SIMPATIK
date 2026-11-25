<?php

namespace Tests\Feature;

use App\Enums\StatusKendala;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginationFilterTest extends TestCase
{
    /**
     * Daftar pengguna memakai paginasi server-side dan filter peran.
     */
    public function test_users_pagination_meta_and_role_filter(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $this->assertNotNull($admin);

        $totalPegawai = User::where('peran', 'pegawai')->count();
        $this->assertGreaterThanOrEqual(3, $totalPegawai);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?per_page=2&page=2&peran=pegawai');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', $totalPegawai);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Daftar kendala memakai paginasi server-side dan mendukung pencarian.
     */
    public function test_issues_pagination_meta_and_search(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $team = Team::first();

        $this->assertNotNull($admin);
        $this->assertNotNull($pegawai);
        $this->assertNotNull($team);

        foreach (range(1, 6) as $index) {
            WeeklyIssue::create([
                'team_id' => $team->id,
                'pelapor_id' => $pegawai->id,
                'bulan' => Carbon::parse('2026-11-01'),
                'kendala' => "UJI-PAGINASI kendala {$index}",
                'status' => StatusKendala::TERBUKA,
            ]);
        }

        Sanctum::actingAs($admin);

        $search = $this->getJson('/api/issues?search=UJI-PAGINASI&per_page=4&page=2');

        $search->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 4)
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(2, $search->json('data'));
    }

    /**
     * Notifikasi memakai paginasi server-side dan filter belum dibaca.
     */
    public function test_notifications_unread_filter_and_pagination_meta(): void
    {
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $this->assertNotNull($pegawai);

        Notification::where('user_id', $pegawai->id)->delete();

        foreach (range(1, 5) as $index) {
            Notification::create([
                'user_id' => $pegawai->id,
                'tipe' => 'info',
                'pesan' => "Notifikasi uji paginasi {$index}",
                'tautan' => '/notifications',
                'dibaca_pada' => $index <= 2 ? Carbon::now() : null,
            ]);
        }

        Sanctum::actingAs($pegawai);

        $firstPage = $this->getJson('/api/notifications?unread=1&per_page=2&page=1');

        $firstPage->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(2, $firstPage->json('data'));

        foreach ($firstPage->json('data') as $item) {
            $this->assertFalse($item['is_read']);
        }

        $secondPage = $this->getJson('/api/notifications?unread=1&per_page=2&page=2');

        $secondPage->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->assertCount(1, $secondPage->json('data'));
    }
}
