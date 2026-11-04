# Panduan Uji Manual di Browser

Dokumen ini berisi daftar periksa uji manual per peran. Centang setiap langkah setelah hasilnya sesuai harapan. Dokumen ini tidak menyatakan bahwa pengujian sudah dijalankan.

Prasyarat umum:

- Backend berjalan (`php artisan serve`) dan frontend berjalan (`npm run dev`).
- Database yang dipakai adalah database pengembangan, bukan `jadwal_bps_test`.
- Siapkan akun uji untuk admin, pimpinan, ketua tim, dan pegawai biasa.
- Gunakan browser dengan mode penyamaran agar sesi antar peran tidak tercampur.

---

## 1. Admin

- [ ] **Login pertama dan ganti sandi.** Masuk dengan akun admin yang masih berstatus wajib ganti sandi. Harapan: diarahkan ke halaman ganti sandi dan tidak bisa membuka menu lain sebelum sandi diganti. Setelah sandi baru disimpan, dashboard terbuka.
- [ ] **Menu admin lengkap.** Periksa sidebar. Harapan: menu Dashboard, Kalender Kegiatan, Daftar Kegiatan, Monitoring Beban Kerja, Kendala Mingguan, Laporan & Ekspor, Manajemen Pengguna & Tim, Pengaturan Sistem, dan Audit Log tampil.
- [ ] **Tambah kegiatan lewat form baru.** Buka Daftar Kegiatan, klik tambah. Isi nama, tim, tanggal, dan baris penugasan. Harapan: form meminta tepat satu Penanggung Jawab dan menolak simpan bila belum ada.
- [ ] **Pratinjau beban kerja.** Sebelum menyimpan, periksa pratinjau beban per pegawai. Harapan: angka sebelum/sesudah, indikator, dan peringatan tampil tanpa menyimpan kegiatan lebih dulu.
- [ ] **Batas volume terlihat.** Buka detail kegiatan yang memiliki beberapa penugasan. Harapan: admin melihat volume, satuan, dan poin semua penugasan.
- [ ] **Kalender baca saja.** Buka Kalender Kegiatan. Harapan: tidak ada tombol tambah/ubah/hapus kegiatan. Klik satu kegiatan lalu tautan "Buka di Daftar Kegiatan"; harapan: berpindah ke `/activities?id={id}` dan detail kegiatan terbuka.
- [ ] **Kendala: tambah, detail, riwayat, edit, kunci.** Tambah kendala, buka detailnya, ubah tenggat dan PIC, lalu ubah status menjadi selesai dengan catatan penutupan. Harapan: riwayat menampilkan perubahan field (misalnya "Tenggat: ... → ..."), dan kendala selesai terkunci untuk PIC.
- [ ] **Notifikasi.** Buka halaman Notifikasi setelah ada penugasan atau perubahan jadwal. Harapan: notifikasi baru muncul dan dapat ditandai dibaca.
- [ ] **Ekspor PDF, CSV, dan XLSX.** Dari Laporan & Ekspor, unduh laporan beban kerja, kegiatan, dan kendala dalam tiga format. Harapan: berkas terunduh, dapat dibuka, dan isinya memuat judul, kolom, serta baris data yang wajar.
- [ ] **Label poin dashboard.** Buka Dashboard dan lihat tabel "Penugasan Aktif Anda". Harapan: kolom poin berlabel "Total poin" dan nilainya bukan 0 untuk penugasan yang memiliki volume.
- [ ] **Detail beban kerja lintas bulan.** Buka Monitoring Beban Kerja, pilih pegawai yang penugasannya membentang lebih dari tiga bulan, lalu buka rincian. Harapan: penugasan tetap muncul pada jendela bulan yang dipilih dan tren bulanan konsisten dengan daftar penugasan.
- [ ] **Sidebar.** Klik area kosong sidebar untuk memperkecil, klik lagi untuk memperbesar. Klik tautan menu, tombol, dan dropdown di dalam sidebar. Harapan: hanya klik area kosong yang mengubah lebar sidebar; klik kontrol tidak ikut men-toggle.

## 2. Pimpinan

