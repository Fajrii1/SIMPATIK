# HAK AKSES PER PERAN

Tabel ini menggambarkan hak akses sistem SIMPATIK berdasarkan kode saat ini (backend Laravel dan frontend React). Hak akses dicek di server (Laravel Policy dan Gate); frontend hanya menyesuaikan tampilan.

## Peran Sistem

Ada tiga peran sistem: **admin**, **pegawai**, dan **pimpinan**. **Ketua tim bukan peran tersendiri.** Ketua tim adalah pegawai atau pimpinan yang tercatat sebagai `teams.ketua_id`, dan haknya selalu berlaku hanya untuk tim yang dipimpinnya. Satu orang bisa memimpin lebih dari satu tim.

| Fitur | Admin | Pimpinan | Ketua Tim (timnya) | Pegawai |
|---|---|---|---|---|
| Kelola pengguna, tim, anggota, ketua tim | Ya | Tidak | Tidak | Tidak |
| Kelola pengaturan sistem | Ya | Tidak | Tidak | Tidak |
| Lihat riwayat perubahan (audit log) | Ya | Tidak | Tidak | Tidak |
| Buat dan ubah kegiatan | Ya (semua) | Tidak | Ya (timnya) | Tidak |
| Tandai kegiatan selesai / buka kembali | Ya | Tidak | Ya (timnya) | Tidak |
| Menugaskan pegawai | Ya | Tidak | Ya (kegiatan timnya) | Tidak |
| Lihat kalender | Ya | Ya | Ya (semua tim) | Ya (semua tim, hanya lihat) |
| Lihat total beban kerja bulanan semua pegawai | Ya | Ya | Ya (anggota timnya) | Tidak (hanya miliknya) |
| Lihat rincian penugasan seorang pegawai | Ya | Ya | Ya (anggota timnya dan penugasan di kegiatan timnya) | Ya (miliknya) |
| Isi dan ubah kendala mingguan | Ya | Tidak | Ya (timnya) | Bila pengaturan mengizinkan (kendala miliknya/timnya) |
| Ubah status kendala | Ya | Tidak | Ya (timnya) | Penanggung jawab kendala tersebut |
| Hapus kendala | Ya | Tidak | Ya (timnya) | Tidak |
| Dashboard | Seluruh kantor | Seluruh kantor | Timnya | Pribadi |
| Ekspor rekap (beban kerja, kegiatan, kendala) | Ya | Ya | Ya (timnya) | Tidak |
| Notifikasi di dalam aplikasi | Ya | Ya | Ya | Ya |
| Mengubah sandi sendiri | Ya | Ya | Ya | Ya |
| Wajib ganti sandi saat login pertama | Ya | Ya | Ya | Ya |

## Catatan Tambahan

- **Ketua tim** perlu melihat total beban seorang pegawai (angka dan warna) saat menugaskan agar tidak menumpuk, walau pegawai itu juga bertugas di tim lain. Rincian penugasan lintas tim hanya menampilkan nama kegiatan dan periode.
- **Pimpinan yang juga ketua tim** mendapat hak ketua tim untuk timnya saja.
- **Pimpinan** murni pemantau: tidak dapat membuat atau mengubah kegiatan, penugasan, atau kendala.
- **Akun baru** dibuat admin. Tidak ada pendaftaran mandiri. Akun baru berstatus `must_change_password` dan wajib ganti sandi saat login pertama.
- **Kendala selesai terkunci**: hanya admin atau ketua tim terkait yang dapat mengubah isi atau membuka kembali; PIC tidak dapat membuka kembali kendala yang sudah selesai.
- **Volume penugasan** (volume, satuan, poin) hanya terlihat oleh admin, pimpinan, ketua tim dari kegiatan tersebut, dan pemilik penugasan. Lainnya hanya melihat nama dan peran.
