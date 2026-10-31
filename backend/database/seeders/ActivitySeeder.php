<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sosial = Team::where('nama', 'Tim Sosial')->first();
        $produksi = Team::where('nama', 'Tim Produksi')->first();
        $distribusi = Team::where('nama', 'Tim Distribusi')->first();
        $neraca = Team::where('nama', 'Tim Neraca dan Analisis')->first();
        $ipds = Team::where('nama', 'Tim IPDS')->first();
        $pelayanan = Team::where('nama', 'Tim Pelayanan Statistik')->first();

        $activities = [
            // 1. Kasus Utama Contoh Uji PRD 8.1 & Kegiatan Besar 1
            [
                'nama' => 'Survei Sosial Ekonomi Nasional (Susenas) Modul',
                'jenis' => 'Survei',
                'team_id' => $sosial->id,
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-15',
                'bobot' => 1.50,
                'kegiatan_besar' => true,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Kasus uji perhitungan bobot 1.50 dan alokasi proporsional Oktober-November.',
            ],
            // 2. Kegiatan Besar 2 (Beririsan dengan Susenas)
            [
                'nama' => 'Survei Pertanian Terpadu Tahap II',
                'jenis' => 'Survei',
                'team_id' => $produksi->id,
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-20',
                'bobot' => 1.30,
                'kegiatan_besar' => true,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Kegiatan besar kedua yang beririsan untuk uji peringatan beban kantor.',
            ],
            // 3. Kegiatan Besar 3 (Beririsan dengan 1 & 2)
            [
                'nama' => 'Survei Harga Konsumen dan Perdagangan Besar',
                'jenis' => 'Survei',
                'team_id' => $distribusi->id,
                'mulai' => '2026-10-10',
                'selesai' => '2026-11-25',
                'bobot' => 1.25,
                'kegiatan_besar' => true,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Kegiatan besar ketiga beririsan di bulan Oktober-November.',
            ],
            // 4. Kasus Uji Terlambat (lewat selesai, selesai_manual = false)
            [
                'nama' => 'Survei Angkatan Kerja Nasional (Sakernas) Triwulan III',
                'jenis' => 'Survei',
                'team_id' => $sosial->id,
                'mulai' => '2026-09-01',
                'selesai' => '2026-10-15',
                'bobot' => 1.20,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Kasus uji status Terlambat otomatis.',
            ],
            // 5. Kasus Uji Selesai Manual (selesai_manual = true)
            [
                'nama' => 'Penyusunan Publikasi Daerah Dalam Angka',
                'jenis' => 'Diseminasi',
                'team_id' => $ipds->id,
                'mulai' => '2026-08-01',
                'selesai' => '2026-10-20',
                'bobot' => 1.00,
                'kegiatan_besar' => false,
                'selesai_manual' => true,
                'selesai_pada' => '2026-10-20 15:30:00',
                'keterangan' => 'Kasus uji status Selesai dengan penutupan manual.',
            ],
            // 6. Kasus Uji Belum Mulai (mulai di masa depan)
            [
                'nama' => 'Survei Kepuasan Konsumen Akhir Tahun',
                'jenis' => 'Survei',
                'team_id' => $pelayanan->id,
                'mulai' => '2026-11-10',
                'selesai' => '2026-12-10',
                'bobot' => 1.00,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Kasus uji status Belum mulai.',
            ],
            // 7. Kegiatan Berjalan di Tim Neraca
            [
                'nama' => 'Penyusunan PDRB Triwulan III Menurut Lapangan Usaha',
                'jenis' => 'Pengolahan Data',
                'team_id' => $neraca->id,
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-05',
                'bobot' => 1.15,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Pengolahan data rutin triwulanan.',
            ],
            // 8. Kegiatan Berjalan di Tim Produksi
            [
                'nama' => 'Survei Industri Manufaktur Besar dan Sedang',
                'jenis' => 'Survei',
                'team_id' => $produksi->id,
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-15',
                'bobot' => 1.00,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Pencacahan industri bulanan.',
            ],
            // 9. Kegiatan Berjalan di Tim Distribusi
            [
                'nama' => 'Survei Pariwisata dan Akomodasi Perhotelan',
                'jenis' => 'Survei',
                'team_id' => $distribusi->id,
                'mulai' => '2026-10-12',
                'selesai' => '2026-11-08',
                'bobot' => 1.00,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Monitoring okupansi kamar hotel.',
            ],
            // 10. Kegiatan Berjalan di Tim IPDS
            [
                'nama' => 'Pemutakhiran Master File Desa (MFD)',
                'jenis' => 'Pengolahan Data',
                'team_id' => $ipds->id,
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-12',
                'bobot' => 1.10,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Validasi batas spasial wilayah administrasi.',
            ],
            // 11. Kegiatan Berjalan di Tim Pelayanan Statistik
            [
                'nama' => 'Pelayanan Konsultasi Statistik Terpadu (PST)',
                'jenis' => 'Pembinaan',
                'team_id' => $pelayanan->id,
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-31',
                'bobot' => 1.00,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Layanan rujukan data untuk mahasiswa dan OPD.',
            ],
            // 12. Kegiatan Koordinasi Lintas Tim
            [
                'nama' => 'Rapat Evaluasi Kegiatan Statistik Triwulan III',
                'jenis' => 'Rapat dan Koordinasi',
                'team_id' => $sosial->id,
                'mulai' => '2026-10-25',
                'selesai' => '2026-10-30',
                'bobot' => 0.80,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Evaluasi kendala lapangan akhir triwulan.',
            ],
            // 13. Kegiatan Neraca Pengeluaran
            [
                'nama' => 'Kompilasi Tabel Input-Output Kota Palembang',
                'jenis' => 'Pengolahan Data',
                'team_id' => $neraca->id,
                'mulai' => '2026-10-18',
                'selesai' => '2026-11-30',
                'bobot' => 1.40,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Analisis keterkaitan antar sektor ekonomi.',
            ],
            // 14. Kegiatan Pembinaan Statistik Sektoral
            [
                'nama' => 'Pembinaan Desa Cantik (Cinta Statistik)',
                'jenis' => 'Pembinaan',
                'team_id' => $pelayanan->id,
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-15',
                'bobot' => 1.10,
                'kegiatan_besar' => false,
                'selesai_manual' => false,
                'selesai_pada' => null,
                'keterangan' => 'Pendampingan aparatur kelurahan dalam pemanfaatan data statistik.',
            ],
        ];

        foreach ($activities as $act) {
            Activity::updateOrCreate(
                ['nama' => $act['nama']],
                $act
            );
        }
    }
}
