<?php
// includes/aset_helper.php - Shared Schema, HMAC QR Token & Notification Helpers for Kunci, Alat & Aset Shift

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

/**
 * Notify active officers (in-app Bell) and Discord webhook on QR guest borrow/return.
 */
function notify_aset_activity(PDO $pdo, string $title, string $message, int $colorHex = 0x6558f5): void {
    try {
        require_once __DIR__ . '/bell_helper.php';
        $uStmt = $pdo->query("SELECT id FROM users WHERE is_active = 1 LIMIT 50");
        foreach ($uStmt->fetchAll() as $u) {
            bell_create_notification($pdo, (int)$u['id'], 'aset', $title, $message, BASE_URL . 'aset/index');
        }
    } catch (Throwable $e) {
    }

    try {
        $webhook = trim((string)get_setting('discord_webhook_url', ''));
        if ($webhook !== '' && function_exists('curl_init')) {
            $payload = json_encode([
                'embeds' => [[
                    'title'       => $title,
                    'description' => $message,
                    'color'       => $colorHex,
                    'timestamp'   => gmdate('c'),
                    'footer'      => ['text' => get_app_name() . ' · Log Kunci & Aset'],
                ]],
            ]);
            $ch = curl_init($webhook);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT        => 2,
            ]);
            @curl_exec($ch);
            @curl_close($ch);
        }
    } catch (Throwable $e) {
    }
}

/**
 * Fetch borrowed assets exceeding $hoursThreshold hours without return.
 */
function get_overdue_borrowed_assets(PDO $pdo, int $hoursThreshold = 12): array {
    try {
        $stmt = $pdo->prepare("
            SELECT id, kode_aset, nama_aset, pemegang_nama, waktu_pinjam
            FROM shift_assets
            WHERE status = 'Dipinjam'
              AND waktu_pinjam IS NOT NULL
              AND waktu_pinjam <= DATE_SUB(NOW(), INTERVAL :hrs HOUR)
            ORDER BY waktu_pinjam ASC
        ");
        $stmt->bindValue(':hrs', $hoursThreshold, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
