<?php
// includes/aset_helper.php - Shared Schema & HMAC QR Token Helpers for Kunci, Alat & Aset Shift

/**
 * Ensure shared shift_assets and shift_asset_logs tables exist.
 */
function ensure_aset_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `shift_assets` (
              `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `kode_aset` VARCHAR(40) NOT NULL,
              `nama_aset` VARCHAR(150) NOT NULL,
              `kategori` ENUM('Kunci','Alat / Tools','Perangkat IT','Kartu Akses','Lainnya') NOT NULL DEFAULT 'Kunci',
              `status` ENUM('Tersedia','Dipinjam') NOT NULL DEFAULT 'Tersedia',
              `pemegang_id` INT(11) DEFAULT NULL,
              `pemegang_nama` VARCHAR(100) DEFAULT NULL,
              `waktu_pinjam` DATETIME DEFAULT NULL,
              `catatan_terakhir` VARCHAR(255) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_asset_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `shift_asset_logs` (
              `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `asset_id` INT(11) NOT NULL,
              `nama_aset` VARCHAR(150) NOT NULL,
              `aksi` ENUM('PINJAM','KEMBALI') NOT NULL,
              `user_id` INT(11) NOT NULL,
              `nama_user` VARCHAR(100) NOT NULL,
              `catatan` VARCHAR(255) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_asset_log_time` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
    }
}

/**
 * Generate compact HMAC-SHA256 token for public QR scan URLs.
 * $assetId = 0 generates the master desk/cabinet QR token (allows choosing any key).
 */
function get_aset_qr_token(int $assetId = 0): string {
    $secret = defined('APP_KEY') && APP_KEY !== ''
        ? APP_KEY
        : (defined('DB_PASS') ? DB_PASS . '|omit_aset_qr_v1' : 'omit_aset_qr_v1');
    return substr(hash_hmac('sha256', 'aset-qr:' . $assetId, $secret), 0, 20);
}

/**
 * Verify QR token using constant-time comparison.
 */
function verify_aset_qr_token(int $assetId, string $token): bool {
    if ($token === '') return false;
    return hash_equals(get_aset_qr_token($assetId), $token);
}
