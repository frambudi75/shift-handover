<?php
// laporan/delete.php - Hapus Laporan (Admin Only)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Delete files if any
    $stmt = $pdo->prepare("SELECT foto_1, foto_2, foto_3, nomor_laporan FROM laporan_serah_terima WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $report = $stmt->fetch();

    if ($report) {
        foreach (['foto_1', 'foto_2', 'foto_3'] as $f) {
            if (!empty($report[$f]) && file_exists(UPLOAD_DIR . $report[$f])) {
                unlink(UPLOAD_DIR . $report[$f]);
            }
        }

        $del = $pdo->prepare("DELETE FROM laporan_serah_terima WHERE id = :id");
        $del->execute(['id' => $id]);

        flash_set('success', "Laporan {$report['nomor_laporan']} berhasil dihapus.");
    }
}

header('Location: ' . BASE_URL . 'laporan/index');
exit;

