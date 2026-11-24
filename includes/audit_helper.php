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
        $stmt->execute([
            'uid'   => $userId,
            'uname' => mb_substr((string)($username ?? 'system'), 0, 80),
            'act'   => mb_substr($action, 0, 60),
            'desc'  => mb_substr($description, 0, 255),
            'ip'    => mb_substr($ip, 0, 45),
        ]);
    } catch (Throwable $e) {
        // Silent fail so audit logging never disrupts business operations
    }
}
