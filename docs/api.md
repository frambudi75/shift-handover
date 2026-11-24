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
curl -s "http://localhost:2031/cron/auto_optimize.php?token=f91c5ca9d00a0222988eafd1d16f4264&force=1"
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

### 2. Endpoint Pencarian Cepat Global (AJAX Quick Search)

Mencari laporan, nomor tiket, dan pos lapangan secara asinkron dari header navigasi.

- **URL**: `GET /api/quick_search.php`
- **Method**: `GET`
- **Parameter**:
  - `q` (string, min 2 chars): Kata kunci pencarian.

#### Contoh Response:
```json
{
  "success": true,
  "results": [
    {
      "type": "laporan",
      "title": "OMIT-20261124-001",
      "subtitle": "Shift Pagi • Control Room (Normal)",
      "url": "laporan/detail.php?id=12"
    },
    {
      "type": "tiket",
      "title": "TK-0192: Kalibrasi Sensor Tekanan",
      "subtitle": "PIC: Andi Pratama (Target: 17:00 WIB)",
      "url": "laporan/detail.php?id=12#task-192"
    }
  ]
}
```

---

### 3. Payload Notifikasi Eksternal

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
