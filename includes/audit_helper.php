<?php
// includes/audit_helper.php - Enterprise Activity Audit Trail (Auto-Migrating)

function ensure_audit_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `activity_logs` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `username` varchar(80) DEFAULT NULL,
              `action` varchar(60) NOT NULL,
              `description` varchar(255) NOT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_audit_action` (`action`,`created_at`),
              KEY `idx_audit_user` (`user_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
    }
}

function log_activity(PDO $pdo, string $action, string $description, ?int $userId = null, ?string $username = null): void {
    ensure_audit_table($pdo);
    try {
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
        if ($username === null && isset($_SESSION['username'])) {
            $username = (string)$_SESSION['username'];
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, username, action, description, ip_address)
            VALUES (:uid, :uname, :act, :desc, :ip)
        ");
        $cleanUser = mb_substr((string)($username ?? 'system'), 0, 80);
        $cleanAct  = mb_substr($action, 0, 60);
        $cleanDesc = mb_substr($description, 0, 255);
        $cleanIp   = mb_substr($ip, 0, 45);

        $stmt->execute([
            'uid'   => $userId,
            'uname' => $cleanUser,
            'act'   => $cleanAct,
            'desc'  => $cleanDesc,
            'ip'    => $cleanIp,
        ]);

        // Forward selected security/operational events to Discord Webhook (non-blocking)
        send_audit_discord_alert($cleanAct, $cleanDesc, $cleanUser, $cleanIp);
    } catch (Throwable $e) {
        // Silent fail so audit logging never disrupts business operations
    }
}

/**
 * Kirim notifikasi keamanan / audit log terpilih ke Discord Webhook (timeout cepat, silent fail).
 */
function send_audit_discord_alert(string $action, string $description, string $username, string $ip): void {
    if (!function_exists('get_setting') || !function_exists('curl_init')) {
        return;
    }
    if (get_setting('discord_audit_enabled', '0') !== '1') {
        return;
    }

    $webhook = trim(get_setting('discord_audit_webhook', ''));
    if ($webhook === '') {
        $webhook = trim(get_setting('discord_webhook_url', ''));
    }
    if ($webhook === '') {
        return;
    }

    $eventsJson = get_setting('discord_audit_events', '["login_failed","handover","admin_critical"]');
    $enabledGroups = json_decode($eventsJson, true);
    if (!is_array($enabledGroups)) {
        $enabledGroups = ['login_failed', 'handover', 'admin_critical'];
    }

    $group = 'other';
    if ($action === 'LOGIN_FAILED') {
        $group = 'login_failed';
    } elseif ($action === 'LOGIN_SUCCESS') {
        $group = 'login_success';
    } elseif (in_array($action, ['HANDOVER_CREATE', 'HANDOVER_APPROVE', 'HANDOVER_DRAFT', 'NOTULEN_FINAL', 'NOTULEN_UPDATE'], true)) {
        $group = 'handover';
    } elseif (str_starts_with($action, 'USER_') || str_starts_with($action, 'LDAP_') || in_array($action, ['SETTINGS_UPDATE', 'SYSTEM_RESTORE', 'NOTULEN_DELETE', 'CRON_BACKUP'], true)) {
        $group = 'admin_critical';
    }

    if (!in_array($group, $enabledGroups, true)) {
        return;
    }

    $color = 6641909; // Indigo
    $emoji = '🛡️';
    if ($action === 'LOGIN_FAILED' || str_contains($action, 'DELETE') || $action === 'SYSTEM_RESTORE') {
        $color = 14689132; // Rose/Red
        $emoji = '🚨';
    } elseif ($action === 'LOGIN_SUCCESS' || $action === 'HANDOVER_APPROVE' || $action === 'NOTULEN_FINAL') {
        $color = 2326507; // Emerald/Green
        $emoji = '✅';
    }

    $appName = function_exists('get_app_name') ? get_app_name() : 'OMIT Shift Handover';
    $payload = [
        'username' => "{$appName} Security",
        'embeds'   => [[
            'title'       => "{$emoji} Audit Event: {$action}",
            'description' => $description,
            'color'       => $color,
            'fields'      => [
                ['name' => 'User',       'value' => '@' . $username, 'inline' => true],
                ['name' => 'IP Address', 'value' => $ip,             'inline' => true],
                ['name' => 'Waktu (WIB)','value' => date('d/m/Y H:i:s'), 'inline' => true],
            ],
            'footer' => ['text' => "{$appName} • Security & Audit Alert"],
        ]],
    ];

    try {
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        @curl_exec($ch);
        @curl_close($ch);
    } catch (Throwable $e) {
    }
}
