<?php
// api/notifications.php - AJAX Endpoint for Bell Notifications & Superadmin Announcements
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bell_helper.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];
$role   = $user['role'] ?? 'user';

$action = $_REQUEST['action'] ?? 'feed';

if ($action === 'feed') {
    $feed = bell_get_feed($pdo, $userId, $role);
    echo json_encode([
        'success' => true,
        'is_superadmin' => is_superadmin(),
        'unread_count' => $feed['unread_count'],
        'announcements' => $feed['announcements'],
        'notifications' => $feed['notifications'],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if ($action === 'mark_all_read') {
    bell_mark_all_read($pdo, $userId, $role);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_read') {
    $kind = $_POST['kind'] ?? 'notification';
    $id   = (int)($_POST['id'] ?? 0);
    bell_mark_one_read($pdo, $userId, $kind, $id);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'create_announcement') {
    if (!is_superadmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat membuat pengumuman.']);
        exit;
    }
    $title      = trim($_POST['title'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $category   = $_POST['category'] ?? 'info';
    $targetRole = $_POST['target_role'] ?? 'all';
    $showBanner = !empty($_POST['show_banner']) ? 1 : 0;

    if ($title === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Judul dan isi pengumuman wajib diisi.']);
        exit;
    }

    $id = bell_create_announcement($pdo, $userId, $title, $message, $category, $targetRole, $showBanner);
    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Pengumuman berhasil dipublikasikan!']);
    exit;
}

if ($action === 'delete_announcement') {
    if (!is_superadmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat menghapus pengumuman.']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        bell_delete_announcement($pdo, $id);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
