<?php
// laporan/validate.php - Handler Approval & TTD Shift Baru (Multi-mode: Barcode QR, Upload Basah, Canvas)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
    $jenis_ttd  = $user['ttd_type'] ?? 'barcode';
    if (!empty($_POST['jenis_ttd_menerima'])) {
        $jenis_ttd = $_POST['jenis_ttd_menerima'];
    }
    $catatan    = trim($_POST['catatan_approval'] ?? '');

    $ttd_file   = $_FILES['ttd_upload_menerima'] ?? null;
    $ttd_base64 = $_POST['ttd_base64_menerima'] ?? null;

    if ($laporan_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM laporan_serah_terima WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $laporan_id]);
        $report = $stmt->fetch();

        if ($report) {
            // Check permission: designated receiver or admin or unassigned receiver
            if ($user['id'] == $report['petugas_menerima_id'] || is_admin() || empty($report['petugas_menerima_id'])) {
                
                $ttd_menerima = null;

                // Gunakan tanda tangan tersimpan di profil penerima (Upload TTD Basah / Canvas)
                if (($jenis_ttd === 'upload' || $jenis_ttd === 'canvas') && !empty($user['ttd_file'])) {
                    $srcPath = UPLOAD_DIR . $user['ttd_file'];
                    if (file_exists($srcPath)) {
                        $ext = pathinfo($user['ttd_file'], PATHINFO_EXTENSION);
                        $destFilename = 'signatures/ttd_baru_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        if (copy($srcPath, UPLOAD_DIR . $destFilename)) {
                            $ttd_menerima = $destFilename;
                        }
                    }
                }

                // Jika barcode QR atau fallback, generate dari signature processor
                if (empty($ttd_menerima)) {
                    $ttd_menerima = process_signature($jenis_ttd, $ttd_file, $ttd_base64, 'ttd_baru_');
                }

                $update = $pdo->prepare("
                    UPDATE laporan_serah_terima
                    SET status_validasi = 'TERVALIDASI',
                        ttd_menerima = :ttd_menerima,
                        jenis_ttd_menerima = :jenis_ttd_menerima,
                        petugas_menerima_id = :petugas_menerima_id,
                        catatan_approval = :catatan,
                        waktu_divalidasi = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    'ttd_menerima'        => $ttd_menerima,
                    'jenis_ttd_menerima'  => $jenis_ttd,
                    'petugas_menerima_id' => $report['petugas_menerima_id'] ?: $user['id'],
                    'catatan'             => $catatan,
                    'id'                  => $laporan_id
                ]);

                // Trigger Notification to Submitter (Laporan Telah Divalidasi)
                require_once __DIR__ . '/../includes/notifications.php';
                notify_handover_approved($laporan_id, $pdo);

                require_once __DIR__ . '/../includes/audit_helper.php';
                log_activity($pdo, 'HANDOVER_APPROVE', "Mengesahkan/memvalidasi laporan {$report['nomor_laporan']}.");

                flash_set('success', "Approval berhasil! Laporan {$report['nomor_laporan']} telah disahkan dan tervalidasi.");
                header('Location: ' . BASE_URL . 'laporan/detail?id=' . $laporan_id);
                exit;
            } else {
                flash_set('error', 'Anda tidak memiliki wewenang untuk menyetujui laporan ini.');
            }
        } else {
            flash_set('error', 'Laporan tidak ditemukan.');
        }
    }
}

header('Location: ' . BASE_URL . 'laporan/index');
exit;
