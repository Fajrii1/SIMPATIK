<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected $seeder = null;

    public function test_database_seeder_minimal_runs_on_empty_database(): void
    {
        // Uji seeder minimal pada database yang masih berisi data demo global:
        // jalankan ulang seeder minimal dan pastikan akun/tim utama tersedia.
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@contoh.test']);
        $this->assertDatabaseHas('users', ['email' => 'kepala@contoh.test']);

        $expectedTeams = ['Umum', 'Sosial', 'Produksi', 'Distribusi', 'NWAS', 'IPDS'];
        foreach ($expectedTeams as $teamName) {
            $team = Team::where('nama', $teamName)->first();
            $this->assertNotNull($team, "Tim [{$teamName}] harus terdaftar.");
        }
    }

    public function test_demo_data_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->seed(DemoDataSeeder::class);
        $usersAfterFirst = User::count();
        $teamsAfterFirst = Team::count();
        $activitiesAfterFirst = Activity::count();

        $this->seed(DemoDataSeeder::class);
        $this->assertSame($usersAfterFirst, User::count());
        $this->assertSame($teamsAfterFirst, Team::count());
        $this->assertSame($activitiesAfterFirst, Activity::count());
    }
}
