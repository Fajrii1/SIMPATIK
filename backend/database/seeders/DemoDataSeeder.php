<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed data demo sesuai PRD bagian 17.
     * Idempoten: aman dijalankan berulang.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            TeamSeeder::class,
            SettingSeeder::class,
        ]);

        $password = Hash::make('password');

        // 1. Akun ketua tim (pegawai yang merangkap ketua)
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

        // 2. Akun pegawai
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

        // 3. Penetapan ketua dan anggota tim (nama tim baru)
        $kepala = User::where('email', 'kepala@contoh.test')->first();
        $ketua = [];
        for ($i = 1; $i <= 5; $i++) {
            $ketua[$i] = User::where('email', "ketua{$i}@contoh.test")->first();
        }
        $pegawai = [];
        for ($i = 1; $i <= 10; $i++) {
            $formattedIndex = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $pegawai[$i] = User::where('email', "pegawai{$formattedIndex}@contoh.test")->first();
        }

        $teamsDefinition = [
            [
                'nama' => 'Umum',
                'ketua_id' => $kepala?->id,
                'members' => ['pegawai01@contoh.test', 'pegawai03@contoh.test', 'pegawai10@contoh.test', 'kepala@contoh.test'],
            ],
            [
                'nama' => 'Sosial',
                'ketua_id' => $ketua[1]?->id,
                'members' => ['pegawai01@contoh.test', 'pegawai02@contoh.test', 'pegawai03@contoh.test', 'ketua1@contoh.test'],
            ],
            [
                'nama' => 'Produksi',
                'ketua_id' => $ketua[2]?->id,
                'members' => ['pegawai02@contoh.test', 'pegawai04@contoh.test', 'pegawai05@contoh.test', 'ketua2@contoh.test'],
            ],
            [
                'nama' => 'Distribusi',
                'ketua_id' => $ketua[3]?->id,
                'members' => ['pegawai05@contoh.test', 'pegawai06@contoh.test', 'pegawai07@contoh.test', 'ketua3@contoh.test'],
            ],
            [
                'nama' => 'NWAS',
                'ketua_id' => $ketua[4]?->id,
                'members' => ['pegawai07@contoh.test', 'pegawai08@contoh.test', 'pegawai09@contoh.test', 'ketua4@contoh.test'],
            ],
            [
                'nama' => 'IPDS',
                'ketua_id' => $ketua[5]?->id,
                'members' => ['pegawai09@contoh.test', 'pegawai10@contoh.test', 'ketua5@contoh.test'],
            ],
        ];

        foreach ($teamsDefinition as $data) {
            $team = Team::where('nama', $data['nama'])->first();
            if ($team) {
                $team->update(['ketua_id' => $data['ketua_id'], 'aktif' => true]);
                $userIds = User::whereIn('email', $data['members'])->pluck('id')->toArray();
                $team->members()->syncWithoutDetaching($userIds);
            }
        }

        // 4. Data kegiatan, penugasan, kendala, notifikasi, audit log
        $this->call([
            ActivitySeeder::class,
            AssignmentSeeder::class,
            WeeklyIssueSeeder::class,
            NotificationSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
