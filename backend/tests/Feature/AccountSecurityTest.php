<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Team;
use App\Models\User;
use App\Services\UserSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    /**
     * Uji pengguna nonaktif yang masih memiliki sesi ditolak 401.
     */
    public function test_inactive_user_with_existing_session_is_rejected(): void
    {
        $inactive = User::create([
            'nama' => 'Pengguna Nonaktif Bersesi',
            'email' => 'nonaktif_beressi@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => false,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($inactive);

            $this->getJson('/api/me')
                ->assertStatus(401)
                ->assertJsonPath('message', 'Akun Anda dinonaktifkan.');

            $this->getJson('/api/dashboard')
                ->assertStatus(401)
                ->assertJsonPath('message', 'Akun Anda dinonaktifkan.');
        } finally {
            $inactive->forceDelete();
        }
    }

    /**
     * Uji reset sandi tanpa input menghasilkan sandi sementara acak sekali tampil.
     */
    public function test_reset_password_without_input_returns_random_temporary_password(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();

        $user = User::create([
            'nama' => 'Pengguna Sandi Acak',
            'email' => 'sandi_acak@contoh.test',
            'password' => 'password-lama',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            Sanctum::actingAs($admin);

            $response = $this->patchJson("/api/users/{$user->id}/reset-password");

            $response->assertStatus(200)
                ->assertJsonPath('user.must_change_password', true);

            $temporary = $response->json('temporary_password');

            $this->assertIsString($temporary);
            $this->assertSame(12, strlen($temporary));
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $temporary);
            $this->assertMatchesRegularExpression('/[0-9]/', $temporary);
            $this->assertDoesNotMatchRegularExpression('/[0Oo1lI]/', $temporary);

            $user->refresh();

            $this->assertTrue(Hash::check($temporary, $user->password));
            $this->assertFalse(Hash::check('password-lama', $user->password));
            $this->assertTrue((bool) $user->must_change_password);

            Sanctum::actingAs($user->fresh());
            $this->getJson('/api/dashboard')
                ->assertStatus(403)
                ->assertJsonPath('must_change_password', true);
        } finally {
            $user->forceDelete();
        }
    }

    /**
     * Uji reset sandi memutus sesi pengguna pada driver database.
     */
    public function test_reset_password_removes_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();

        $user = User::create([
            'nama' => 'Pengguna Sesi Reset',
            'email' => 'sesi_reset@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $otherUser = User::where('email', 'pegawai01@contoh.test')->firstOrFail();

        try {
            $this->insertSession('sesi-reset-1', $user->id);
            $this->insertSession('sesi-reset-2', $user->id);
            $this->insertSession('sesi-orang-lain', $otherUser->id);

            Sanctum::actingAs($admin);
            $this->patchJson("/api/users/{$user->id}/reset-password")->assertStatus(200);

            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-reset-1']);
            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-reset-2']);
            $this->assertDatabaseHas('sessions', ['id' => 'sesi-orang-lain']);
        } finally {
            DB::table('sessions')->whereIn('id', ['sesi-reset-1', 'sesi-reset-2', 'sesi-orang-lain'])->delete();
            $user->forceDelete();
        }
    }

    /**
     * Uji penonaktifan akun memutus sesi dan tercatat di audit log.
     */
    public function test_deactivating_user_removes_sessions_and_is_audited(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();

        $user = User::create([
            'nama' => 'Pengguna Sesi Nonaktif',
            'email' => 'sesi_nonaktif@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        try {
            $this->insertSession('sesi-nonaktif', $user->id);

            Sanctum::actingAs($admin);
            $this->patchJson("/api/users/{$user->id}/status", ['aktif' => false])
                ->assertStatus(200)
                ->assertJsonPath('user.aktif', false);

            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-nonaktif']);
        } finally {
            DB::table('sessions')->where('id', 'sesi-nonaktif')->delete();
            $user->forceDelete();
        }
    }

    /**
     * Uji penghapusan pengguna yang menjadi ketua mengosongkan ketua dan memutus sesi.
     */
    public function test_deleting_ketua_user_clears_ketua_audits_and_removes_sessions(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();

        $user = User::create([
            'nama' => 'Ketua Yang Dihapus',
            'email' => 'ketua_dihapus@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => false,
        ]);

        $team = Team::create([
            'nama' => 'Tim Uji Hapus Ketua',
            'ketua_id' => $user->id,
            'aktif' => true,
        ]);
        $team->members()->attach($user->id);

        try {
            $this->insertSession('sesi-hapus-ketua', $user->id);

            Sanctum::actingAs($admin);
            $this->deleteJson("/api/users/{$user->id}")
                ->assertStatus(200)
                ->assertJsonPath('message', 'Pengguna berhasil dinonaktifkan/dihapus. Ketua tim dikosongkan.');

            $team->refresh();

            $this->assertNull($team->ketua_id);
            $this->assertSoftDeleted('users', ['id' => $user->id]);
            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hapus-ketua']);

        } finally {
            DB::table('sessions')->where('id', 'sesi-hapus-ketua')->delete();
            $team->members()->detach();
            $team->forceDelete();
            $user->forceDelete();
        }
    }

    /**
     * Uji pergantian sandi sendiri menghapus sesi lain pada driver database.
     */
    public function test_change_password_removes_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::create([
            'nama' => 'Pengguna Ganti Sandi Sesi',
            'email' => 'ganti_sandi_sesi@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'aktif' => true,
            'must_change_password' => true,
        ]);

        $otherUser = User::where('email', 'pegawai01@contoh.test')->firstOrFail();

        try {
            $this->insertSession('sesi-ganti-1', $user->id);
            $this->insertSession('sesi-ganti-2', $user->id);
            $this->insertSession('sesi-ganti-lain', $otherUser->id);

            Sanctum::actingAs($user);

            $this->postJson('/api/change-password', [
                'current_password' => 'password-awal',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])->assertStatus(200);

            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-ganti-1']);
            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-ganti-2']);
            $this->assertDatabaseHas('sessions', ['id' => 'sesi-ganti-lain']);
        } finally {
            DB::table('sessions')->whereIn('id', ['sesi-ganti-1', 'sesi-ganti-2', 'sesi-ganti-lain'])->delete();
            $user->forceDelete();
        }
    }

    /**
     * Uji layanan sesi mempertahankan sesi yang sedang dipakai.
     */
    public function test_session_service_keeps_current_session(): void
    {
        config(['session.driver' => 'database']);

        $user = User::where('email', 'pegawai01@contoh.test')->firstOrFail();

        try {
            $this->insertSession('sesi-sekarang', $user->id);
            $this->insertSession('sesi-lama', $user->id);

            $deleted = app(UserSessionService::class)->invalidate($user->id, 'sesi-sekarang');

            $this->assertSame(1, $deleted);
            $this->assertDatabaseHas('sessions', ['id' => 'sesi-sekarang']);
            $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lama']);
        } finally {
            DB::table('sessions')->whereIn('id', ['sesi-sekarang', 'sesi-lama'])->delete();
        }
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
