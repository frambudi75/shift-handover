# OMIT Shift Handover System
### Sistem Laporan Berita Acara Serah Terima Shift Operasional Berstandar Industri

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.5-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479a1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-7%20Alpine-dc382d?logo=redis&logoColor=white)](https://redis.io/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ed?logo=docker&logoColor=white)](https://www.docker.com/)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-CSS-38bdf8?logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Aplikasi web modern yang dirancang untuk mendigitalisasi proses serah terima kerja antar-shift di berbagai fasilitas industri dan operasional (Control Room, Manufaktur, Logistik, Server Room / Data Center, dan Utility). Menjamin transparansi, akuntabilitas, eskalasi tiket tanpa henti, serta kepatuhan prosedur operasional standar (SOP).

---

## 🌟 Fitur Unggulan

- **Digital Handover Multi-Mode**: Pembuatan berita acara serah terima terstruktur sesuai format standar formulir resmi OMIT dengan 3 pilihan metode tanda tangan digital (Canvas digital, QR Code kriptografis, dan upload TTD basah asli).
- **Eskalasi Tiket Berkesinambungan**: Tiket prioritas tinggi atau kritis yang belum terselesaikan pada shift sebelumnya secara otomatis dipindahkan ke form shift selanjutnya hingga berstatus `Selesai`.
- **Master Kondisi Operasional Dinamis**: Pengaturan pilihan kondisi lapangan (*Normal*, *Perlu Pantauan*, *Ada Gangguan*, dll) dengan 7 palet warna badge visual dan pengatur kondisi default.
- **Pusat Backup & Pemulihan Bencana**: Pencadangan instan database MySQL (`.sql`) dan full sistem aplikasi (`.zip`) dengan opsi pengiriman otomatis via email SMTP dan notifikasi Discord webhook.
- **Auto-Optimise & Pemeliharaan Database**: Defragmentasi tabel otomatis (`OPTIMIZE TABLE`), pembaruan statistik optimizer query, serta pembersihan berkas orphan berkala.
- **Performa Bebas Lag (Docker & Redis Cache)**: Terintegrasi dengan Redis 7 In-Memory Cache dan OPcache, menjamin waktu respon di bawah 50ms meskipun diakses ramai.
- **Desain NoteFlow UI Responsif & Dual Mode**: Tampilan elegan dengan mode Terang (*Light Mode*) dan Gelap (*Dark Mode*) yang nyaman bagi petugas shift siang maupun malam.

---

## 🚀 Memulai dengan Docker (Direkomendasikan)

### 1. Salin Berkas Lingkungan
```bash
cp .env.example .env
```

### 2. Jalankan Kontainer
```bash
docker compose up -d
```

### 3. Akses Aplikasi
Buka browser pada alamat:
👉 **`http://localhost:2032`**

**Kredensial Default:**
- **Superadmin**: `superadmin` / `password`
- **Admin**: `admin` / `password`
- **Operator**: `andi` / `password`

---

## 💻 Memulai dengan XAMPP (Local Native)

1. Pindahkan folder proyek ke `c:/xampp/htdocs/serah-terima/`.
2. Nyalakan layanan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buat database `db_serah_terima` dan impor berkas `docker/init.sql`.
4. Buka di browser: `http://localhost/serah-terima/`.

---

## 📚 Indeks Dokumentasi Teknis

Seluruh dokumentasi teknis sistem tersimpan rapi di dalam folder [`docs/`](docs/):

- 📄 [**Product Requirement Document (PRD)**](docs/prd.md)
- 🏗️ [**System Architecture Documentation**](docs/architecture.md)
- 🐳 [**Panduan & Spesifikasi Docker**](docs/docker.md)
- 🗄️ [**Dokumentasi Basis Data & Indexing**](docs/database.md)
- 🔄 [**Alur Kerja & Flowchart Sistem**](docs/flowchart.md)
- 📊 [**Diagram Arsitektur & ERD**](docs/diagram.md)
- 📜 [**Aturan Bisnis & Validasi (Business Rules)**](docs/rules.md)
- 📖 [**Panduan Pengguna & SOP (User Guide)**](docs/user_guide.md)
- 🌐 [**Dokumentasi API & Webhook**](docs/api.md)
- 🎨 [**NoteFlow Design System**](docs/DESIGN.md)
- 🚀 [**Panduan Penerapan Produksi (Deployment)**](docs/deployment.md)
- 📝 [**Catatan Perubahan Rilis (Changelog)**](docs/changelog.md)