- [ ] **Login dan ganti sandi.** Masuk sebagai pimpinan. Harapan: berhasil masuk dan dashboard pemantau tampil.
- [ ] **Menu yang tampil.** Periksa sidebar. Harapan: tidak ada menu Manajemen Pengguna & Tim, Pengaturan Sistem, maupun Audit Log. Menu Laporan & Ekspor tetap tampil.
- [ ] **Kegiatan hanya baca.** Buka Daftar Kegiatan dan detail kegiatan. Harapan: tidak ada tombol tambah, ubah, atau hapus yang dapat dipakai.
- [ ] **Volume terlihat.** Buka detail kegiatan. Harapan: pimpinan melihat volume, satuan, dan poin seluruh penugasan.
- [ ] **Kalender baca saja.** Buka Kalender Kegiatan dan klik satu kegiatan. Harapan: tidak ada editor kegiatan dan tautan "Buka di Daftar Kegiatan" berfungsi.
- [ ] **Kendala hanya baca.** Buka Kendala Mingguan dan detail kendala. Harapan: pimpinan tidak dapat menambah atau mengubah kendala.
- [ ] **Notifikasi.** Buka halaman Notifikasi. Harapan: notifikasi yang ditujukan ke pimpinan tampil dan dapat ditandai dibaca.
- [ ] **Ekspor.** Unduh laporan beban kerja, kegiatan, dan kendala dalam PDF, CSV, dan XLSX. Harapan: semua berkas terunduh dan isinya sesuai filter yang dipilih.
- [ ] **Monitoring beban kerja.** Buka Monitoring Beban Kerja dan rincian seorang pegawai. Harapan: data seluruh pegawai tampil dan tren bulanan dapat dibaca.
- [ ] **Sidebar.** Perkecil dan perbesar sidebar lewat area kosong, lalu klik beberapa tautan menu. Harapan: navigasi tetap berjalan dan sidebar tidak ikut men-toggle saat tautan diklik.

## 3. Ketua Tim

- [ ] **Login dan ganti sandi.** Masuk sebagai ketua tim. Harapan: berhasil masuk dan dashboard menampilkan ringkasan tim yang dipimpin.
- [ ] **Menu yang tampil.** Periksa sidebar. Harapan: menu Laporan & Ekspor tampil, sedangkan menu admin tidak tampil.
- [ ] **Tambah kegiatan tim sendiri.** Buat kegiatan untuk tim yang dipimpin dengan form baru. Harapan: tepat satu Penanggung Jawab wajib diisi dan kegiatan tersimpan.
- [ ] **Tolak kegiatan tim lain.** Coba membuat atau mengubah kegiatan tim lain lewat URL atau permintaan langsung. Harapan: ditolak dengan pesan tidak berwenang, bukan error mentah.
- [ ] **Pratinjau beban.** Isi beberapa penugasan lalu periksa pratinjau. Harapan: peringatan tumpang tindih dan beban sebelum/sesudah tampil sebelum simpan.
- [ ] **Batas volume.** Buka detail kegiatan tim sendiri dan kegiatan tim lain. Harapan: volume terlihat pada kegiatan tim sendiri, sedangkan metrik penugasan di kegiatan tim lain hanya menampilkan nama dan peran.
- [ ] **Kalender baca saja.** Buka kalender dan tautan "Buka di Daftar Kegiatan". Harapan: kalender tidak memiliki editor dan tautan membuka detail yang benar.
- [ ] **Kendala tim.** Tambah kendala tim, buka detail, ubah tenggat dan PIC, lalu tutup kendala. Harapan: riwayat menampilkan perubahan field dan kendala selesai terkunci.
- [ ] **Monitoring beban kerja anggota tim.** Buka Monitoring Beban Kerja. Harapan: hanya anggota tim yang dipimpin yang tampil, beserta tren bulanannya.
- [ ] **Notifikasi peringatan.** Setelah mengubah tanggal kegiatan sehingga tiga penugasan bertumpuk, periksa Notifikasi. Harapan: peringatan penugasan bersamaan diterima tanpa duplikat saat disimpan berulang.
- [ ] **Ekspor tim.** Unduh laporan untuk tim yang dipimpin. Harapan: hanya data tim tersebut yang muncul.
- [ ] **Sidebar.** Perkecil dan perbesar sidebar lewat area kosong serta tombol header. Harapan: keduanya berfungsi dan klik tautan menu tidak men-toggle sidebar.

## 4. Pegawai Biasa

