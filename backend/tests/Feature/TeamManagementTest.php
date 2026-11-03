<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke seluruh endpoint tim kerja ditolak (401).
     */
    public function test_unauthenticated_cannot_access_team_endpoints(): void
    {
        $this->getJson('/api/teams')->assertStatus(401);
        $this->postJson('/api/teams', [])->assertStatus(401);
        $this->getJson('/api/teams/1')->assertStatus(401);
        $this->putJson('/api/teams/1', [])->assertStatus(401);
        $this->deleteJson('/api/teams/1')->assertStatus(401);
        $this->getJson('/api/teams/1/members')->assertStatus(401);
        $this->postJson('/api/teams/1/members', [])->assertStatus(401);
        $this->deleteJson('/api/teams/1/members/1')->assertStatus(401);
    }

    /**
     * Uji seluruh pengguna terautentikasi (pegawai & pimpinan) dapat melihat daftar tim dan anggota.
     */
    public function test_all_authenticated_roles_can_view_teams_and_members(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Viewer Tim',
            'email' => 'pegawai_viewer@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $firstTeam = Team::first();
        $this->assertNotNull($firstTeam);

        try {
            Sanctum::actingAs($tempPegawai);

            // 1. Melihat daftar tim
            $indexResponse = $this->getJson('/api/teams');
            $indexResponse->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'nama',
                            'ketua_id',
                            'aktif',
                        ],
                    ],
                ]);

            // 2. Melihat detail tim
            $showResponse = $this->getJson("/api/teams/{$firstTeam->id}");
            $showResponse->assertStatus(200)
                ->assertJsonPath('data.id', $firstTeam->id);

            // 3. Melihat anggota tim
            $membersResponse = $this->getJson("/api/teams/{$firstTeam->id}/members");
            $membersResponse->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'nama',
                            'email',
                            'peran',
                        ],
                    ],
                ]);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji pengguna non-admin tidak dapat membuat, memperbarui, atau menghapus tim dan anggotanya (403).
     */
    public function test_non_admin_cannot_modify_teams_or_members(): void
    {
        $tempPegawai = User::create([
            'nama' => 'Pegawai Mutator Fail',
            'email' => 'pegawai_mutator@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $firstTeam = Team::first();
        $this->assertNotNull($firstTeam);

        try {
            Sanctum::actingAs($tempPegawai);

            $this->postJson('/api/teams', ['nama' => 'Tim Palsu'])->assertStatus(403);
            $this->putJson("/api/teams/{$firstTeam->id}", ['nama' => 'Ubah Nama'])->assertStatus(403);
            $this->deleteJson("/api/teams/{$firstTeam->id}")->assertStatus(403);
            $this->postJson("/api/teams/{$firstTeam->id}/members", ['user_id' => $tempPegawai->id])->assertStatus(403);
            $this->deleteJson("/api/teams/{$firstTeam->id}/members/{$tempPegawai->id}")->assertStatus(403);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji admin dapat membuat tim kerja baru dengan validasi.
     */
    public function test_admin_can_create_team_with_validation(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $ketua = User::where('email', 'ketua1@contoh.test')->first();

        Sanctum::actingAs($admin);

        // Validasi nama kosong
        $this->postJson('/api/teams', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama']);

        // Sukses membuat tim
        $response = $this->postJson('/api/teams', [
            'nama' => 'Tim Khusus Riset Statistik',
            'ketua_id' => $ketua->id,
            'aktif' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama', 'Tim Khusus Riset Statistik')
            ->assertJsonPath('data.ketua_id', $ketua->id);

        $teamId = $response->json('data.id');
        $createdTeam = Team::find($teamId);
        $this->assertNotNull($createdTeam);

        // Verifikasi ketua otomatis menjadi anggota tim
        $this->assertTrue($createdTeam->members()->where('user_id', $ketua->id)->exists());

        // Bersihkan
        $createdTeam->members()->detach();
        $createdTeam->forceDelete();
    }

    /**
     * Uji pembuatan tim gagal jika ketua yang dipilih tidak aktif.
     */
    public function test_admin_cannot_assign_inactive_user_as_team_ketua(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();

        $inactiveUser = User::create([
            'nama' => 'Calon Ketua Nonaktif',
            'email' => 'calon_ketua_nonaktif@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => false,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->postJson('/api/teams', [
                'nama' => 'Tim Calon Nonaktif',
                'ketua_id' => $inactiveUser->id,
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('message', 'Ketua tim yang dipilih harus merupakan pengguna yang aktif.');
        } finally {
            $inactiveUser->forceDelete();
        }
    }

    /**
     * Uji admin dapat memperbarui data tim dan mengganti ketua tim.
     */
    public function test_admin_can_update_team_and_change_ketua(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $ketua2 = User::where('email', 'ketua2@contoh.test')->first();

        $tempTeam = Team::create([
            'nama' => 'Tim Uji Pembaruan',
            'ketua_id' => $ketua1->id,
            'aktif' => true,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->putJson("/api/teams/{$tempTeam->id}", [
                'nama' => 'Tim Uji Pembaruan Diedit',
                'ketua_id' => $ketua2->id,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.nama', 'Tim Uji Pembaruan Diedit')
                ->assertJsonPath('data.ketua_id', $ketua2->id);

            $tempTeam->refresh();
            $this->assertEquals('Tim Uji Pembaruan Diedit', $tempTeam->nama);
            $this->assertEquals($ketua2->id, $tempTeam->ketua_id);
            // Verifikasi ketua baru otomatis masuk ke members
            $this->assertTrue($tempTeam->members()->where('user_id', $ketua2->id)->exists());
        } finally {
            $tempTeam->members()->detach();
            $tempTeam->forceDelete();
        }
    }

    public function test_designated_ketua_becomes_member_on_store_and_update(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $ketuaStore = User::where('email', 'ketua1@contoh.test')->first();
        $ketuaUpdate = User::where('email', 'ketua2@contoh.test')->first();

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/teams', [
            'nama' => 'Tim Uji Ketua Otomatis',
            'ketua_id' => $ketuaStore->id,
        ]);

        $createResponse->assertStatus(201);
        $teamId = $createResponse->json('data.id');
        $team = Team::findOrFail($teamId);

        try {
            $this->assertTrue($team->members()->where('user_id', $ketuaStore->id)->exists());

            $this->putJson("/api/teams/{$team->id}", [
                'ketua_id' => $ketuaUpdate->id,
            ])->assertStatus(200);

            $this->assertTrue($team->members()->where('user_id', $ketuaUpdate->id)->exists());
        } finally {
            $team->members()->detach();
            $team->forceDelete();
        }
    }

    /**
     * Uji admin dapat menambahkan anggota ke tim dan mencegah duplikasi.
     */
    public function test_admin_can_add_member_and_prevent_duplicate_membership(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();

        $tempTeam = Team::create([
            'nama' => 'Tim Uji Anggota',
            'aktif' => true,
        ]);

        try {
            Sanctum::actingAs($admin);

            // Tambah pertama kali -> 201
            $response = $this->postJson("/api/teams/{$tempTeam->id}/members", [
                'user_id' => $pegawai->id,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('message', 'Anggota berhasil ditambahkan ke dalam tim.');

            $this->assertTrue($tempTeam->members()->where('user_id', $pegawai->id)->exists());

            // Tambah kedua kali (duplikasi) -> 422
            $dupResponse = $this->postJson("/api/teams/{$tempTeam->id}/members", [
                'user_id' => $pegawai->id,
            ]);

            $dupResponse->assertStatus(422)
                ->assertJsonPath('message', 'Pengguna sudah terdaftar sebagai anggota pada tim ini.');
        } finally {
            $tempTeam->members()->detach();
            $tempTeam->forceDelete();
        }
    }

    /**
     * Uji admin dapat menghapus anggota dari tim kerja dan mencabut ketua_id jika anggota tersebut ketua.
     */
    public function test_admin_can_remove_member_and_clears_ketua_if_applicable(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $ketua = User::where('email', 'ketua1@contoh.test')->first();

        $tempTeam = Team::create([
            'nama' => 'Tim Uji Hapus Anggota',
            'ketua_id' => $ketua->id,
            'aktif' => true,
        ]);
        $tempTeam->members()->attach($ketua->id);

        try {
            Sanctum::actingAs($admin);

            $response = $this->deleteJson("/api/teams/{$tempTeam->id}/members/{$ketua->id}");
            $response->assertStatus(200)
                ->assertJsonPath('message', 'Anggota berhasil dihapus dari tim kerja.');

            $tempTeam->refresh();
            $this->assertFalse($tempTeam->members()->where('user_id', $ketua->id)->exists());
            // Karena ketua dihapus dari tim, jabatan ketua_id harus otomatis dinolkan (null)
            $this->assertNull($tempTeam->ketua_id);
        } finally {
            $tempTeam->members()->detach();
            $tempTeam->forceDelete();
        }
    }

    /**
     * Uji admin dapat menghapus (soft delete) tim kerja.
     */
    public function test_admin_can_soft_delete_team(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();

        $tempTeam = Team::create([
            'nama' => 'Tim Uji Hapus Tim',
            'aktif' => true,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->deleteJson("/api/teams/{$tempTeam->id}");
            $response->assertStatus(200)
                ->assertJsonPath('message', 'Tim berhasil dihapus.');

            $this->assertSoftDeleted('teams', ['id' => $tempTeam->id]);
        } finally {
            $tempTeam->forceDelete();
        }
    }
}
