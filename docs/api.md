# Dokumentasi Endpoint & API (API & Webhook Documentation)
## Sistem Laporan Serah Terima Shift Operasional (OMIT)

### 1. Endpoint Otomatisasi & Pemeliharaan (Cron Job API)

Digunakan untuk memicu proses pemeliharaan sistem, defragmentasi database, rotasi backup, dan pembersihan file orphan secara terjadwal.

- **URL**: `GET /cron/auto_optimize.php`
- **Autentikasi**: Wajib menyertakan parameter `token` yang cocok dengan `cron_secret_key` pada database.
- **Query Parameters**:
  - `token` (string, required): Kunci rahasia token cron sistem.
  - `force` (boolean, optional): Jika diset `1`, memaksa optimasi dijalankan tanpa memedulikan interval hari.
  - `verbose` (boolean, optional): Jika diset `1`, mengembalikan rincian teknis setiap tabel.

#### Contoh Request:
```bash
curl -s "http://localhost:2032/cron/auto_optimize.php?token=f91c5ca9d00a0222988eafd1d16f4264&force=1"
```

#### Contoh Response Berhasil (HTTP 200):
```json
{
  "success": true,
  "message": "Optimasi sistem berhasil dijalankan.",
  "duration_ms": 42.5,
  "db_results": {
    "tables": ["users", "master_lokasi", "master_shift", "master_kondisi", "laporan_serah_terima", "laporan_pekerjaan"],
    "freed_bytes": 16384,
    "freed_format": "16 KB"
  },
  "orphan_results": {
    "scanned": 15,
    "cleaned": 2,
    "freed_bytes": 1048576,
    "freed_format": "1 MB"
  },
  "backup_results": {
    "purged": 1,
    "freed_bytes": 5242880,
    "freed_format": "5 MB"
  }
}
```

---

### 2. Endpoint Pencarian Cepat Global (`Ctrl + K` / AJAX Quick Search)

Mencari laporan serah terima, nomor tiket, lokasi, dan nama petugas secara asinkron dari header navigasi.

- **URL**: `GET /api/search` (`api/search.php`)
- **Method**: `GET`
- **Autentikasi**: Wajib memiliki sesi login aktif (`401` jika belum login).
- **Parameter**:
  - `q` (string, min 2 chars): Kata kunci pencarian.

#### Contoh Response:
```json
{
  "success": true,
  "results": [
    {
      "id": 12,
      "nomor": "OMIT-20261124-001",
      "tanggal": "24 Nov 2026",
      "lokasi": "Control Room",
      "shift": "Shift Pagi",
      "petugas": "Andi Pratama",
      "status": "MENUNGGU VALIDASI",
      "url": "/laporan/detail?id=12"
    }
  ]
}
```

---

### 3. Endpoint Notifikasi Lonceng & Pengumuman (`api/notifications`)

Mengambil daftar notifikasi serah terima pengguna dan pengumuman resmi Superadmin, serta menandai status baca.

- **URL**: `GET /api/notifications` atau `POST /api/notifications` (`api/notifications.php`)
- **Autentikasi**: Wajib memiliki sesi login aktif (`401` jika belum login).
- **Action (`action`)**:
  - `GET ?action=feed`: Mengembalikan `unread_count`, `announcements`, dan `notifications`.
  - `POST action=mark_read` (`kind`, `id`): Menandai satu notifikasi atau pengumuman telah dibaca.
  - `POST action=mark_all_read`: Menandai seluruh notifikasi pengguna telah dibaca.
  - `POST action=create_announcement`: Membuat pengumuman resmi baru (khusus peran `superadmin`, mengembalikan `403` untuk peran lain).

---

### 4. Tautan Publik Baca-Saja (`/laporan/share`)

Menampilkan detail 1 laporan serah terima tanpa login menggunakan token HMAC SHA-256.

- **URL**: `GET /laporan/share?id={id}&token={hmac_24chars}` (`laporan/share.php`)
- **Validasi**: `hash_equals(get_report_share_token($id, $nomor_laporan), $token)` — mengembalikan tampilan akses ditolak apabila token tidak cocok atau laporan berstatus `DRAFT`.

---

### 5. Endpoint Backup Terjadwal (`cron/auto_backup`)

Memicu backup database/full sistem otomatis dan mengirimkannya ke Discord Webhook (mendukung pemecahan multi-part 8 MB).

- **CLI / Docker Crontab**:
  ```bash
  docker exec omit_shift_app php /var/www/html/cron/auto_backup.php --type=db --discord=1
  ```
- **HTTP Webhook**: `GET /cron/auto_backup?token={cron_secret_key}&type=db|full&discord=1`

---

### 6. Payload Notifikasi Eksternal

#### A. Webhook Discord (`POST {discord_webhook_url}`)
Sistem mengirimkan kartu *rich embed* setiap kali serah terima baru dibuat atau dicadangkan:
```json
{
  "username": "OMIT System Bot",
  "avatar_url": "https://domain.com/assets/logo.png",
  "embeds": [
    {
      "title": "🔔 Laporan Serah Terima Shift Baru",
      "description": "Laporan **OMIT-20261124-001** telah diterbitkan dan menunggu validasi shift penerima.",
      "color": 6641909,
      "fields": [
        {"name": "Lokasi", "value": "Control Room", "inline": true},
        {"name": "Shift", "value": "Shift Pagi", "inline": true},
        {"name": "Kondisi", "value": "Normal", "inline": true},
        {"name": "Petugas Menyerahkan", "value": "Andi Pratama", "inline": true}
      ],
      "footer": {"text": "Sistem Laporan Serah Terima Shift Operasional"}
    }
  ]
}
```
