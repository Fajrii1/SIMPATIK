<?php

namespace Database\Seeders;

use App\Enums\StatusKendala;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyIssue;
use Illuminate\Database\Seeder;

class WeeklyIssueSeeder extends Seeder
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

        $ketua1 = User::where('email', 'ketua1@contoh.test')->first();
        $ketua2 = User::where('email', 'ketua2@contoh.test')->first();
        $ketua3 = User::where('email', 'ketua3@contoh.test')->first();
        $ketua4 = User::where('email', 'ketua4@contoh.test')->first();
        $ketua5 = User::where('email', 'ketua5@contoh.test')->first();
        $p01 = User::where('email', 'pegawai01@contoh.test')->first();
        $p02 = User::where('email', 'pegawai02@contoh.test')->first();
        $p06 = User::where('email', 'pegawai06@contoh.test')->first();
        $p08 = User::where('email', 'pegawai08@contoh.test')->first();
        $p10 = User::where('email', 'pegawai10@contoh.test')->first();

        $actSusenas = Activity::where('nama', 'like', '%Susenas%')->first();
        $actPertanian = Activity::where('nama', 'like', '%Pertanian Terpadu%')->first();
        $actHarga = Activity::where('nama', 'like', '%Harga Konsumen%')->first();
        $actMfd = Activity::where('nama', 'like', '%Master File Desa%')->first();

        $issues = [
            // 1. Kasus Uji Kendala Terbawa (Carried Over) dari minggu lalu (19 Okt) ke minggu ini (26 Okt)
            [
                'activity_id' => $actSusenas->id,
                'team_id' => $sosial->id,
                'pelapor_id' => $ketua1->id,
                'minggu_mulai' => '2026-10-19',
                'kendala' => 'Responden rumah tangga pada klaster Ilir Barat II sulit ditemui pada jam kerja reguler.',
                'solusi' => 'Penjadwalan ulang pencacahan pada malam hari atau akhir pekan.',
                'tindak_lanjut' => 'Koordinasi dengan ketua RT setempat untuk pemberitahuan kunjungan.',
                'pic_id' => $p01->id,
                'tenggat' => '2026-10-31',
                'status' => StatusKendala::DIPROSES,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 2. Kasus Uji Kendala Jatuh Tempo (Overdue) - tenggat 23 Okt, belum selesai
            [
                'activity_id' => $actPertanian->id,
                'team_id' => $produksi->id,
                'pelapor_id' => $ketua2->id,
                'minggu_mulai' => '2026-10-12',
                'kendala' => 'Keterlambatan pengembalian dokumen sensus dari pengawas kecamatan Gandus.',
                'solusi' => 'Penjemputan langsung dokumen oleh tim verifikasi kantor.',
                'tindak_lanjut' => 'Kunjungan jemput bola ke lokasi pengawas.',
                'pic_id' => $p02->id,
                'tenggat' => '2026-10-23',
                'status' => StatusKendala::TERBUKA,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 3. Kasus Kendala Selesai dengan Catatan Penutupan
            [
                'activity_id' => $actHarga->id,
                'team_id' => $distribusi->id,
                'pelapor_id' => $ketua3->id,
                'minggu_mulai' => '2026-10-12',
                'kendala' => 'Perbedaan satuan komoditas beras lokal pada pedagang Pasar 16 Ilir.',
                'solusi' => 'Konversi standar timbangan digital ke kilogram standar BPS.',
                'tindak_lanjut' => 'Pengadaan tabel konversi satuan lokal untuk pencacah.',
                'pic_id' => $p06->id,
                'tenggat' => '2026-10-18',
                'status' => StatusKendala::SELESAI,
                'catatan_penutupan' => 'Tabel konversi telah didistribusikan ke seluruh petugas pasar dan data berhasil diharmonisasi.',
                'ditutup_pada' => '2026-10-18 16:00:00',
            ],

            // 4. Kendala Umum Tim (activity_id NULL) pada Tim IPDS
            [
                'activity_id' => null,
                'team_id' => $ipds->id,
                'pelapor_id' => $ketua5->id,
                'minggu_mulai' => '2026-10-26',
                'kendala' => 'Koneksi jaringan VPN internal BPS lambat saat proses sinkronisasi server pusat.',
                'solusi' => 'Pengalihan jalur routing bandwidth lokal dan tiket perbaikan ke Pusdatin.',
                'tindak_lanjut' => 'Monitoring stabilisasi bandwith hingga akhir bulan.',
                'pic_id' => $p10->id,
                'tenggat' => '2026-10-30',
                'status' => StatusKendala::TERBUKA,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 5. Kendala Minggu Berjalan di Tim Neraca
            [
                'activity_id' => null,
                'team_id' => $neraca->id,
                'pelapor_id' => $ketua4->id,
                'minggu_mulai' => '2026-10-26',
                'kendala' => 'Laporan realisasi anggaran OPD Triwulan III dari BPKAD belum seluruhnya terkumpul.',
                'solusi' => 'Surat permohonan susulan percepatan data belanja modal.',
                'tindak_lanjut' => 'Audiensi langsung ke kantor BPKAD Palembang.',
                'pic_id' => $p08->id,
                'tenggat' => '2026-11-03',
                'status' => StatusKendala::DIPROSES,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 6. Kendala Spasial MFD (Tim IPDS)
            [
                'activity_id' => $actMfd->id,
                'team_id' => $ipds->id,
                'pelapor_id' => $p10->id,
                'minggu_mulai' => '2026-10-19',
                'kendala' => 'Pemekaran RT baru di Kelurahan Sukamaju belum memiliki batas peta digital definitif.',
                'solusi' => 'Pelacakan batas lapangan bersama kasi pemerintahan kelurahan.',
                'tindak_lanjut' => 'Geotagging titik kartometrik.',
                'pic_id' => $p10->id,
                'tenggat' => '2026-10-29',
                'status' => StatusKendala::DIPROSES,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 7. Kendala Umum Tim Pelayanan Statistik
            [
                'activity_id' => null,
                'team_id' => $pelayanan->id,
                'pelapor_id' => $ketua1->id,
                'minggu_mulai' => '2026-10-26',
                'kendala' => 'Buku tamu elektronik PST mengalami glitch saat pencetakan antrean otomatis.',
                'solusi' => 'Restart service printer thermal dan pembaruan driver lokal.',
                'tindak_lanjut' => 'Pemeriksaan kabel antarmuka USB printer.',
                'pic_id' => $p10->id,
                'tenggat' => '2026-10-29',
                'status' => StatusKendala::TERBUKA,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 8. Kendala Selesai di Tim Sosial
            [
                'activity_id' => $actSusenas->id,
                'team_id' => $sosial->id,
                'pelapor_id' => $p01->id,
                'minggu_mulai' => '2026-10-05',
                'kendala' => 'Kekurangan kelengkapan kuesioner cetak modul kesehatan.',
                'solusi' => 'Permintaan tambahan blok dokumen cadangan ke gudang.',
                'tindak_lanjut' => 'Distribusi ulang kuesioner ke 4 petugas lapangan.',
                'pic_id' => $ketua1->id,
                'tenggat' => '2026-10-08',
                'status' => StatusKendala::SELESAI,
                'catatan_penutupan' => 'Dokumen cadangan lengkap dan telah diterima petugas.',
                'ditutup_pada' => '2026-10-08 11:00:00',
            ],

            // 9. Kendala Pengolahan Tim Produksi
            [
                'activity_id' => $actPertanian->id,
                'team_id' => $produksi->id,
                'pelapor_id' => $p02->id,
                'minggu_mulai' => '2026-10-19',
                'kendala' => 'Ketidaksesuaian kode komoditas tanaman biofarmaka pada aplikasi entri.',
                'solusi' => 'Update master tabel kodifikasi komoditas.',
                'tindak_lanjut' => 'Sosialisasi perubahan kode kepada seluruh operator entri.',
                'pic_id' => $ketua2->id,
                'tenggat' => '2026-10-28',
                'status' => StatusKendala::DIPROSES,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 10. Kendala Diseminasi Tim IPDS
            [
                'activity_id' => null,
                'team_id' => $ipds->id,
                'pelapor_id' => $ketua5->id,
                'minggu_mulai' => '2026-10-12',
                'kendala' => 'Permintaan data mikro dari akademisi memerlukan persetujuan komite etik.',
                'solusi' => 'Penyusunan format pakta integritas dan surat komitmen kerahasiaan responden.',
                'tindak_lanjut' => 'Penandatanganan dokumen perjanjian pinjam pakai data mikro.',
                'pic_id' => $ketua5->id,
                'tenggat' => '2026-10-19',
                'status' => StatusKendala::SELESAI,
                'catatan_penutupan' => 'Pakta integritas telah ditandatangani pemohon.',
                'ditutup_pada' => '2026-10-19 14:00:00',
            ],

            // 11. Kendala Tim Distribusi
            [
                'activity_id' => $actHarga->id,
                'team_id' => $distribusi->id,
                'pelapor_id' => $ketua3->id,
                'minggu_mulai' => '2026-10-26',
                'kendala' => 'Salah satu responden pedagang grosir menolak memberikan data volume penjualan.',
                'solusi' => 'Pendekatan persuasif dan penjelasan UU Statistik No. 16 Tahun 1997.',
                'tindak_lanjut' => 'Kunjungan bersama ketua tim ke pemilik usaha.',
                'pic_id' => $ketua3->id,
                'tenggat' => '2026-11-02',
                'status' => StatusKendala::TERBUKA,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],

            // 12. Kendala Tim Neraca
            [
                'activity_id' => null,
                'team_id' => $neraca->id,
                'pelapor_id' => $p08->id,
                'minggu_mulai' => '2026-10-26',
                'kendala' => 'Perbedaan tahun dasar perhitungan inflasi komparatif.',
                'solusi' => 'Rekonsiliasi menggunakan deflator PDRB tahun rujukan 2010.',
                'tindak_lanjut' => 'Sinkronisasi metodologi dengan BPS Provinsi Sumsel.',
                'pic_id' => $ketua4->id,
                'tenggat' => '2026-11-05',
                'status' => StatusKendala::TERBUKA,
                'catatan_penutupan' => null,
                'ditutup_pada' => null,
            ],
        ];

        foreach ($issues as $issue) {
            WeeklyIssue::updateOrCreate(
                [
                    'team_id' => $issue['team_id'],
                    'minggu_mulai' => $issue['minggu_mulai'],
                    'kendala' => $issue['kendala'],
                ],
                $issue
            );
        }
    }
}
