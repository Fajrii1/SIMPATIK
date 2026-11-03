<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    /**
     * Uji akses tanpa autentikasi ke seluruh endpoint manajemen pengguna ditolak (401).
     */
    public function test_unauthenticated_cannot_access_user_endpoints(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
        $this->postJson('/api/users', [])->assertStatus(401);
        $this->getJson('/api/users/1')->assertStatus(401);
        $this->putJson('/api/users/1', [])->assertStatus(401);
        $this->deleteJson('/api/users/1')->assertStatus(401);
        $this->patchJson('/api/users/1/status', [])->assertStatus(401);
        $this->patchJson('/api/users/1/reset-password', [])->assertStatus(401);
    }

    /**
     * Uji pengguna non-admin (pegawai & pimpinan) ditolak dari endpoint manajemen pengguna (403).
     */
    public function test_non_admin_cannot_access_user_management(): void
    {
        // Uji sebagai Pegawai
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        $this->assertNotNull($pegawai);

        // Pastikan must_change_password = false untuk pengetesan otorisasi
        $tempPegawai = User::create([
            'nama' => 'Pegawai Tester Auth',
            'email' => 'pegawai_test_auth@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($tempPegawai);

            $this->getJson('/api/users')->assertStatus(403);
            $this->postJson('/api/users', [
                'nama' => 'User Baru',
                'email' => 'baru@contoh.test',
                'peran' => 'pegawai',
            ])->assertStatus(403);
            $this->getJson("/api/users/{$pegawai->id}")->assertStatus(403);
            $this->putJson("/api/users/{$pegawai->id}", ['nama' => 'Ubah'])->assertStatus(403);
            $this->deleteJson("/api/users/{$pegawai->id}")->assertStatus(403);
            $this->patchJson("/api/users/{$pegawai->id}/status", ['aktif' => false])->assertStatus(403);
            $this->patchJson("/api/users/{$pegawai->id}/reset-password", [])->assertStatus(403);
        } finally {
            $tempPegawai->forceDelete();
        }
    }

    /**
     * Uji admin dapat melihat daftar pengguna dengan pagination, filter, dan pencarian.
     */
    public function test_admin_can_view_users_list_with_pagination_and_search(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $this->assertNotNull($admin);

        Sanctum::actingAs($admin);

        // 1. Ambil daftar default
        $response = $this->getJson('/api/users');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nama',
                        'email',
                        'peran',
                        'nip',
                        'jabatan',
                        'aktif',
                        'must_change_password',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonMissing(['password', 'remember_token']);

        // 2. Pencarian berdasarkan nama/email
        $searchResponse = $this->getJson('/api/users?search=Administrator');
        $searchResponse->assertStatus(200)
            ->assertJsonPath('data.0.email', 'admin@contoh.test');

        // 3. Filter berdasarkan peran
        $filterResponse = $this->getJson('/api/users?peran=pimpinan');
        $filterResponse->assertStatus(200);
        foreach ($filterResponse->json('data') as $userData) {
            $this->assertEquals('pimpinan', $userData['peran']);
        }
    }

    /**
     * Uji admin membuat pengguna baru dengan validasi dan aturan must_change_password.
     */
    public function test_admin_can_create_user_with_validation_and_must_change_password(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        // 1. Validasi input kosong
        $this->postJson('/api/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'email', 'peran']);

        // 2. Validasi duplikasi email
        $this->postJson('/api/users', [
            'nama' => 'Admin Klon',
            'email' => 'admin@contoh.test',
            'peran' => 'pegawai',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 3. Berhasil membuat pengguna baru
        $newEmail = 'pegawai_baru_fase2@contoh.test';
        $createResponse = $this->postJson('/api/users', [
            'nama' => 'Pegawai Baru Fase 2',
            'email' => $newEmail,
            'peran' => 'pegawai',
            'nip' => '199505052020011005',
            'jabatan' => 'Statistisi Pertama',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.email', $newEmail)
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissing(['password', 'remember_token']);

        $createdUser = User::where('email', $newEmail)->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue((bool) $createdUser->must_change_password);
        $this->assertTrue(Hash::check('password', $createdUser->password));

        // Bersihkan
        $createdUser->forceDelete();
    }

    /**
     * Uji admin dapat melihat detail satu pengguna.
     */
    public function test_admin_can_view_user_detail(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->first();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$pegawai->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $pegawai->id)
            ->assertJsonPath('data.email', 'pegawai01@contoh.test')
            ->assertJsonMissing(['password', 'remember_token']);
    }

    /**
     * Uji admin dapat memperbarui data pengguna lain.
     */
    public function test_admin_can_update_user(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        $tempUser = User::create([
            'nama' => 'User Untuk Diubah',
            'email' => 'user_ubah_test@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            $response = $this->putJson("/api/users/{$tempUser->id}", [
                'nama' => 'Nama Sudah Diperbarui',
                'jabatan' => 'Statistisi Muda',
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.nama', 'Nama Sudah Diperbarui')
                ->assertJsonPath('data.jabatan', 'Statistisi Muda');

            $tempUser->refresh();
            $this->assertEquals('Nama Sudah Diperbarui', $tempUser->nama);
            $this->assertEquals('Statistisi Muda', $tempUser->jabatan);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji admin tidak dapat menonaktifkan, mengubah peran, atau menghapus akun miliknya sendiri.
     */
    public function test_admin_cannot_deactivate_or_delete_own_account(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        // Menonaktifkan via PUT
        $this->putJson("/api/users/{$admin->id}", [
            'aktif' => false,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');

        // Mengubah peran via PUT
        $this->putJson("/api/users/{$admin->id}", [
            'peran' => 'pegawai',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat mengubah peran akun Anda sendiri.');

        // Menonaktifkan via PATCH /status
        $this->patchJson("/api/users/{$admin->id}/status", [
            'aktif' => false,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');

        // Menghapus akun via DELETE
        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat menghapus akun Anda sendiri.');
    }

    /**
     * Uji admin dapat menonaktifkan akun pengguna lain dan akun tersebut tidak bisa login.
     */
    public function test_admin_can_toggle_user_status_and_inactive_user_cannot_login(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();

        $tempUser = User::create([
            'nama' => 'User Nonaktif Test',
            'email' => 'user_nonaktif_test@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            // Nonaktifkan
            $response = $this->patchJson("/api/users/{$tempUser->id}/status", [
                'aktif' => false,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('user.aktif', false);

            $tempUser->refresh();
            $this->assertFalse((bool) $tempUser->aktif);

            // Coba login sebagai pengguna nonaktif -> harus ditolak 422
            $loginResponse = $this->postJson('/api/login', [
                'email' => 'user_nonaktif_test@contoh.test',
                'password' => 'password',
            ]);

            $loginResponse->assertStatus(422)
                ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji admin dapat mereset kata sandi pengguna secara manual.
     */
    public function test_admin_can_reset_user_password(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();

        $tempUser = User::create([
            'nama' => 'User Reset Sandi',
            'email' => 'user_reset_sandi@contoh.test',
            'password' => 'password-lama',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->patchJson("/api/users/{$tempUser->id}/reset-password", [
                'password' => 'sandi-sementara-123',
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('user.must_change_password', true)
                ->assertJsonMissing(['temporary_password']);

            $tempUser->refresh();
            $this->assertTrue((bool) $tempUser->must_change_password);
            $this->assertTrue(Hash::check('sandi-sementara-123', $tempUser->password));
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji admin dapat menghapus (soft delete) pengguna lain.
     */
    public function test_admin_can_soft_delete_user(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();

        $tempUser = User::create([
            'nama' => 'User Dihapus',
            'email' => 'user_dihapus@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->deleteJson("/api/users/{$tempUser->id}");
            $response->assertStatus(200)
                ->assertJsonPath('message', 'Pengguna berhasil dinonaktifkan/dihapus.');

            $this->assertSoftDeleted('users', ['id' => $tempUser->id]);
        } finally {
            $tempUser->forceDelete();
        }
    }
}
