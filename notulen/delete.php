<?php
// notulen/delete.php - Hapus Notulen Meeting / Briefing
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

require_login();
$user = current_user();
ensure_notulen_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM notulen_meeting WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_set('error', 'Notulen tidak ditemukan.');
    header('Location: ' . BASE_URL . 'notulen/index');
    exit;
}

if (!is_admin() && $row['created_by'] != $user['id']) {
    flash_set('error', 'Hanya pembuat notulen atau Admin yang dapat menghapus notulen ini.');
    header('Location: ' . BASE_URL . 'notulen/index');
    exit;
}

if (!empty($row['lampiran_foto'])) {
    $file = UPLOAD_DIR . $row['lampiran_foto'];
    if (file_exists($file)) @unlink($file);
}

$pdo->prepare("DELETE FROM notulen_meeting WHERE id = :id")->execute(['id' => $id]);

require_once __DIR__ . '/../includes/audit_helper.php';
log_activity($pdo, 'NOTULEN_DELETE', "Menghapus notulen {$row['nomor_notulen']} ({$row['judul']}).");

flash_set('success', "Notulen {$row['nomor_notulen']} berhasil dihapus.");
header('Location: ' . BASE_URL . 'notulen/index');
exit;
