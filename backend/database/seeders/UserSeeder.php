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

        // 2. Kepala (Pimpinan, juga ketua satu tim)
        User::updateOrCreate(
            ['email' => 'kepala@contoh.test'],
            [
                'nama' => 'Kepala BPS Palembang',
                'password' => $password,
                'peran' => PeranPengguna::PIMPINAN,
                'nip' => '197505121998031002',
                'jabatan' => 'Kepala BPS Kota Palembang',
                'aktif' => true,
                // Akun demo langsung dapat dipakai; akun buatan admin tetap wajib ganti sandi.
                'must_change_password' => false,
            ]
        );

        // 3. Ketua Tim 1 s.d. 5 (Pegawai yang memimpin tim)
        $ketuaData = [
            1 => ['nama' => 'Ketua Tim 01', 'jabatan' => 'Statistisi Ahli Muda'],
            2 => ['nama' => 'Ketua Tim 02', 'jabatan' => 'Statistisi Ahli Muda'],
            3 => ['nama' => 'Ketua Tim 03', 'jabatan' => 'Statistisi Ahli Muda'],
            4 => ['nama' => 'Ketua Tim 04', 'jabatan' => 'Statistisi Ahli Pertama'],
            5 => ['nama' => 'Ketua Tim 05', 'jabatan' => 'Pranata Komputer Ahli Muda'],
        ];

        foreach ($ketuaData as $i => $data) {
            User::updateOrCreate(
                ['email' => "ketua{$i}@contoh.test"],
                [
                    'nama' => $data['nama'],
                    'password' => $password,
                    'peran' => PeranPengguna::PEGAWAI,
                    'nip' => '1985010'.$i.'200801100'.$i,
                    'jabatan' => $data['jabatan'],
                    'aktif' => true,
                    'must_change_password' => false,
                ]
            );
        }

        // 4. Pegawai 01 s.d. 10
        for ($i = 1; $i <= 10; $i++) {
            $formattedIndex = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            User::updateOrCreate(
                ['email' => "pegawai{$formattedIndex}@contoh.test"],
                [
                    'nama' => "Pegawai {$formattedIndex}",
                    'password' => $password,
                    'peran' => PeranPengguna::PEGAWAI,
                    'nip' => '199002'.$formattedIndex.'20150210'.$formattedIndex,
                    'jabatan' => 'Statistisi Terampil',
                    'aktif' => true,
                    'must_change_password' => false,
                ]
            );
        }
    }
}
