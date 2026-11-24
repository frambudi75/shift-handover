# Catatan Perubahan Rilis (Changelog)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Semua perubahan dan peningkatan fungsionalitas sistem dicatat secara berkala pada dokumen ini.

---

### Versi 2.5.0 (2026-11-24) - *Current Release*
- **Docker Containerization**:
  - Konfigurasi multi-kontainer Docker Compose (`app` PHP 8.2 Apache, `db` MySQL 8.0, `redis` Redis 7 Alpine).
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
