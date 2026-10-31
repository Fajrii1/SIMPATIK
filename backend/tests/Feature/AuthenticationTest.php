<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Bersihkan rate limiter untuk email pengujian
        RateLimiter::clear('admin@contoh.test|127.0.0.1');
        RateLimiter::clear('throttle-test@contoh.test|127.0.0.1');

        parent::tearDown();
    }

    /**
     * Uji validasi input saat login kosong.
     */
    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Uji format email yang tidak valid.
     */
    public function test_login_validation_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'bukan-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Uji login berhasil dengan kredensial benar.
     */
    public function test_login_successful_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@contoh.test',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'nama',
                    'email',
                    'peran',
                    'nip',
                    'jabatan',
                    'aktif',
                    'must_change_password',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'message' => 'Login berhasil.',
                'user' => [
                    'email' => 'admin@contoh.test',
                    'peran' => 'admin',
                    'aktif' => true,
                ],
            ])
            ->assertJsonMissing([
                'password',
                'remember_token',
            ]);
    }

    /**
     * Uji login gagal jika kata sandi salah.
     */
    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@contoh.test',
            'password' => 'katasandisalah',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');
    }

    /**
     * Uji login gagal jika email tidak terdaftar (tanpa membocorkan keberadaan email).
     */
    public function test_login_fails_with_unregistered_email_without_leaking_existence(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'tidak_ada_di_database@contoh.test',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');
    }

    /**
     * Uji login ditolak jika akun pengguna dinonaktifkan (aktif = false).
     */
    public function test_login_rejected_for_inactive_user(): void
    {
        $inactiveUser = User::create([
            'nama' => 'Pengguna Nonaktif Sementara',
            'email' => 'nonaktif_test@contoh.test',
            'password' => 'password',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011099',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => false,
            'must_change_password' => false,
        ]);

        try {
            $response = $this->postJson('/api/login', [
                'email' => 'nonaktif_test@contoh.test',
                'password' => 'password',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email'])
                ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');
        } finally {
            $inactiveUser->forceDelete();
        }
    }

    /**
     * Uji rate limiting percobaan login (maksimal 5 kali percobaan gagal).
     */
    public function test_login_rate_limiting_triggers_after_max_attempts(): void
    {
        $throttleEmail = 'throttle-test@contoh.test';

        // 5 percobaan gagal awal
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $throttleEmail,
                'password' => 'wrong-pass',
            ]);
            $response->assertStatus(422);
        }

        // Percobaan ke-6 harus ditolak dengan status 429
        $response = $this->postJson('/api/login', [
            'email' => $throttleEmail,
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(429)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Uji akses tanpa autentikasi ke endpoint terproteksi ditolak (401).
     */
    public function test_unauthenticated_access_to_protected_endpoints_is_rejected(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->postJson('/api/logout')->assertStatus(401);
        $this->postJson('/api/change-password')->assertStatus(401);
    }

    /**
     * Uji endpoint /api/me untuk pengguna terautentikasi yang sudah mengganti sandi.
     */
    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $this->assertNotNull($admin);
        $this->assertFalse((bool) $admin->must_change_password);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'nama',
                    'email',
                    'peran',
                    'nip',
                    'jabatan',
                    'aktif',
                    'must_change_password',
                ],
            ])
            ->assertJson([
                'user' => [
                    'email' => 'admin@contoh.test',
                    'peran' => 'admin',
                ],
            ])
            ->assertJsonMissing([
                'password',
                'remember_token',
            ]);
    }

    /**
     * Uji pengguna dengan kewajiban must_change_password diblokir dari endpoint /api/me (403).
     */
    public function test_user_with_must_change_password_is_blocked_from_me_endpoint(): void
    {
        $kepala = User::where('email', 'kepala@contoh.test')->first();
        $this->assertNotNull($kepala);
        $this->assertTrue((bool) $kepala->must_change_password);

        Sanctum::actingAs($kepala);

        $response = $this->getJson('/api/me');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda harus mengganti kata sandi terlebih dahulu.',
                'must_change_password' => true,
            ]);
    }

    /**
     * Uji alur pergantian kata sandi untuk pengguna must_change_password:
     * 1. Pengguna wajib ganti sandi diizinkan akses /api/change-password.
     * 2. Setelah berhasil ganti sandi, must_change_password menjadi false.
     * 3. Pengguna kini diizinkan mengakses /api/me.
     */
    public function test_must_change_password_flow_and_subsequent_access(): void
    {
        $tempUser = User::create([
            'nama' => 'Pengguna Uji Sandi',
            'email' => 'uji_ganti_sandi@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011088',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            Sanctum::actingAs($tempUser);

            // Akses /me sebelum ganti kata sandi -> 403
            $this->getJson('/api/me')->assertStatus(403);

            // Ganti kata sandi dengan data valid
            $response = $this->postJson('/api/change-password', [
                'current_password' => 'password-awal',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Kata sandi berhasil diubah.',
                    'user' => [
                        'must_change_password' => false,
                    ],
                ]);

            // Verifikasi di database
            $tempUser->refresh();
            $this->assertFalse((bool) $tempUser->must_change_password);
            $this->assertTrue(Hash::check('password-baru-123', $tempUser->password));

            // Sekarang akses /me berhasil (200)
            $meResponse = $this->getJson('/api/me');
            $meResponse->assertStatus(200)
                ->assertJsonPath('user.email', 'uji_ganti_sandi@contoh.test')
                ->assertJsonPath('user.must_change_password', false);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji validasi saat pergantian kata sandi dengan kata sandi saat ini yang keliru.
     */
    public function test_change_password_fails_if_current_password_is_incorrect(): void
    {
        $tempUser = User::create([
            'nama' => 'Pengguna Uji Validasi Sandi',
            'email' => 'uji_sandi_salah@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011077',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            Sanctum::actingAs($tempUser);

            $response = $this->postJson('/api/change-password', [
                'current_password' => 'password-salah',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji validasi saat kata sandi baru kurang dari 8 karakter.
     */
    public function test_change_password_fails_if_new_password_is_too_short(): void
    {
        $tempUser = User::create([
            'nama' => 'Pengguna Uji Sandi Pendek',
            'email' => 'uji_sandi_pendek@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011066',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            Sanctum::actingAs($tempUser);

            $response = $this->postJson('/api/change-password', [
                'current_password' => 'password-awal',
                'password' => '12345',
                'password_confirmation' => '12345',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji validasi saat konfirmasi kata sandi tidak cocok.
     */
    public function test_change_password_fails_if_confirmation_does_not_match(): void
    {
        $tempUser = User::create([
            'nama' => 'Pengguna Uji Konfirmasi Beda',
            'email' => 'uji_sandi_beda@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011055',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            Sanctum::actingAs($tempUser);

            $response = $this->postJson('/api/change-password', [
                'current_password' => 'password-awal',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-berbeda-999',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji validasi saat kata sandi baru sama dengan kata sandi saat ini.
     */
    public function test_change_password_fails_if_new_password_same_as_current(): void
    {
        $tempUser = User::create([
            'nama' => 'Pengguna Uji Sandi Sama',
            'email' => 'uji_sandi_sama@contoh.test',
            'password' => 'password-awal',
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => '199999992020011044',
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
        ]);

        try {
            Sanctum::actingAs($tempUser);

            $response = $this->postJson('/api/change-password', [
                'current_password' => 'password-awal',
                'password' => 'password-awal',
                'password_confirmation' => 'password-awal',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        } finally {
            $tempUser->forceDelete();
        }
    }

    /**
     * Uji logout pengguna terautentikasi.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Berhasil keluar.',
            ]);
    }
}
