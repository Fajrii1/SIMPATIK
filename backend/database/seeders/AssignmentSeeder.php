<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Users
        $p01 = User::where('email', 'pegawai01@contoh.test')->first();
        $p02 = User::where('email', 'pegawai02@contoh.test')->first();
        $p03 = User::where('email', 'pegawai03@contoh.test')->first();
        $p04 = User::where('email', 'pegawai04@contoh.test')->first();
        $p05 = User::where('email', 'pegawai05@contoh.test')->first();
        $p06 = User::where('email', 'pegawai06@contoh.test')->first();
        $p07 = User::where('email', 'pegawai07@contoh.test')->first();
        $p08 = User::where('email', 'pegawai08@contoh.test')->first();
        $p09 = User::where('email', 'pegawai09@contoh.test')->first();
        $p10 = User::where('email', 'pegawai10@contoh.test')->first();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $ketua2 = User::where('email', 'ketua2@contoh.test')->first();
        $ketua3 = User::where('email', 'ketua3@contoh.test')->first();
        $ketua4 = User::where('email', 'ketua4@contoh.test')->first();
        $ketua5 = User::where('email', 'ketua5@contoh.test')->first();
        $kepala = User::where('email', 'kepala@contoh.test')->first();

        // Activities
        $actSusenas = Activity::where('nama', 'like', '%Susenas%')->first();
        $actPertanian = Activity::where('nama', 'like', '%Pertanian Terpadu%')->first();
        $actHarga = Activity::where('nama', 'like', '%Harga Konsumen%')->first();
        $actSakernas = Activity::where('nama', 'like', '%Sakernas%')->first();
        $actDDA = Activity::where('nama', 'like', '%Daerah Dalam Angka%')->first();
        $actKepuasan = Activity::where('nama', 'like', '%Kepuasan Konsumen%')->first();
        $actPdrb = Activity::where('nama', 'like', '%PDRB Triwulan%')->first();
        $actIndustri = Activity::where('nama', 'like', '%Industri Manufaktur%')->first();
        $actPariwisata = Activity::where('nama', 'like', '%Pariwisata%')->first();
        $actMfd = Activity::where('nama', 'like', '%Master File Desa%')->first();
        $actPst = Activity::where('nama', 'like', '%Pelayanan Konsultasi%')->first();
        $actRapat = Activity::where('nama', 'like', '%Rapat Evaluasi%')->first();
        $actIo = Activity::where('nama', 'like', '%Input-Output%')->first();
        $actDesaCantik = Activity::where('nama', 'like', '%Desa Cantik%')->first();

        $assignments = [
            // 1. Kasus Uji Wajib PRD Bagian 8.1 (Pegawai 01: volume 60, bobot 1.5, 2026-10-01 s.d. 2026-11-15)
            [
                'activity_id' => $actSusenas->id,
                'user_id' => $p01->id,
                'peran' => 'Pencacah Lapangan',
                'volume' => 60.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-15',
            ],

            // 2 & 3. Pegawai 02: Sengaja melewati ambang beban bulanan (October beban >= 100)
            [
                'activity_id' => $actPertanian->id,
                'user_id' => $p02->id,
                'peran' => 'Pencacah Utama',
                'volume' => 70.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-10',
            ],
            [
                'activity_id' => $actIndustri->id,
                'user_id' => $p02->id,
                'peran' => 'Editor / Pengawas',
                'volume' => 35.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-15',
                'selesai' => '2026-10-31',
            ],

            // 4, 5, 6. Pegawai 03: Tiga (3) penugasan beririsan di bulan Oktober (Uji Peringatan Jadwal Menumpuk PRD 8.2)
            [
                'activity_id' => $actSusenas->id,
                'user_id' => $p03->id,
                'peran' => 'Pencacah Lapangan',
                'volume' => 20.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-25',
            ],
            [
                'activity_id' => $actPst->id,
                'user_id' => $p03->id,
                'peran' => 'Petugas Front Office',
                'volume' => 22.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-31',
            ],
            [
                'activity_id' => $actDesaCantik->id,
                'user_id' => $p03->id,
                'peran' => 'Fasilitator Desa',
                'volume' => 15.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-10',
                'selesai' => '2026-10-30',
            ],

            // 7 & 8. Satu pegawai dengan 2 peran berbeda pada kegiatan yang sama (PRD Bagian 8.4)
            [
                'activity_id' => $actSusenas->id,
                'user_id' => $ketua1->id,
                'peran' => 'Pengawas Lapangan (PML)',
                'volume' => 30.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-15',
            ],
            [
                'activity_id' => $actSusenas->id,
                'user_id' => $ketua1->id,
                'peran' => 'Pemeriksa Administrasi',
                'volume' => 10.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-10',
            ],

            // 9. Kasus penugasan dengan periode kosong (fallback ke periode kegiatan)
            [
                'activity_id' => $actDDA->id,
                'user_id' => $p10->id,
                'peran' => 'Desain Tata Letak Publikasi',
                'volume' => 25.00,
                'satuan' => 'Laporan',
                'mulai' => null, // fallback
                'selesai' => null, // fallback
            ],

            // 10. Penugasan pada kegiatan Sakernas (terlambat)
            [
                'activity_id' => $actSakernas->id,
                'user_id' => $p01->id,
                'peran' => 'Pencacah Cadangan',
                'volume' => 15.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-09-01',
                'selesai' => '2026-10-15',
            ],

            // 11 s.d. 30: Penugasan tambahan untuk melengkapi variasi beban 30 item
            [
                'activity_id' => $actPertanian->id,
                'user_id' => $ketua2->id,
                'peran' => 'Pengawas Mutu Data',
                'volume' => 25.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-20',
            ],
            [
                'activity_id' => $actPertanian->id,
                'user_id' => $p04->id,
                'peran' => 'Pencacah Blok Sensus',
                'volume' => 40.00,
                'satuan' => 'Blok Sensus',
                'mulai' => '2026-10-10',
                'selesai' => '2026-11-15',
            ],
            [
                'activity_id' => $actPertanian->id,
                'user_id' => $p05->id,
                'peran' => 'Pencacah Blok Sensus',
                'volume' => 45.00,
                'satuan' => 'Blok Sensus',
                'mulai' => '2026-10-10',
                'selesai' => '2026-11-15',
            ],
            [
                'activity_id' => $actHarga->id,
                'user_id' => $ketua3->id,
                'peran' => 'Koordinator Pencacahan',
                'volume' => 30.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-10',
                'selesai' => '2026-11-25',
            ],
            [
                'activity_id' => $actHarga->id,
                'user_id' => $p06->id,
                'peran' => 'Pencacah Pasar Tradisional',
                'volume' => 50.00,
                'satuan' => 'Responden',
                'mulai' => '2026-10-10',
                'selesai' => '2026-11-20',
            ],
            [
                'activity_id' => $actHarga->id,
                'user_id' => $p07->id,
                'peran' => 'Pencacah Pasar Modern',
                'volume' => 40.00,
                'satuan' => 'Responden',
                'mulai' => '2026-10-12',
                'selesai' => '2026-11-20',
            ],
            [
                'activity_id' => $actPdrb->id,
                'user_id' => $ketua4->id,
                'peran' => 'Kompilator Neraca Wilayah',
                'volume' => 30.00,
                'satuan' => 'Laporan',
                'mulai' => '2026-10-01',
                'selesai' => '2026-11-05',
            ],
            [
                'activity_id' => $actPdrb->id,
                'user_id' => $p08->id,
                'peran' => 'Pengolah Data Sektor Jasa',
                'volume' => 20.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-02',
            ],
            [
                'activity_id' => $actPdrb->id,
                'user_id' => $p09->id,
                'peran' => 'Pengolah Data Sektor Manufaktur',
                'volume' => 25.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-02',
            ],
            [
                'activity_id' => $actIndustri->id,
                'user_id' => $p04->id,
                'peran' => 'Pencacah Lapangan',
                'volume' => 15.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-10',
            ],
            [
                'activity_id' => $actPariwisata->id,
                'user_id' => $p05->id,
                'peran' => 'Pencacah Hotel Berbintang',
                'volume' => 18.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-12',
                'selesai' => '2026-11-05',
            ],
            [
                'activity_id' => $actPariwisata->id,
                'user_id' => $p06->id,
                'peran' => 'Pencacah Restoran dan Objek Wisata',
                'volume' => 20.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-12',
                'selesai' => '2026-11-05',
            ],
            [
                'activity_id' => $actMfd->id,
                'user_id' => $ketua5->id,
                'peran' => 'Verifikator Batas Wilayah',
                'volume' => 16.00,
                'satuan' => 'Laporan',
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-12',
            ],
            [
                'activity_id' => $actMfd->id,
                'user_id' => $p10->id,
                'peran' => 'Operator GIS dan Spasial',
                'volume' => 35.00,
                'satuan' => 'Dokumen',
                'mulai' => '2026-10-15',
                'selesai' => '2026-11-12',
            ],
            [
                'activity_id' => $actPst->id,
                'user_id' => $kepala->id,
                'peran' => 'Konsultan Senior Statistik',
                'volume' => 10.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-31',
            ],
            [
                'activity_id' => $actRapat->id,
                'user_id' => $ketua1->id,
                'peran' => 'Penyaji Evaluasi Tim Sosial',
                'volume' => 5.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-25',
                'selesai' => '2026-10-30',
            ],
            [
                'activity_id' => $actRapat->id,
                'user_id' => $ketua2->id,
                'peran' => 'Penyaji Evaluasi Tim Produksi',
                'volume' => 5.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-25',
                'selesai' => '2026-10-30',
            ],
            [
                'activity_id' => $actIo->id,
                'user_id' => $p07->id,
                'peran' => 'Kompilator Matriks Antar Industri',
                'volume' => 20.00,
                'satuan' => 'Laporan',
                'mulai' => '2026-10-18',
                'selesai' => '2026-11-25',
            ],
            [
                'activity_id' => $actIo->id,
                'user_id' => $p08->id,
                'peran' => 'Analisis Koefisien Input',
                'volume' => 22.00,
                'satuan' => 'Laporan',
                'mulai' => '2026-10-18',
                'selesai' => '2026-11-25',
            ],
            [
                'activity_id' => $actDesaCantik->id,
                'user_id' => $p09->id,
                'peran' => 'Instruktur Pendamping Kelurahan',
                'volume' => 12.00,
                'satuan' => 'Hari',
                'mulai' => '2026-10-05',
                'selesai' => '2026-11-10',
            ],
            [
                'activity_id' => $actKepuasan->id,
                'user_id' => $p10->id,
                'peran' => 'Pencacah Pengunjung PST',
                'volume' => 30.00,
                'satuan' => 'Responden',
                'mulai' => '2026-11-10',
                'selesai' => '2026-12-10',
            ],
        ];

        foreach ($assignments as $asn) {
            Assignment::updateOrCreate(
                [
                    'activity_id' => $asn['activity_id'],
                    'user_id' => $asn['user_id'],
                    'peran' => $asn['peran'],
                ],
                $asn
            );
        }
    }
}
