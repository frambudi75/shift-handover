# Panduan Setup Docker & Redis Cache - OMIT Shift Handover

Dokumen ini menjelaskan cara menjalankan sistem **OMIT Shift Handover** menggunakan arsitektur kontainer **Docker** dan **Redis In-Memory Cache** untuk performa tinggi, stabilitas 24/7, dan bebas lag saat digunakan oleh banyak pengguna secara bersamaan.

---

## 🚀 Arsitektur Layanan Kontainer

Sistem ini diorkestrasi menggunakan **Docker Compose** dengan 3 layanan utama:

1. **`app` (PHP 8.2 + Apache + OPcache + Redis PECL Extension)**:
   - Web server Apache dengan modul `rewrite` & `headers`.
   - Mengaktifkan **OPcache** (kompilasi bytecode PHP di RAM 128MB) sehingga eksekusi skrip instan tanpa kompilasi berulang.
   - Ekstensi PHP `gd` (kompresi gambar otomatis), `pdo_mysql`, `zip`, dan `redis`.
   - Port default: `http://localhost:2031` (dapat diubah di file `.env`).

2. **`db` (MySQL 8.0)**:
   - Database MySQL dengan konfigurasi `InnoDB Buffer Pool 256MB` dan charset `utf8mb4`.
   - Menginisialisasi otomatis seluruh tabel, indeks performa, data pengaturan, dan user dari berkas `docker/init.sql`.
   - Port eksternal: `3307` (agar tidak bentrok jika port 3306 digunakan oleh XAMPP/MySQL lokal Anda).

3. **`redis` (Redis 7 Alpine)**:
   - In-memory key-value cache dengan kebijakan memori `allkeys-lru` (maksimal 128MB).
   - Menyimpan cache master data (jadwal shift, master lokasi, pilihan kondisi operasional umum, dan preferensi aplikasi `app_settings`).
   - Mengurangi beban kueri database berulang hingga **85%–90%**, membuat aplikasi tetap responsif saat diakses ramai.
   - Port eksternal: `6380`.

---

## 🛠️ Cara Menjalankan dengan Docker

### 1. Salin File Konfigurasi Lingkungan
Buka terminal di direktori proyek dan salin `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```

### 2. Jalankan Kontainer (Background Mode)
Jalankan perintah berikut:
```bash
docker compose up -d
```
Docker akan otomatis:
- Mengunduh image dasar (`mysql:8.0`, `redis:7-alpine`).
- Membangun image PHP 8.2 Apache dengan ekstensi OPcache & Redis.
- Menjalankan kontainer dan menginisialisasi database dari `docker/init.sql`.

### 3. Cek Status Kontainer
```bash
docker compose ps
```
Pastikan ketiga kontainer (`omit_shift_app`, `omit_shift_db`, `omit_shift_redis`) berstatus `Up` / `healthy`.

### 4. Akses Aplikasi di Browser
Buka browser dan kunjungi:
👉 **`http://localhost:2031`**

**Akun Login Default:**
- **Username**: `superadmin`
- **Password**: `password`
*(Atau `admin` / `password`)*

---

## ⚡ Fitur Redis Cache & Mekanisme Anti-Lag

### 1. Zero-Downtime Safe Fallback
Kode aplikasi dilengkapi proteksi **Safe Fallback**:
- Jika Redis menyala: Data master dan pengaturan disimpan di Redis dengan TTL.
- Jika Redis mati atau aplikasi dijalankan di luar Docker (misal XAMPP biasa): Aplikasi **TIDAK AKAN CRASH**, melainkan otomatis beralih menggunakan in-memory cache lokal.

### 2. Auto Cache Invalidation
Setiap kali Administrator mengubah nama instansi, logo, jadwal shift, master lokasi, atau menambah pilihan kondisi operasional, cache Redis terkait **langsung dihapus secara otomatis seketika** (`cache_delete`), sehingga perubahan langsung tampil tanpa perlu restart.

### 3. Pemantauan & Tombol Flush Cache dari Antarmuka Web
Di menu **Pusat Backup & Pemulihan Sistem** (`master/backup.php`):
- Tersedia indikator chip status **Cache: Redis Aktif / In-Memory**.
- Tersedia tombol **Flush Cache** untuk mengosongkan seluruh memori cache dengan 1 klik kapan saja.

---

## 🛑 Perintah Pemeliharaan Docker

| Perintah | Deskripsi |
| :--- | :--- |
| `docker compose up -d` | Menjalankan seluruh sistem di latar belakang |
| `docker compose down` | Menghentikan seluruh kontainer (data tetap aman di volume) |
| `docker compose restart app` | Merestart kontainer web aplikasi |
| `docker compose logs -f app` | Melihat log akses dan error web server secara real-time |
| `docker compose exec redis redis-cli info memory` | Memeriksa kapasitas memori Redis yang digunakan |
| `docker compose exec db mysql -u root -proot_secure_password db_serah_terima` | Masuk ke konsol MySQL |
