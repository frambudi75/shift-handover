# Panduan & Spesifikasi Docker (Docker Deployment Specification)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Spesifikasi Layanan Kontainer

| Layanan | Image Dasar | Port Host | Port Kontainer | Fungsi |
| :--- | :--- | :---: | :---: | :--- |
| **`app`** | Custom (`Dockerfile` berbasis `php:8.2-apache`) | **2032** | 80 | Web server Apache, PHP 8.2 runtime, OPcache, Redis extension |
| **`db`** | `mysql:8.0` | **3307** | 3306 | Database relasional MySQL dengan buffer pool 256MB |
| **`redis`** | `redis:7-alpine` | **6380** | 6379 | In-memory cache server dengan limit 128MB dan kebijakan LRU |

---

### 2. Berkas Konfigurasi Lingkungan (`.env`)

```ini
# Port Web Aplikasi Utama
APP_PORT=2032
APP_BASE_URL=/

# Konfigurasi Database MySQL
DB_HOST=db
DB_PORT=3306
DB_NAME=db_serah_terima
DB_USER=serahterima_user
DB_PASS=secret_serahterima_pass
DB_ROOT_PASS=root_secure_password
DB_PORT_EXTERNAL=3307

# Konfigurasi Cache Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PORT_EXTERNAL=6380
REDIS_PASS=
REDIS_PREFIX=omit_cache:
```

---

### 3. Perintah Operasional Docker

#### Menjalankan Sistem
```bash
# Menjalankan seluruh layanan di latar belakang
docker compose up -d

# Membangun ulang image jika ada perubahan kode/konfigurasi
docker compose up -d --build
```

#### Memeriksa Status & Log
```bash
# Cek status kontainer dan kesehatan (healthcheck)
docker compose ps

# Membaca log web server secara langsung (streaming)
docker compose logs -f app

# Membaca log database MySQL
docker compose logs -f db

# Membaca log cache Redis
docker compose logs -f redis
```

#### Menghentikan Sistem
```bash
# Menghentikan kontainer tanpa menghapus data volume
docker compose down

# Menghentikan dan menghapus seluruh volume (Hati-hati: data database terhapus)
docker compose down -v
```

---

### 4. Volume Persistensi Data
Data disimpan secara persisten di host melalui Docker Named Volumes:
- `db_data`: Menyimpan tabel dan direktori `/var/lib/mysql`.
- `redis_data`: Menyimpan dump snapshot `/data` Redis (AOF).
- `uploads_data`: Menyimpan foto dokumentasi lapangan di `/var/www/html/uploads`.
- `backups_data`: Menyimpan file arsip ZIP dan SQL cadangan di `/var/www/html/storage/backups`.