- [ ] **Login dan ganti sandi.** Masuk sebagai pegawai biasa. Harapan: berhasil masuk dan dashboard pribadi tampil.
- [ ] **Menu yang tampil.** Periksa sidebar. Harapan: menu Laporan & Ekspor dan menu admin tidak tampil.
- [ ] **Kegiatan hanya baca.** Buka Daftar Kegiatan dan detail kegiatan. Harapan: tidak ada tombol tambah, ubah, atau hapus yang dapat dipakai.
- [ ] **Metrik milik sendiri.** Buka detail kegiatan yang memuat penugasan milik sendiri. Harapan: volume, satuan, dan poin milik sendiri terlihat.
- [ ] **Metrik rekan disembunyikan.** Pada kegiatan yang sama, periksa penugasan rekan. Harapan: volume, satuan, dan poin rekan bernilai kosong dan hanya nama serta peran yang tampil.
- [ ] **Kalender baca saja.** Buka Kalender Kegiatan. Harapan: kalender hanya untuk melihat dan tautan "Buka di Daftar Kegiatan" berfungsi.
- [ ] **Kendala sesuai pengaturan.** Bila pengaturan mengizinkan, tambah kendala untuk tim sendiri. Bila tidak, tombol tambah tidak tampil atau permintaan ditolak dengan pesan yang wajar.
- [ ] **Notifikasi.** Buka Notifikasi setelah menerima penugasan. Harapan: notifikasi penugasan baru tampil dan dapat ditandai dibaca.
- [ ] **Agenda dashboard.** Buka Dashboard dan periksa tabel agenda. Harapan: kolom "Total poin" menampilkan volume x bobot, bukan 0.
- [ ] **Sidebar.** Klik area kosong sidebar, tautan menu, dan tombol di dalam sidebar. Harapan: hanya area kosong yang men-toggle, navigasi tetap berjalan.

---

## 5. Tampilan 360px

- [ ] **Modal form kegiatan.** Buka Daftar Kegiatan di lebar 360px, buka form tambah kegiatan. Harapan: modal dapat digulir, tombol simpan/batal terjangkau, dan tidak ada isian yang terpotong.
- [ ] **Tabel.** Buka Monitoring Beban Kerja dan Daftar Kegiatan. Harapan: tabel berubah menjadi kartu atau dapat digulir horizontal tanpa memotong teks penting.
- [ ] **Kalender.** Buka Kalender Kegiatan. Harapan: kalender dapat digulir dan detail kegiatan tetap terbaca tanpa elemen yang saling menimpa.
- [ ] **Drawer sidebar.** Buka drawer lewat tombol hamburger. Harapan: drawer muncul dari kiri, latar gelap menutupi konten, tombol tutup berfungsi, dan tautan menu menutup drawer.
- [ ] **Tombol sentuh.** Periksa tombol utama (simpan, tutup, hamburger, notifikasi). Harapan: ukuran sentuh memadai dan tidak ada tombol yang terpotong.

## 6. Uji Dua Perangkat (Penonaktifan Akun)

- [ ] **Sesi aktif di perangkat A.** Masuk sebagai pegawai di perangkat atau browser A dan buka satu halaman data.
- [ ] **Nonaktifkan dari perangkat B.** Masuk sebagai admin di perangkat atau browser B, lalu nonaktifkan akun pegawai tersebut.
- [ ] **Cek permintaan berikutnya di perangkat A.** Muat ulang halaman atau buka menu lain. Harapan: sesi berakhir dan muncul pesan bahwa akun dinonaktifkan, bukan data yang tetap terbuka.
- [ ] **Cek login ulang.** Coba masuk lagi dengan akun nonaktif. Harapan: login ditolak dengan pesan yang tidak membocorkan status akun.

## 7. Menguji Scheduler `notify:due-issues` di Windows XAMPP

1. Pastikan perintah berjalan manual:
   - Buka Command Prompt.
   - Jalankan `C:\xampp\php\php.exe artisan notify:due-issues` dari folder `backend`.
   - Harapan: perintah selesai tanpa error dan mencatat jumlah kendala yang mendekati atau melewati tenggat.

2. Buat tugas terjadwal:
   - Buka **Task Scheduler** Windows, pilih **Create Basic Task**.
   - Nama tugas: `SIMPATIK notify due issues`.
   - Pemicu: **Daily**, pilih jam yang diinginkan.
   - Aksi: **Start a program**.
   - Program/script: `C:\xampp\php\php.exe`.
   - Add arguments: `artisan notify:due-issues`.
   - Start in: path folder `backend`, misalnya `C:\laragon\www\simpatik\backend`.
   - Simpan tugas.

3. Verifikasi:
   - Klik kanan tugas lalu **Run**.
   - Harapan: status tugas selesai dan tidak ada jendela error.
   - Periksa `backend\storage\logs\laravel.log` bila perintah gagal.
   - Pastikan `.env` pada folder `backend` menunjuk database yang benar sebelum menjalankan tugas.

4. Catatan:
   - Tugas berjalan dengan akun Windows yang dipakai membuatnya. Pastikan akun itu memiliki akses ke folder `backend` dan `storage`.
   - Bila PHP atau path proyek berbeda, sesuaikan program dan `Start in`.
   - Jangan menjalankan perintah artisan yang mengubah data pada database `jadwal_bps` tanpa izin.
