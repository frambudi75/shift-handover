# Product Requirement Document (PRD)
## Sistem Informasi Laporan Serah Terima Shift Operasional (OMIT)

### 1. Ringkasan Eksekutif
Aplikasi Laporan Serah Terima Shift Operasional adalah sistem web berbasis **PHP 8.2+ Native**, **MySQL 8.0**, dan **Redis In-Memory Cache** yang dirancang untuk mendigitalisasi proses serah terima kerja antar-shift di lingkungan operasional berisiko tinggi. Sistem ini mengusung antarmuka **Full-Width Responsive** dengan arsitektur modern berstandar NoteFlow, fitur tanda tangan digital multi-mode (Canvas, Barcode QR Code, Upload TTD Basah), eskalasi otomatis tugas kritis, pencadangan bencana instan, serta kontainerisasi **Docker**. Output dokumen cetak PDF 100% identik dengan standar formulir *Template_Serah_Terima_Shift_OMIT.pdf*.

---

### 2. Sasaran & Tujuan
1. **Transparansi & Akuntabilitas Penuh**: Memastikan setiap peralihan tanggung jawab shift terekam secara digital, sah secara hukum operasional dengan tanda tangan dua belah pihak, dan terpantau oleh manajemen.
2. **Zero-Lag Architecture (Redis Cache & OPcache)**: Menjaga respon aplikasi tetap secepat kilat (sub-50ms) meskipun diakses bersamaan oleh ratusan personil lapangan.
3. **Pencegahan Kehilangan Informasi Lapangan (Zero-Lost Ticket)**: Setiap pekerjaan kritis yang belum terselesaikan pada shift sebelumnya secara otomatis dieksekusi ulang ke shift berikutnya hingga dinyatakan selesai.
4. **Disaster Recovery & Data Integrity**: Fasilitas backup database dan full file sistem yang terintegrasi dengan email SMTP dan Discord webhook.
5. **Multi-Platform Fluidity**: Tampilan optimal di komputer stasiun kontrol (desktop), tablet pengawas, maupun smartphone petugas lapangan.

---

### 3. Matriks Peran Pengguna (RBAC Matrix)

| Fitur / Menu | Superadmin | Admin | User |
| :--- | :---: | :---: | :---: |
| Login, Profil & Keamanan Akun | Ya | Ya | Ya |
| Pengalihan Tema (Dark & Light Mode) | Ya | Ya | Ya |
| Dashboard Operasional & Statistik | Ya | Ya | Ya |
| Buat Berita Acara Serah Terima Shift | Ya | Ya | Ya |
| Unggah 3 Dokumentasi Foto + Kompresi Otomatis | Ya | Ya | Ya |
| Multi-Mode Signature (Canvas / QR / Basah) | Ya | Ya | Ya |
| Konfirmasi & Approval Penerimaan Shift | Ya | Ya | Ya (Penerima) |
| Cetak Dokumen PDF Standar OMIT Presisi | Ya | Ya | Ya |
| Rekap Logbook Harian Shift | Ya | Ya | Ya |
| Kelola Jadwal Shift & Jam Kerja Resmi | Ya | Ya | Tidak |
| Kelola Master Lokasi / Pos Lapangan | Ya | Ya | Tidak |
| Kelola Pilihan Kondisi Operasional & Badge | Ya | Ya | Tidak |
| Manajemen Akun Pengguna & Reset Sandi | Ya | Ya | Tidak |
| Manajemen Superadmin (Ubah Peran Puncak) | Ya | Tidak | Tidak |
| Pusat Backup Sistem & Unduh Arsip | Ya | Ya | Tidak |
| Eksekusi Auto-Optimise & Flush Cache | Ya | Ya | Tidak |
| Hapus / Koreksi Laporan Permanen | Ya | Ya | Tidak |

---

### 4. Struktur Berita Acara Serah Terima (4 Bagian Standar)
- **Bagian 1: Identitas Shift & Kondisi Lapangan**: Nomor Laporan otomatis (`OMIT-YYYYMMDD-XXX`), Tanggal, Pilihan Shift, Lokasi/Pos, Jam Serah Terima, Pilihan Dinamis Kondisi Operasional Umum, Petugas Menyerahkan, Petugas Menerima, Auto-counter Pekerjaan Aktif & Kritis, serta Catatan Kendala.
- **Bagian 2: Rincian Pekerjaan, Insiden & Tindak Lanjut**: Tabel multi-baris dinamis yang mencakup No/Tiket, Uraian Pekerjaan/Masalah, Tingkat Prioritas, Status Progres, Kondisi & Tindak Lanjut, PIC, dan Target Selesai.
- **Bagian 3: Bukti Visual / Foto Lapangan**: 3 slot unggahan foto beresolusi optimal dengan kompresi otomatis untuk: (1) Area/Peralatan, (2) Indikator/Kendala, dan (3) Dokumentasi Serah Terima Fisik.
- **Bagian 4: Konfirmasi Checklist & Pengesahan**: 4 butir pertanyaan verifikasi operasional (Lisan, Tiket, Risiko K3, Kunci/Aset) serta blok tanda tangan digital berstatus approval dua tahap.
