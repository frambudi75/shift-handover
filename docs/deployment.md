# Panduan Penerapan Produksi (Production Deployment Guide)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

Panduan teknis langkah demi langkah untuk men-deploy aplikasi pada server produksi (Ubuntu / Debian / CentOS / Windows Server).

---

### 1. Rekomendasi Spesifikasi Server (Production Requirements)

| Komponen | Spesifikasi Minimum | Rekomendasi Produksi |
| :--- | :--- | :--- |
| **CPU** | 2 vCPU | 4 vCPU atau lebih |
| **RAM** | 2 GB | 4 GB - 8 GB |
| **Penyimpanan** | 20 GB SSD | 50 GB NVMe SSD |
| **Sistem Operasi** | Ubuntu Server 22.04 LTS / 24.04 LTS | Ubuntu Server 24.04 LTS |
| **Runtime** | Docker Engine 24+ & Docker Compose | Docker Compose v2 |

---

### 2. Langkah Penerapan Menggunakan Docker (Metode Resmi)

#### Langkah 1: Kloning Repositori
```bash
git clone https://github.com/frambudi75/shift-handover.git /var/www/serah-terima
cd /var/www/serah-terima
```

#### Langkah 2: Buat Berkas Konfigurasi Lingkungan
Salin berkas template lingkungan produksi:
```bash
cp .env.example .env
```
Sesuaikan kata sandi database dan token rahasia pada berkas `.env`:
```ini
APP_PORT=2031
APP_BASE_URL=/
DB_NAME=db_serah_terima
DB_USER=serahterima_user
DB_PASS=GantiDenganPasswordSangatKuat123!
DB_ROOT_PASS=GantiDenganRootPasswordSangatKuat123!
REDIS_HOST=redis
REDIS_PORT=6379
```

#### Langkah 3: Bangun & Jalankan Kontainer
```bash
docker compose up -d --build
```

#### Langkah 4: Verifikasi Layanan
```bash
docker compose ps
```
Pastikan kontainer `omit_shift_app`, `omit_shift_db`, dan `omit_shift_redis` berstatus `Up (healthy)`.

---

### 3. Konfigurasi Reverse Proxy Nginx & SSL Let's Encrypt (Opsional)

Jika ingin diakses menggunakan domain resmi dengan protokol HTTPS aman (port 443):

```nginx
server {
    listen 80;
    server_name handover.perusahaan.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name handover.perusahaan.co.id;

    ssl_certificate /etc/letsencrypt/live/handover.perusahaan.co.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/handover.perusahaan.co.id/privkey.pem;

    client_max_body_size 35M;

    location / {
        proxy_pass http://127.0.0.1:2031;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

### 4. Setup Cron Pemeliharaan Otomatis (Host Crontab)
Jalankan perintah optimasi sistem setiap minggu secara otomatis:
```bash
crontab -e
```
Tambahkan baris berikut:
```bash
0 2 * * 0 curl -s "http://127.0.0.1:2031/cron/auto_optimize.php?token=TOKEN_ANDA_DISINI" > /dev/null 2>&1
```
