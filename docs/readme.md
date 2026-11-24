# Dokumentasi Lengkap Sistem (System Overview & Manual)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Aplikasi web modern berbasis **PHP 8.2+**, **MySQL 8.0**, dan **Redis 7** yang dirancang khusus untuk memfasilitasi berita acara serah terima kerja antar-shift di berbagai fasilitas industri dan operasional (Pusat Kendali / Control Room, Fasilitas Manufaktur, Logistik, Server Room / IT & Jaringan, dan Unit Utility).

---

## 🌟 Fitur Utama Sistem

1. **Digital Handover Multi-Mode**:
   - Pembuatan berita acara serah terima terstruktur sesuai format standar formulir resmi OMIT.
   - Pilihan metode tanda tangan digital 3-in-1: **Goresan Canvas Digital**, **Kode Verifikasi QR Code**, dan **Upload Foto TTD Basah Asli**.
2. **Eskalasi Otomatis Antar-Shift**:
   - Tiket pekerjaan yang belum selesai (`Berjalan`, `Tertunda`, `Belum Mulai`) atau berkategori `Tinggi / Kritis` otomatis dipindahkan ke form shift berikutnya dan dimonitor hingga berstatus `Selesai`.
3. **Master Kondisi Operasional Dinamis**:
   - Administrator dapat menambah, mengedit, memilih warna badge status (*Emerald*, *Amber*, *Rose*, *Indigo*, *Purple*, dll), serta mengatur kondisi default formulir.
4. **Pusat Backup & Disaster Recovery**:
   - Mendukung pencadangan instan **Database MySQL (`.sql`)** dan **Full Sistem Aplikasi (`.zip`)**.
   - Berkas cadangan dapat diunduh langsung, dikirim otomatis ke email administrator via SMTP, atau dikirimkan notifikasinya ke channel Discord Webhook.
5. **Auto-Optimise & Pemeliharaan Database**:
   - Fitur otomatis untuk `OPTIMIZE TABLE` & `ANALYZE TABLE`, pembersihan file gambar sampah (*orphan*), dan rotasi penghapusan arsip cadangan lama secara berkala.
6. **Performa Tinggi Bebas Lag (Docker & Redis Cache)**:
   - Terintegrasi dengan **Redis 7 In-Memory Cache** dan **OPcache** untuk memangkas waktu muat hingga di bawah 50ms bahkan pada beban puncak.
7. **Desain NoteFlow UI Responsif & Dual Mode**:
   - Desain modern berstandar NoteFlow, mendukung pengalihan instan **Light Mode** dan **Dark Mode** baik pada layar desktop maupun smartphone.

---

## 💻 Panduan Instalasi Cepat

### A. Menggunakan Docker (Direkomendasikan)
```bash
# 1. Salin konfigurasi environment
cp .env.example .env

# 2. Jalankan seluruh layanan
docker compose up -d

# 3. Buka di browser
# URL: http://localhost:2032
```

### B. Menggunakan XAMPP / Manual Local Server
1. Pindahkan folder proyek ke `c:/xampp/htdocs/serah-terima/`.
2. Nyalakan **Apache** dan **MySQL** di panel kontrol XAMPP.
3. Buat database baru bernama `db_serah_terima` dan impor berkas `docker/init.sql`.
4. Buka browser pada alamat: `http://localhost/serah-terima/`.

---

## 🔑 Kredensial Login Bawaan

| Akun | Peran (Role) | Username | Password |
| :--- | :--- | :--- | :--- |
| **Super Administrator** | `superadmin` | `superadmin` | `password` |
| **Administrator Operasional** | `admin` | `admin` | `password` |
| **Operator Lapangan** | `user` | `andi` | `password` |
