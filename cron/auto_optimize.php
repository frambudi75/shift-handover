<?php
// cron/auto_optimize.php - Skrip Otomatisasi Pemeliharaan & Optimasi Sistem (CLI / Cron Job)
if (php_sapi_name() !== 'cli') {
    // Jika dipanggil via HTTP GET / Webhook, wajib menyertakan secret token
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';

    $token = $_GET['token'] ?? '';
    $cronKey = get_setting('cron_secret_key', '');
    if (empty($cronKey)) {
        $cronKey = md5((defined('DB_PASS') ? DB_PASS : '') . (defined('DB_NAME') ? DB_NAME : 'db_serah_terima') . 'omit_cron_salt');
        set_setting('cron_secret_key', $cronKey);
    }

    if (empty($token) || !hash_equals($cronKey, $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Akses ditolak: Token cron tidak valid atau tidak disertakan.']);
        exit;
    }
} else {
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
}

require_once __DIR__ . '/../includes/optimizer_helper.php';

$isCli = (php_sapi_name() === 'cli');
$force = false;
$verbose = false;

if ($isCli) {
    global $argv;
    $force = in_array('--force', $argv ?? []);
    $verbose = in_array('--verbose', $argv ?? []) || in_array('-v', $argv ?? []);
} else {
    $force = isset($_GET['force']);
    $verbose = isset($_GET['verbose']);
}

if ($verbose && $isCli) {
    echo "====================================================\n";
    echo "   " . get_app_name() . " - AUTO SYSTEM OPTIMIZER   \n";
    echo "====================================================\n";
    echo "Waktu Mulai: " . date('Y-m-d H:i:s') . "\n";
}

if ($force) {
    $res = run_system_optimization($pdo);
} else {
    $res = check_and_run_auto_optimization($pdo);
    if ($res === null) {
        $lastRun = get_setting('last_optimized_at', 'Belum pernah');
        $interval = get_setting('auto_optimize_interval', '7');
        $msg = "Optimasi dilewati: Interval {$interval} hari belum terlewati. (Terakhir jalan: {$lastRun})";
        if ($isCli) {
            echo "[INFO] {$msg}\n";
            echo "Gunakan flag --force untuk memaksa eksekusi sekarang.\n";
            exit(0);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'skipped' => true, 'message' => $msg]);
            exit;
        }
    }
}

if ($isCli) {
    echo "[SUKSES] " . $res['message'] . "\n";
    if ($verbose) {
        echo "Tabel Dioptimasi : " . count($res['db_results']['tables']) . " tabel\n";
        echo "Orphan Jobs     : " . $res['db_results']['orphan_jobs_deleted'] . "\n";
        echo "File Orphan     : " . $res['orphan_results']['cleaned'] . " berkas (" . format_bytes($res['orphan_results']['bytes_freed']) . ")\n";
        echo "Backup Dirotasi : " . $res['backup_results']['purged'] . " berkas (" . format_bytes($res['backup_results']['bytes_freed']) . ")\n";
        echo "Total Ruang Hemat: " . format_bytes($res['total_bytes_freed']) . "\n";
        echo "Waktu Selesai   : " . date('Y-m-d H:i:s') . "\n";
        echo "====================================================\n";
    }
    exit(0);
} else {
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}
