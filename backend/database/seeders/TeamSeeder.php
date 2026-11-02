<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Enam tim kerja statistik BPS Kota Palembang (nama kanonik).
        // Seeder development hanya membuat tim, tanpa ketua dan anggota;
        // ketua tim ditentukan admin setelah pegawai ditambahkan.
        $teamsDefinition = [
            ['nama' => 'Umum', 'aliases' => ['Tim Pelayanan Statistik', 'Umum']],
            ['nama' => 'Sosial', 'aliases' => ['Tim Sosial', 'Sosial']],
            ['nama' => 'Produksi', 'aliases' => ['Tim Produksi', 'Produksi']],
            ['nama' => 'Distribusi', 'aliases' => ['Tim Distribusi', 'Distribusi']],
            ['nama' => 'NWAS', 'aliases' => ['Tim Neraca dan Analisis', 'NWAS']],
            ['nama' => 'IPDS', 'aliases' => ['Tim IPDS', 'IPDS']],
        ];

        foreach ($teamsDefinition as $data) {
            $team = Team::whereIn('nama', $data['aliases'])->first();

            if ($team) {
                $team->update([
                    'nama' => $data['nama'],
                    'ketua_id' => null,
                    'aktif' => true,
                ]);
            } else {
                Team::create([
                    'nama' => $data['nama'],
                    'ketua_id' => null,
                    'aktif' => true,
                ]);
            }
        }
    }
}
