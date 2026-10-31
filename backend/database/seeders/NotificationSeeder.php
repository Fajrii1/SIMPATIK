<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $p01 = User::where('email', 'pegawai01@contoh.test')->first();
        $p02 = User::where('email', 'pegawai02@contoh.test')->first();
        $p03 = User::where('email', 'pegawai03@contoh.test')->first();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();

        $notifications = [
            // Belum dibaca
            [
                'user_id' => $p01->id,
                'tipe' => 'penugasan',
                'pesan' => 'Anda telah ditugaskan pada Survei Sosial Ekonomi Nasional (Susenas) Modul sebagai Pencacah Lapangan.',
                'tautan' => '/kegiatan/1',
                'dibaca_pada' => null,
            ],
            [
                'user_id' => $p02->id,
                'tipe' => 'kendala_jatuh_tempo',
                'pesan' => 'Peringatan: Kendala Keterlambatan pengembalian dokumen sensus telah melewati tenggat waktu.',
                'tautan' => '/kendala',
                'dibaca_pada' => null,
            ],
            [
                'user_id' => $p03->id,
                'tipe' => 'peringatan_beban',
                'pesan' => 'Jadwal Anda padat: Anda memiliki 3 penugasan beririsan pada bulan Oktober 2026.',
                'tautan' => '/beban-kerja',
                'dibaca_pada' => null,
            ],
            // Sudah dibaca
            [
                'user_id' => $p01->id,
                'tipe' => 'jadwal_berubah',
                'pesan' => 'Jadwal kegiatan Sakernas Triwulan III telah diperbarui oleh Ketua Tim Sosial.',
                'tautan' => '/kegiatan/4',
                'dibaca_pada' => '2026-10-25 09:30:00',
            ],
            [
                'user_id' => $ketua1->id,
                'tipe' => 'kendala_baru',
                'pesan' => 'Kendala baru dilaporkan oleh Pegawai 01 terkait kuesioner modul kesehatan.',
                'tautan' => '/kendala',
                'dibaca_pada' => '2026-10-20 10:15:00',
            ],
        ];

        foreach ($notifications as $notif) {
            Notification::updateOrCreate(
                [
                    'user_id' => $notif['user_id'],
                    'pesan' => $notif['pesan'],
                ],
                $notif
            );
        }
    }
}
