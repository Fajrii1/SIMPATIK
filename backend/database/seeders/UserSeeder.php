<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Admin
        User::updateOrCreate(
            ['email' => 'admin@contoh.test'],
            [
                'nama' => 'Administrator BPS',
                'password' => $password,
                'peran' => PeranPengguna::ADMIN,
                'nip' => '198001012005011001',
                'jabatan' => 'Pranata Komputer Ahli Muda',
                'aktif' => true,
                'must_change_password' => false,
            ]
        );

        // 2. Kepala (Pimpinan). Belum ada pegawai; ketua tim akan ditentukan
        //    nanti melalui admin setelah pegawai ditambahkan.
        User::updateOrCreate(
            ['email' => 'kepala@contoh.test'],
            [
                'nama' => 'Kepala BPS Palembang',
                'password' => $password,
                'peran' => PeranPengguna::PIMPINAN,
                'nip' => '197505121998031002',
                'jabatan' => 'Kepala BPS Kota Palembang',
                'aktif' => true,
                'must_change_password' => false,
            ]
        );
    }
}
