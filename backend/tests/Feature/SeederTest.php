<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected $seeder = null;

    public function test_database_seeder_minimal_runs_on_empty_database(): void
    {
        // Uji pada database kosong (seed dimatikan) lalu jalankan DatabaseSeeder.
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

    public function test_team_seeder_only_normalizes_alias_and_preserves_ketua_and_active_status(): void
    {
        $ketua = User::create([
            'nama' => 'Ketua Uji Seeder',
            'email' => 'ketua_seeder@contoh.test',
            'password' => 'password',
            'peran' => 'pegawai',
            'aktif' => true,
            'must_change_password' => false,
        ]);

        // Database uji sudah berisi tim kanonik dari DemoDataSeeder. Ubah tim
        // tersebut menjadi alias agar tidak ada dua tim Sosial yang bersaing.
        $team = Team::whereIn('nama', ['Tim Sosial', 'Sosial'])->first();

        if ($team) {
            $team->update([
                'nama' => 'Tim Sosial',
                'ketua_id' => $ketua->id,
                'aktif' => false,
            ]);
        } else {
            $team = Team::create([
                'nama' => 'Tim Sosial',
                'ketua_id' => $ketua->id,
                'aktif' => false,
            ]);
        }

        $this->seed(TeamSeeder::class);

        $team->refresh();

        $this->assertSame('Sosial', $team->nama);
        $this->assertSame($ketua->id, $team->ketua_id);
        $this->assertFalse($team->aktif);
    }

    public function test_user_seeder_does_not_overwrite_existing_password_or_change_flag(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        $admin->update([
            'password' => 'kata-sandi-khusus',
            'must_change_password' => true,
        ]);
        $passwordAfterChange = $admin->fresh()->password;

        $this->seed(UserSeeder::class);

        $admin->refresh();

        $this->assertSame($passwordAfterChange, $admin->password);
        $this->assertTrue($admin->must_change_password);
    }

    public function test_demo_data_seeder_does_not_overwrite_existing_demo_password_or_change_flag(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $ketua = User::where('email', 'ketua1@contoh.test')->firstOrFail();
        $ketua->update([
            'password' => 'kata-sandi-khusus',
            'must_change_password' => true,
        ]);
        $passwordAfterChange = $ketua->fresh()->password;

        $this->seed(DemoDataSeeder::class);

        $ketua->refresh();

        $this->assertSame($passwordAfterChange, $ketua->password);
        $this->assertTrue($ketua->must_change_password);
    }
}
