<?php
// cron/auto_backup.php - Scheduled Automated Backup (CLI / Docker Crontab & HTTP Token Webhook)
// Usage CLI : php /var/www/html/cron/auto_backup.php --type=db --discord=1
// Usage HTTP: GET /cron/auto_backup?token=YOUR_CRON_SECRET&type=db|full&discord=1

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_helper.php';
require_once __DIR__ . '/../includes/audit_helper.php';

$isCli = (php_sapi_name() === 'cli');

// Default parameters
$type        = 'db'; // db | full
$sendDiscord = true;

if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--type=')) {
            $type = substr($arg, 7) === 'full' ? 'full' : 'db';
        } elseif (str_starts_with($arg, '--discord=')) {
            $sendDiscord = substr($arg, 10) !== '0';
        }
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    $expectedToken = get_setting('cron_secret_key', '');
    $givenToken    = trim($_GET['token'] ?? '');
    if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token cron tidak valid.']);
        exit;
    }
    $type        = ($_GET['type'] ?? 'db') === 'full' ? 'full' : 'db';
    $sendDiscord = ($_GET['discord'] ?? '1') !== '0';
}

$res = ($type === 'full')
    ? generate_full_system_backup($pdo)
    : generate_database_backup($pdo, true);

$discordResult = null;
if (!empty($res['success']) && $sendDiscord && trim(get_setting('discord_webhook_url', '')) !== '') {
    $discordResult = send_backup_to_discord($res['filepath'], $type === 'full' ? 'system' : 'database', [
        'nama_lengkap' => 'Cron Scheduler',
    ]);
}

$logMsg = ($res['success'] ? 'Berhasil' : 'Gagal') . " menjalankan backup terjadwal ({$type}): " . ($res['filename'] ?? $res['message']);
log_activity($pdo, 'CRON_BACKUP', $logMsg, null, 'cron');

if ($isCli) {
    echo '[' . date('Y-m-d H:i:s') . " WIB] {$logMsg}\n";
    if ($discordResult) {
        echo '[Discord] ' . ($discordResult['message'] ?? '') . "\n";
    }
    exit($res['success'] ? 0 : 1);
}

echo json_encode([
    'success' => (bool)$res['success'],
    'type'    => $type,
    'file'    => $res['filename'] ?? null,
    'size'    => $res['filesize'] ?? 0,
    'message' => $res['message'] ?? '',
    'discord' => $discordResult,
]);
