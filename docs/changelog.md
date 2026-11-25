# Catatan Perubahan Rilis (Changelog)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Semua perubahan dan peningkatan fungsionalitas sistem dicatat secara berkala pada dokumen ini.

---

### Versi 2.8.0 LTS (2026-11-25) - *Current Release*
- **Modul Notulen Meeting & Briefing (`/notulen`)**:
  - Pencatatan notulen rapat/briefing mandiri terbuka untuk seluruh pengguna (lihat, catat, dan update isi maupun status *Action Items*).
  - Mendukung mode **Draft** & **Finalisasi**, notifikasi lonceng otomatis ke peserta internal saat finalisasi, cetak PDF A4, salin ringkasan WhatsApp, serta terintegrasi ke *Live Quick Search* (`Ctrl + K`).
- **Simpan Draft Serah Terima Shift**:
  - Tombol **Simpan Draft** dan mode **Lanjutkan Edit Draft** (`laporan/create?edit=<id>`) agar pengisian daftar pekerjaan bisa dicicil sebelum diajukan ke shift penerima.
- **Dashboard & Perbaikan Sistem**:
  - Penambahan kartu cepat & widget **Notulen Meeting & Briefing Terbaru** serta banner **Draft Serah Terima** di Dashboard.
  - Penegakan `SET time_zone = '+07:00'` pada koneksi PDO agar waktu Audit Trail & timestamp selalu dalam WIB, perbaikan *scroll* mobile side drawer, dan tag meta PWA `mobile-web-app-capable`.

---

### Versi 2.7.0 LTS (2026-11-25)
- **Enterprise Security & Audit Trail**:
  - Halaman **Audit Trail & Log Aktivitas (`/audit`)** khusus Superadmin dengan tabel filter & rekam jejak IP Address (`LOGIN_SUCCESS`, `LOGIN_FAILED`, `HANDOVER_CREATE`, `HANDOVER_APPROVE`, `USER_RESET_PASS`, `USER_DELETE`, `SETTINGS_UPDATE`).
  - **Control Room Idle Auto-Logout**: Otomatis memunculkan dialog hitung mundur 60 detik setelah 45 menit tanpa aktivitas sebelum logout otomatis.
- **Produktivitas & PWA**:
  - **Live Quick Search AJAX (`Ctrl + K`)** di header atas (`api/search`) untuk pencarian nomor laporan, nomor tiket, lokasi, dan petugas secara instan.
  - **Progressive Web App (PWA)**: Penambahan `manifest.php` dinamis (mengikuti nama & logo aplikasi di Pengaturan) dan Service Worker `sw.js`.
- **Pemolesan Halaman Login (`auth/login`)**:
  - Menampilkan badge versi aplikasi (`v2.7.0 LTS`) di bawah kartu login serta menghapus teks placeholder `NIK` pada kolom username.
  - Penambahan motif latar **Batik Kawung** SVG modern yang selaras untuk *Light Mode* dan *Dark Mode*.

---

### Versi 2.6.0 LTS (2026-11-25)
- **Sistem Notifikasi Lonceng & Pengumuman Superadmin**:
  - Popover lonceng notifikasi real-time (`api/notifications`) dengan badge jumlah belum dibaca.
  - Notifikasi otomatis ke lonceng dan Email petugas penerima saat serah terima dibuat/divalidasi (hilang otomatis dari daftar setelah diklik/dibaca).
  - Fitur pembuatan Pengumuman Resmi khusus Superadmin dengan kategori (`Info`, `Penting`, `Darurat`) dan Banner Pengumuman di atas Dashboard.
- **Peningkatan Antarmuka & Pengalaman Pengguna (UX)**:
  - Widget **Live Shift Aktif & Jam Real-Time WIB** di Dashboard (mendukung shift malam lintas hari).
  - Deretan **Chip Filter Cepat 1-Klik** pada Riwayat Laporan (`Semua`, `Hari Ini`, `Menunggu Validasi`, `Tervalidasi`, `Ada Gangguan`).
  - **Floating Toast Notification** *frosted glass* yang menghilang otomatis dalam 4 detik.
  - **Floating Glass Pill Tooltip** dengan animasi pegas pada sidebar PC serta auto *cache-buster* CSS.
- **Backup Multi-Part Discord & Clean URLs**:
  - Pemecahan otomatis file backup besar menjadi part 8 MB (`.part001`, `.part002`, ...) agar kompatibel dengan batas upload Discord Webhook.
  - Clean URLs tanpa `.php`, favicon dinamis, logo PNG transparan murni, serta halaman **Informasi Sistem (`/info`)**.

---

### Versi 2.5.0 (2026-11-24)
- **Docker Containerization**:
  - Konfigurasi multi-kontainer Docker Compose (`app` PHP 8.5 Apache, `db` MySQL 8.0, `redis` Redis 7 Alpine).
  - Port default aplikasi dialokasikan ke port **2032** untuk isolasi produksi.
  - OPcache aktif dengan alokasi memori RAM 128MB untuk kompilasi skrip instan.
- **Redis In-Memory Caching Layer**:
  - Pustaka `OmitCache` dengan mekanisme *safe fallback* ke memori lokal tanpa memicu error jika Redis offline.
  - Caching master data (Shift, Lokasi, Kondisi Operasional) dan `app_settings` dengan auto-invalidation.
  - Tombol **Flush Cache** interaktif pada halaman Pusat Backup.
- **Manajemen Master Kondisi Operasional Dinamis**:
  - Penambahan modul `master/kondisi.php` dengan pilihan 7 warna badge status dan pengatur kondisi default.
  - Integrasi dinamis pada formulir serah terima shift baru (`laporan/create.php`).
- **Optimalisasi Antarmuka & Mobile Layout**:
  - Penyatuan tombol menu master operasional di sidebar menjadi satu ikon terpadu (`ph-sliders-horizontal`) demi menghemat ruang vertikal sidebar.
  - Perbaikan tombol dark mode khusus mobile menjadi tombol sirkuler tunggal responsif dengan animasi transisi sun/moon tanpa menyebabkan overflow horizontal.
  - Penambahan kontrol tema visual di dalam mobile drawer samping.
- **Pusat Backup & Disaster Recovery**:
  - Fitur backup database MySQL (`.sql`) dan backup full sistem aplikasi (`.zip`).
  - Opsi pengiriman berkas cadangan ke email via SMTP dan pemberitahuan status ke Discord Webhook.
  - Fitur Auto-Optimise database (`OPTIMIZE TABLE` & `ANALYZE TABLE`) serta pembersihan berkas orphan otomatis.

---

### Versi 2.0.0 (2026-11-20)
- **Multi-Mode Digital Signature Engine**:
  - Dukungan tanda tangan kanvas digital (touchscreen/mouse).
  - Cap verifikasi Barcode QR Code kriptografis.
  - Dukungan upload scan tanda tangan fisik / cap basah.
- **Eskalasi Tiket Berkelanjutan**:
  - Tiket pekerjaan yang belum selesai otomatis terbawa ke shift berikutnya dan dipantau hingga status `Selesai`.
- **Integrasi Notifikasi Multi-Kanal**:
  - Notifikasi email SMTP berbasis template HTML modern.
  - Notifikasi pesan ringkas ke WhatsApp Gateway API.
  - Notifikasi embed ke Discord Webhook channel.

---

### Versi 1.0.0 (2026-11-10) - *Initial MVP*
- Rilis perdana sistem serah terima shift operasional standar formulir OMIT.
- Cetak PDF dokumen berita acara ukuran A4 dengan tata letak presisi 1:1.
- Autentikasi pengguna berbasis peran (Superadmin, Admin, Operator).
