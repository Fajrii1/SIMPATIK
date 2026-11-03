<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $production = app()->environment('production');

        $this->createIfMissing(
            'admin@contoh.test',
            [
                'nama' => 'Administrator BPS',
                'peran' => PeranPengguna::ADMIN,
                'nip' => '198001012005011001',
                'jabatan' => 'Pranata Komputer Ahli Muda',
                'aktif' => true,
            ],
            'SEED_ADMIN_PASSWORD',
            $production
        );

        // 2. Kepala (Pimpinan). Belum ada pegawai; ketua tim akan ditentukan
        //    nanti melalui admin setelah pegawai ditambahkan.
        $this->createIfMissing(
            'kepala@contoh.test',
            [
                'nama' => 'Kepala BPS Palembang',
                'peran' => PeranPengguna::PIMPINAN,
                'nip' => '197505121998031002',
                'jabatan' => 'Kepala BPS Kota Palembang',
                'aktif' => true,
            ],
            'SEED_KEPALA_PASSWORD',
            $production
        );
    }

    /**
     * Buat akun hanya bila email belum terdaftar.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createIfMissing(string $email, array $attributes, string $envKey, bool $production): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            array_merge($attributes, [
                'password' => Hash::make($this->resolvePassword($envKey, $production)),
                'must_change_password' => $production,
            ])
        );
    }

    /**
     * Ambil kata sandi awal dari environment saat production.
     */
    private function resolvePassword(string $envKey, bool $production): string
    {
        if (! $production) {
            return 'password';
        }

        $value = env($envKey);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Variabel lingkungan {$envKey} wajib diisi saat APP_ENV=production."
            );
        }

        return $value;
    }
}
