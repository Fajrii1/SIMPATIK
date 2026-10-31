<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->first();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $actSusenas = Activity::where('nama', 'like', '%Susenas%')->first();
        $actDDA = Activity::where('nama', 'like', '%Daerah Dalam Angka%')->first();

        $logs = [
            [
                'user_id' => $ketua1->id,
                'aksi' => 'create',
                'objek' => 'Activity',
                'objek_id' => $actSusenas->id,
                'sebelum' => null,
                'sesudah' => [
                    'nama' => $actSusenas->nama,
                    'mulai' => '2026-10-01',
                    'selesai' => '2026-11-15',
                    'bobot' => 1.50,
                    'kegiatan_besar' => true,
                ],
            ],
            [
                'user_id' => $admin->id,
                'aksi' => 'update',
                'objek' => 'Setting',
                'objek_id' => 1,
                'sebelum' => [
                    'kunci' => 'ambang_beban_bulanan',
                    'nilai' => '90',
                ],
                'sesudah' => [
                    'kunci' => 'ambang_beban_bulanan',
                    'nilai' => '100',
                ],
            ],
            [
                'user_id' => $ketua1->id,
                'aksi' => 'update',
                'objek' => 'Activity',
                'objek_id' => $actDDA->id,
                'sebelum' => [
                    'selesai_manual' => false,
                    'selesai_pada' => null,
                ],
                'sesudah' => [
                    'selesai_manual' => true,
                    'selesai_pada' => '2026-10-20 15:30:00',
                ],
            ],
            [
                'user_id' => $ketua1->id,
                'aksi' => 'create',
                'objek' => 'Assignment',
                'objek_id' => 1,
                'sebelum' => null,
                'sesudah' => [
                    'activity_id' => $actSusenas->id,
                    'user_id' => 3,
                    'peran' => 'Pencacah Lapangan',
                    'volume' => 60.00,
                    'satuan' => 'Dokumen',
                ],
            ],
        ];

        foreach ($logs as $log) {
            AuditLog::updateOrCreate(
                [
                    'user_id' => $log['user_id'],
                    'aksi' => $log['aksi'],
                    'objek' => $log['objek'],
                    'objek_id' => $log['objek_id'],
                ],
                $log
            );
        }
    }
}
