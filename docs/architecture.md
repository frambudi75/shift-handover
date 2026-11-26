# Dokumentasi Arsitektur Sistem (System Architecture)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Gambaran Umum Arsitektur (High-Level Architecture)

Aplikasi dibangun menggunakan arsitektur modular **Monolith Modern (MVC-Lite)** berbasis **PHP 8.5+ Native**, didukung oleh **MySQL 8.0** sebagai penyimpanan relasional berintegritas tinggi, **Redis 7** sebagai akselerator *in-memory cache*, serta dikemas dalam orkestrasi kontainer **Docker**.

```mermaid
graph TD
    UserClient[Pengguna: Browser Desktop / Tablet / Smartphone] --> |HTTP / HTTPS Port 2032| WebServer[Web Server: Apache 2.4 + PHP 8.5 + OPcache]
    
    subgraph Kontainer Aplikasi (omit_shift_app)
        WebServer --> Router[Front Controller & Routing]
        Router --> AuthMod[Autentikasi & RBAC Session]
        Router --> HandoverMod[Modul Serah Terima & Digital Signature]
        Router --> MasterMod[Modul Master Data: Shift, Lokasi, Kondisi]
        Router --> BackupMod[Modul Backup & Auto-Optimise]
        Router --> NotifMod[Notifikasi: SMTP, WA, Discord]
        
        HandoverMod --> CacheLayer[OmitCache Abstraction Layer]
        MasterMod --> CacheLayer
        AuthMod --> CacheLayer
    end
    
    subgraph Kontainer Cache (omit_shift_redis)
        CacheLayer --> |TCP Port 6379 / In-Memory| RedisSvc[(Redis 7 In-Memory Cache)]
    end
    
    subgraph Kontainer Database (omit_shift_db)
        HandoverMod --> |PDO Prepared Statements Port 3306| MySQL[(MySQL 8.0 Relational Database)]
        MasterMod --> MySQL
        AuthMod --> MySQL
        BackupMod --> MySQL
    end
    
    subgraph Layanan Eksternal
        NotifMod --> |TLS Port 587| SMTPServer[Mail Server SMTP]
        NotifMod --> |HTTPS Webhook| DiscordAPI[Discord Channel Webhook]
        NotifMod --> |REST API| WAGateway[WhatsApp Gateway API]
    end
```

---

### 2. Lapisan Layanan (Layer Architecture)

#### A. Presentation Layer (Antarmuka Pengguna)
- **Desain UI**: NoteFlow Design Language (Modern, Clean, Rounded-2xl, Elevated Cards) dengan motif **Batik Kawung** SVG pada halaman login.
- **Styling**: Tailwind CSS ter-compile lokal (`assets/css/tailwind.min.css`) + Phosphor Icons + Custom Canvas Signature Engine + *cache-busting* CSS otomatis via `filemtime`.
- **Responsivitas & PWA**:
  - Desktop: Docked sidebar ramping di kiri dengan *Floating Glass Pill Tooltip*, serta header *Live Quick Search* (`Ctrl + K`).
  - Mobile: Floating bottom navigation bar, mobile drawer samping (*scroll* independen), responsive single dark mode toggle.
  - **Progressive Web App (PWA)**: `manifest.php` dinamis dan Service Worker `sw.js`.
- **Dual Mode**: Default **Light Mode** (`#f5f5fa`) dengan opsi **Dark Mode** (`#121421`) tersimpan di `localStorage`.

#### B. Application & Logic Layer (PHP 8.5+)
- **OPcache Enabled**: Bytecode PHP disimpan di memori RAM (128MB) sehingga tidak ada proses kompilasi berulang pada setiap *request*.
- **Multi-Mode Signature**: Mendukung 3 jenis tanda tangan:
  1. *Canvas Pad*: Goresan digital mouse / layar sentuh berbasis Base64 PNG.
  2. *Barcode QR Code*: Kode verifikasi kriptografis unik yang dapat dipindai publik.
  3. *Wet Signature Upload*: Unggah foto tanda tangan fisik / basah asli di atas kertas.
- **Escalation Engine**: Tiket pekerjaan yang belum terselesaikan (`prioritas = 'Tinggi / Kritis'` atau `status != 'Selesai'`) secara otomatis dipindahkan (*carried over*) ke form shift berikutnya dan dimonitor hingga tuntas.
- **Enterprise Security, Integrasi & Observability**:
  - **Integrasi LDAP / AD (`/master/ldap`)**: Autentikasi *Hybrid* (`includes/ldap_helper.php` + `ext-ldap`) serta sinkronisasi massal user.
  - **Audit Trail (`/audit`)**: Rekam jejak aktivitas dan IP Address (`includes/audit_helper.php`).
  - **Control Room Idle Auto-Logout**: Peringatan hitung mundur 60 detik setelah 45 menit tanpa aktivitas.
  - **System Info (`/info`)**: Telemetri versi aplikasi, PHP, MariaDB/MySQL, dan Redis cache.
  - **Modul Mandiri & Disaster Recovery**: Notulen Meeting (`/notulen`), Tautan Publik HMAC (`/laporan/share`), Restore & Migrasi Sistem (`master/backup.php`), dan Cron Auto-Backup (`cron/auto_backup.php`).

#### C. Caching Layer (Redis 7 In-Memory with Safe Fallback)
- **Komponen**: `includes/cache.php` (`OmitCache`).
- **Mekanisme**:
  - Menggunakan ekstensi PECL `redis` di Docker.
  - Timeout koneksi cepat (0.5 detik). Jika Redis tidak aktif atau sistem dijalankan di XAMPP lokal, sistem otomatis beralih (*graceful fallback*) ke *In-Memory Request Cache* tanpa menghasilkan error.
  - Data yang di-cache: `app_settings` (1 jam), `master_shift` (30 menit), `master_lokasi` (30 menit), `master_kondisi` (30 menit).
  - *Cache Invalidation*: Setiap aksi simpan/ubah di master data dan pengaturan langsung memicu `cache_delete` seketika.

#### D. Data Layer (MySQL 8.0)
- **Engine**: InnoDB dengan `utf8mb4_unicode_ci`.
- **Buffer Pool**: 256MB di kontainer database.
- **Relasional**: Foreign Keys dengan aturan `RESTRICT` pada data master dan `CASCADE` pada detail laporan.
- **Indeks**: B-Tree Indexing pada kolom pencarian (`nomor_laporan`, `tanggal`, `shift_id`, `lokasi_id`, `no_tiket`, `status`).

---

### 3. Keamanan Sistem (Security Architecture)

1. **SQL Injection Defense**: 100% kueri database menggunakan PDO Prepared Statements dengan parameter binding (`:param`).
2. **XSS Protection**: Seluruh output dinamis disaring menggunakan fungsi helper `sanitize()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
3. **CSRF & Action Guard**: Operasi sensitif diproteksi dengan verifikasi sesi dan otorisasi level peran (`require_admin()` / `require_superadmin()`).
4. **Credential Masking**: Password di-hash menggunakan algoritma modern `PASSWORD_BCRYPT` dengan cost default PHP.
5. **Brute Force & Flooding Protection**: Token rahasia unik (`cron_secret_key`) untuk eksekusi API pemeliharaan dan backup terjadwal.
