<?php
// includes/bell_helper.php - In-App Bell Notifications & Superadmin Broadcast Announcements

/**
 * Safely auto-create notification & announcement tables if they do not exist yet.
 * Ensures zero-downtime compatibility across Docker, XAMPP, and cPanel deployments.
 */
function ensure_bell_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `announcements` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `title` varchar(150) NOT NULL,
              `message` text NOT NULL,
              `category` enum('info','penting','darurat') NOT NULL DEFAULT 'info',
              `target_role` enum('all','admin','user') NOT NULL DEFAULT 'all',
              `show_banner` tinyint(1) NOT NULL DEFAULT 1,
              `created_by` int(11) NOT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_ann_active` (`is_active`,`show_banner`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `announcement_reads` (
              `announcement_id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`announcement_id`,`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `type` varchar(30) NOT NULL DEFAULT 'handover',
              `title` varchar(150) NOT NULL,
              `message` varchar(255) NOT NULL,
              `link_url` varchar(255) DEFAULT NULL,
              `is_read` tinyint(1) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_notif_user_read` (`user_id`,`is_read`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
        // Ignore if insufficient DDL privileges; queries below handle gracefully
    }
}

function bell_create_notification(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $linkUrl = null): void {
    if ($userId <= 0) return;
    ensure_bell_tables($pdo);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, type, title, message, link_url)
            VALUES (:uid, :type, :title, :msg, :link)
        ");
        $stmt->execute([
            'uid'   => $userId,
            'type'  => $type,
            'title' => mb_substr($title, 0, 150),
            'msg'   => mb_substr($message, 0, 255),
            'link'  => $linkUrl
        ]);
    } catch (Throwable $e) {
        // Silent fail to avoid blocking primary workflow
    }
}

function bell_role_filter(string $role): array {
    if ($role === 'superadmin') {
        return ['all', 'admin', 'user'];
    }
    if ($role === 'admin') {
        return ['all', 'admin'];
    }
    return ['all', 'user'];
}

function bell_get_feed(PDO $pdo, int $userId, string $role): array {
    ensure_bell_tables($pdo);

    $allowedTargets = bell_role_filter($role);
    $placeholders = implode(',', array_fill(0, count($allowedTargets), '?'));

    // 1. Fetch active announcements matching user role
    $announcements = [];
    $unreadAnnouncements = 0;
    try {
        $sqlAnn = "
            SELECT a.*, u.nama_lengkap AS author_name,
                   IF(ar.user_id IS NOT NULL, 1, 0) AS is_read
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.is_active = 1 AND a.target_role IN ({$placeholders})
            ORDER BY a.created_at DESC
            LIMIT 10
        ";
        $stmtAnn = $pdo->prepare($sqlAnn);
        $stmtAnn->execute(array_merge([$userId], $allowedTargets));
        $announcements = $stmtAnn->fetchAll();

        foreach ($announcements as $ann) {
            if ((int)$ann['is_read'] === 0) {
                $unreadAnnouncements++;
            }
        }
    } catch (Throwable $e) {
        $announcements = [];
    }

    // 2. Fetch user-specific notifications
    $notifications = [];
    $unreadNotifs = 0;
    try {
        $stmtNotif = $pdo->prepare("
            SELECT * FROM user_notifications
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 15
        ");
        $stmtNotif->execute(['uid' => $userId]);
        $notifications = $stmtNotif->fetchAll();

        foreach ($notifications as $n) {
            if ((int)$n['is_read'] === 0) {
                $unreadNotifs++;
            }
        }
    } catch (Throwable $e) {
        $notifications = [];
    }

    // 3. Banner announcements (active & show_banner = 1, latest 2)
    $banners = array_values(array_filter($announcements, fn($a) => (int)$a['show_banner'] === 1));
    $banners = array_slice($banners, 0, 2);

    return [
        'unread_count'  => $unreadAnnouncements + $unreadNotifs,
        'announcements' => $announcements,
        'notifications' => $notifications,
        'banners'       => $banners,
    ];
}

function bell_mark_all_read(PDO $pdo, int $userId, string $role): void {
    ensure_bell_tables($pdo);
    try {
        $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0")
            ->execute(['uid' => $userId]);

        $allowedTargets = bell_role_filter($role);
        $placeholders = implode(',', array_fill(0, count($allowedTargets), '?'));
        $stmt = $pdo->prepare("SELECT id FROM announcements WHERE is_active = 1 AND target_role IN ({$placeholders})");
        $stmt->execute($allowedTargets);
        $annIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($annIds) {
            $ins = $pdo->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)");
            foreach ($annIds as $aid) {
                $ins->execute([(int)$aid, $userId]);
            }
        }
    } catch (Throwable $e) {
    }
}

function bell_mark_one_read(PDO $pdo, int $userId, string $kind, int $itemId): void {
    if ($itemId <= 0) return;
    ensure_bell_tables($pdo);
    try {
        if ($kind === 'announcement') {
            $pdo->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)")
                ->execute([$itemId, $userId]);
        } else {
            $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
                ->execute(['id' => $itemId, 'uid' => $userId]);
        }
    } catch (Throwable $e) {
    }
}

function bell_create_announcement(PDO $pdo, int $creatorId, string $title, string $message, string $category, string $targetRole, int $showBanner): int {
    ensure_bell_tables($pdo);
    $category   = in_array($category, ['info', 'penting', 'darurat'], true) ? $category : 'info';
    $targetRole = in_array($targetRole, ['all', 'admin', 'user'], true) ? $targetRole : 'all';

    $stmt = $pdo->prepare("
        INSERT INTO announcements (title, message, category, target_role, show_banner, created_by)
        VALUES (:title, :msg, :cat, :target, :banner, :uid)
    ");
    $stmt->execute([
        'title'  => mb_substr($title, 0, 150),
        'msg'    => $message,
        'cat'    => $category,
        'target' => $targetRole,
        'banner' => $showBanner ? 1 : 0,
        'uid'    => $creatorId
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Auto-mark as read for the Superadmin who created it
    bell_mark_one_read($pdo, $creatorId, 'announcement', $newId);
    return $newId;
}

function bell_delete_announcement(PDO $pdo, int $id): void {
    ensure_bell_tables($pdo);
    $pdo->prepare("UPDATE announcements SET is_active = 0 WHERE id = :id")->execute(['id' => $id]);
}
