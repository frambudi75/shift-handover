# Catatan Perubahan Rilis (Changelog)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Semua perubahan dan peningkatan fungsionalitas sistem dicatat secara berkala pada dokumen ini.

---

### Versi 2.11.0 LTS (2026-11-27) - *Current Release*
- **Kanban & Catatan Pribadi (`/kanban`) + Log Kunci & Aset Shift (`/aset`)**:
  - **Kanban & Catatan Pribadi (`/kanban`)**: Papan tugas 3 kolom (`Belum Mulai`, `Berjalan`, `Selesai`) dan *sticky notes* yang **100% privat** per akun login (`personal_kanban_tasks`, `personal_notes`).
  - **Log Kunci, Alat & Aset Shift (`/aset`)**: Pencatatan bersama/publik untuk peminjaman & pengembalian kunci ruang server, HT, kartu akses, dan alat piket (`shift_assets`, `shift_asset_logs`).
- **Kolaborasi, Tautan Publik & Monitor Ruang Kontrol**:
  - **Tautan Publik Notulen (`/notulen/share`)**: Halaman baca-saja bertoken HMAC untuk Notulen berstatus `FINAL`, terintegrasi di tombol Salin Link & teks WhatsApp.
  - **Catatan Tindak Lanjut Laporan (`laporan_comments`)**: Kolom diskusi/update perkembangan di bawah `laporan/detail` tanpa mengubah isi berita acara bertanda tangan.
  - **Mode Monitor TV / Kiosk**: Tombol layar penuh di Dashboard dengan *auto-refresh* setiap 60 detik, serta filter cepat **Perlu Validasi Saya** & **Laporan Saya** di Riwayat Laporan.
- **Keamanan, Audit & Poles UI/UX**:
  - Pembatasan menu **Pusat Backup & Pemulihan (`/master/backup`)** khusus Superadmin.
  - Tombol **Ekspor CSV** pada halaman **Audit Trail (`/audit`)** sesuai filter aktif.
  - Perapihan kurva konektor SVG atas & bawah sidebar desktop agar menyatu mulus.
  - Perlindungan *anti-double-submit* (spinner `Memproses...`), shortcut keyboard (`/` untuk Quick Search, `Esc` untuk tutup modal/popover), dan tombol *Back to Top*.

---

### Versi 2.10.0 LTS (2026-11-27)
- **Analitik & Grafik Tren Operasional (`/rekap`)**:
  - Kartu KPI evaluasi periode (Total Laporan & % Tervalidasi, Rasio Tiket Selesai, Shift Non-Normal, Total Notulen), grafik batang tren harian lokal tanpa CDN, rasio status pekerjaan, serta tombol ekspor Excel (`.xls`).
- **Notifikasi Keamanan Discord & Pengingat Shift**:
  - Peringatan *real-time* event keamanan & audit ke Discord Webhook (khusus Superadmin di `/master/pengaturan`) dengan pilihan event granular.
  - Banner *countdown* otomatis 30 menit sebelum jam serah terima apabila laporan shift berjalan belum dibuat.
- **Peningkatan UI/UX & Mobile**:
  - Efek mikro **Sakuga VFX** (`assets/js/sakuga-vfx.js` Canvas 2D + animasi *spring* bertema OMIT) pada tombol aksi, toggle tema, checkbox, dan toast notifikasi.
  - Tampilan **Mobile Card View** (`< 640px`) pada halaman Riwayat Laporan, Notulen Meeting, dan Audit Trail agar nyaman dibaca di HP tanpa *horizontal scroll*.

---

### Versi 2.9.0 LTS (2026-11-26)
- **Integrasi LDAP / Active Directory (`/master/ldap`)**:
  - Menu khusus **Superadmin** untuk konfigurasi server AD / OpenLDAP / FreeIPA, Test Koneksi, dan **Impor / Sync massal user** ke database lokal.
  - Autentikasi **Hybrid** di halaman login (LDAP terlebih dahulu dengan fallback akun lokal) serta pemasangan ekstensi `ext-ldap` di `Dockerfile` & container produksi.
- **Restore, Migrasi Sistem & Cron Auto-Backup**:
  - Fitur **Restore & Migrasi** (`.sql` / `.zip`) di `master/backup.php` khusus Superadmin dengan *pre-restore safety snapshot* otomatis dan pemulihan folder `uploads/`.
  - Skrip `cron/auto_backup.php` untuk backup terjadwal (CLI Docker Crontab & Webhook) dengan pengiriman otomatis ke Discord Webhook (multi-part 8 MB).
- **Tautan Publik, RBAC & Default Tema**:
  - Halaman publik baca-saja `/laporan/share` bertoken HMAC pada tautan WhatsApp agar 1 laporan dapat dilihat tanpa login.
  - Pembatasan menu **Pengaturan Sistem (`/master/pengaturan`)** khusus Superadmin, serta penyeragaman tema default ke **Light Mode**.

---

### Versi 2.8.1 LTS (2026-11-25)
- **Performa & Aset Lokal**:
  - Compile **Tailwind CSS lokal** (`assets/css/tailwind.min.css`) menggantikan `cdn.tailwindcss.com` agar loading lebih cepat di LAN/offline dan bebas warning di DevTools Console.
- **Keamanan (*Hardening*)**:
  - Blokir akses HTTP langsung ke folder sensitif (`storage/`, `docs/`, `includes/`, `config/`, `.git`, `.env`, `.sql`, `.md`) via `.htaccess`.
  - Penegakan `SameSite=Lax` + `HttpOnly` pada cookie sesi, `session_regenerate_id(true)` saat login, dan *rate limit* percobaan login gagal (maks. 8x per 10 menit per IP).
- **Perbaikan Operasional & Mobile**:
  - URL verifikasi **Barcode QR TTD** fleksibel mengikuti alamat aktif di browser (IP maupun domain).
  - Penomoran tiket otomatis (`TK-{id_laporan}-01`) jika kolom `No / Tiket` dikosongkan + fallback cetak PDF.
  - Perbaikan CSS `.curved-sidebar-wrapper` agar sidebar PC tidak muncul di layar mobile.

---

### Versi 2.8.0 LTS (2026-11-25)
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
