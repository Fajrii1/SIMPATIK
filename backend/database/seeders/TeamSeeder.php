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
        $kepala = User::where('email', 'kepala@contoh.test')->first();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $ketua2 = User::where('email', 'ketua2@contoh.test')->first();
        $ketua3 = User::where('email', 'ketua3@contoh.test')->first();
        $ketua4 = User::where('email', 'ketua4@contoh.test')->first();
        $ketua5 = User::where('email', 'ketua5@contoh.test')->first();

        // 6 Tim Kerja Statistik BPS Kota Palembang
        $teamsDefinition = [
            [
                'nama' => 'Tim Sosial',
                'ketua_id' => $ketua1?->id,
                'aktif' => true,
                'members' => ['pegawai01@contoh.test', 'pegawai02@contoh.test', 'pegawai03@contoh.test', 'ketua1@contoh.test'],
            ],
            [
                'nama' => 'Tim Produksi',
                'ketua_id' => $ketua2?->id,
                'aktif' => true,
                'members' => ['pegawai02@contoh.test', 'pegawai04@contoh.test', 'pegawai05@contoh.test', 'ketua2@contoh.test'],
            ],
            [
                'nama' => 'Tim Distribusi',
                'ketua_id' => $ketua3?->id,
                'aktif' => true,
                'members' => ['pegawai05@contoh.test', 'pegawai06@contoh.test', 'pegawai07@contoh.test', 'ketua3@contoh.test'],
            ],
            [
                'nama' => 'Tim Neraca dan Analisis',
                'ketua_id' => $ketua4?->id,
                'aktif' => true,
                'members' => ['pegawai07@contoh.test', 'pegawai08@contoh.test', 'pegawai09@contoh.test', 'ketua4@contoh.test'],
            ],
            [
                'nama' => 'Tim IPDS',
                'ketua_id' => $ketua5?->id,
                'aktif' => true,
                'members' => ['pegawai09@contoh.test', 'pegawai10@contoh.test', 'ketua5@contoh.test'],
            ],
            [
                'nama' => 'Tim Pelayanan Statistik',
                'ketua_id' => $kepala?->id, // Kepala merangkap ketua tim ini
                'aktif' => true,
                'members' => ['pegawai01@contoh.test', 'pegawai03@contoh.test', 'pegawai10@contoh.test', 'kepala@contoh.test'],
            ],
        ];

        foreach ($teamsDefinition as $data) {
            $team = Team::updateOrCreate(
                ['nama' => $data['nama']],
                [
                    'ketua_id' => $data['ketua_id'],
                    'aktif' => $data['aktif'],
                ]
            );

            // Sync keanggotaan tim
            $userIds = User::whereIn('email', $data['members'])->pluck('id')->toArray();
            $team->members()->syncWithoutDetaching($userIds);
        }
    }
}
