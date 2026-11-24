<?php
// includes/optimizer_helper.php - Core Engine Auto Optimise Sistem (Database, Storage & Garbage Collection)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/backup_helper.php';

/**
 * Dapatkan statistik ruang penyimpanan sistem (Database, Uploads, Backups)
 */
function get_system_storage_stats(PDO $pdo): array {
    // 1. Database Size
    $dbDataBytes = 0;
    $dbIndexBytes = 0;
    $dbFreeBytes = 0;
    $tables = ['app_settings', 'laporan_pekerjaan', 'laporan_serah_terima', 'master_lokasi', 'master_shift', 'users'];
    
    foreach ($tables as $t) {
        $st = $pdo->query("SHOW TABLE STATUS LIKE '$t'")->fetch(PDO::FETCH_ASSOC);
        if ($st) {
            $dbDataBytes += (int)($st['Data_length'] ?? 0);
            $dbIndexBytes += (int)($st['Index_length'] ?? 0);
            $dbFreeBytes += (int)($st['Data_free'] ?? 0);
        }
    }
    $dbTotalBytes = $dbDataBytes + $dbIndexBytes;

    // 2. Uploads Storage
    $uploadsBytes = 0;
    $uploadsFiles = 0;
    $uploadDir = rtrim(UPLOAD_DIR, '/\\');
    if (is_dir($uploadDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $name = $file->getFilename();
                if (!in_array($name, ['.htaccess', 'index.html', '.gitkeep'])) {
                    $uploadsBytes += $file->getSize();
                    $uploadsFiles++;
                }
            }
        }
    }

    // 3. Backups Storage
    $backupsBytes = 0;
    $backupsFiles = 0;
    $backupDir = get_backup_dir();
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || in_array($f, ['.htaccess', 'index.html'])) continue;
            $fp = $backupDir . $f;
            if (is_file($fp)) {
                $backupsBytes += filesize($fp);
                $backupsFiles++;
            }
        }
    }

    return [
        'db_total_bytes'    => $dbTotalBytes,
        'db_data_bytes'     => $dbDataBytes,
        'db_index_bytes'    => $dbIndexBytes,
        'db_free_bytes'     => $dbFreeBytes,
        'db_total_format'   => format_bytes($dbTotalBytes),
        'uploads_bytes'     => $uploadsBytes,
        'uploads_files'     => $uploadsFiles,
        'uploads_format'    => format_bytes($uploadsBytes),
        'backups_bytes'     => $backupsBytes,
        'backups_files'     => $backupsFiles,
        'backups_format'    => format_bytes($backupsBytes),
        'total_all_bytes'   => $dbTotalBytes + $uploadsBytes + $backupsBytes,
        'total_all_format'  => format_bytes($dbTotalBytes + $uploadsBytes + $backupsBytes)
    ];
}

/**
 * Optimasi seluruh tabel database: defragmentasi, update statistik indeks & bersihkan orphan rows
 */
function optimize_database_tables(PDO $pdo): array {
    $tables = ['app_settings', 'laporan_pekerjaan', 'laporan_serah_terima', 'master_lokasi', 'master_shift', 'users'];
    $results = [];

    // 1. Bersihkan orphan rows di laporan_pekerjaan jika ada laporan_id yang sudah tidak ada
    $orphanJobsDeleted = 0;
    try {
        $orphanJobsDeleted = $pdo->exec("
            DELETE lp FROM laporan_pekerjaan lp
            LEFT JOIN laporan_serah_terima lst ON lp.laporan_id = lst.id
            WHERE lst.id IS NULL
        ");
    } catch (PDOException $e) {
        // Abaikan jika relasi foreign key sudah cascade
    }

    // 2. Jalankan OPTIMIZE TABLE dan ANALYZE TABLE untuk setiap tabel
    foreach ($tables as $table) {
        try {
            // OPTIMIZE TABLE (reclaims unused space & defragments)
            $optRes = $pdo->query("OPTIMIZE TABLE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            // ANALYZE TABLE (refreshes index cardinality & optimizer query plan stats)
            $anaRes = $pdo->query("ANALYZE TABLE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

            $statusText = 'OK';
            if (!empty($optRes[0]['Msg_text'])) {
                $statusText = $optRes[0]['Msg_text'];
            }

            $results[$table] = [
                'status'  => $statusText,
                'analyze' => $anaRes[0]['Msg_text'] ?? 'OK'
            ];
        } catch (PDOException $e) {
            $results[$table] = [
                'status'  => 'Error: ' . $e->getMessage(),
                'analyze' => 'Error'
            ];
        }
    }

    return [
        'tables' => $results,
        'orphan_jobs_deleted' => (int)$orphanJobsDeleted
    ];
}

/**
 * Deteksi dan bersihkan file unggahan yang tidak lagi terhubung ke database (orphan files)
 */
function clean_orphan_uploads(PDO $pdo, bool $dryRun = false): array {
    $uploadDir = rtrim(UPLOAD_DIR, '/\\');
    if (!is_dir($uploadDir)) {
        return ['cleaned' => 0, 'bytes_freed' => 0, 'files' => []];
    }

    // Kumpulkan seluruh nama file yang valid terdaftar di database
    $referencedFiles = [];

    // 1. Logo Aplikasi
    $appLogo = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'app_logo'")->fetchColumn();
    if ($appLogo) {
        $referencedFiles[basename($appLogo)] = true;
    }

    // 2. Foto Dokumentasi Laporan (foto_1, foto_2, foto_3)
    $stmt = $pdo->query("SELECT foto_1, foto_2, foto_3 FROM laporan_serah_terima");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['foto_1'])) $referencedFiles[basename($row['foto_1'])] = true;
        if (!empty($row['foto_2'])) $referencedFiles[basename($row['foto_2'])] = true;
        if (!empty($row['foto_3'])) $referencedFiles[basename($row['foto_3'])] = true;
    }

    // 3. User Avatars & Signature Files
    $stmtUser = $pdo->query("SELECT foto, ttd_file FROM users");
    while ($row = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['foto'])) $referencedFiles[basename($row['foto'])] = true;
        if (!empty($row['ttd_file'])) $referencedFiles[basename($row['ttd_file'])] = true;
    }

    // Scan direktori uploads (termasuk subdirektori avatars & signatures)
    $orphanFiles = [];
    $bytesFreed = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filename = $file->getFilename();
            // Lewati file sistem pengaman
            if (in_array($filename, ['.htaccess', 'index.html', '.gitkeep'])) {
                continue;
            }

            // Jika file tidak terdaftar di database
            if (!isset($referencedFiles[$filename])) {
                $filePath = $file->getRealPath();
                $size = $file->getSize();

                // Jangan hapus file yang baru saja diunggah dalam 2 jam terakhir (mencegah hapus file form draft)
                if (time() - $file->getMTime() < 7200) {
                    continue;
                }

                $orphanFiles[] = [
                    'filename' => $filename,
                    'path'     => $filePath,
                    'size'     => $size
                ];
                $bytesFreed += $size;

                if (!$dryRun) {
                    @unlink($filePath);
                }
            }
        }
    }

    return [
        'cleaned'     => count($orphanFiles),
        'bytes_freed' => $bytesFreed,
        'files'       => $orphanFiles
    ];
}

/**
 * Rotasi arsip cadangan lama sesuai retensi hari (default 14 hari, minimal menyisakan 3 file)
 */
function purge_old_backups(int $retentionDays = 14, int $minKeep = 3, bool $dryRun = false): array {
    $backupDir = get_backup_dir();
    $history = get_backup_history();

    if (count($history) <= $minKeep) {
        return ['purged' => 0, 'bytes_freed' => 0, 'files' => []];
    }

    $cutoffTime = time() - ($retentionDays * 86400);
    $purgedFiles = [];
    $bytesFreed = 0;

    // Riwayat sudah terurut dari yang terbaru ke terlama
    // Pertahankan minimal $minKeep berkas terbaru tanpa peduli tanggal
    $eligibleForPurge = array_slice($history, $minKeep);

    foreach ($eligibleForPurge as $b) {
        if ($b['mtime'] < $cutoffTime) {
            $filePath = $b['path'];
            if (file_exists($filePath)) {
                $purgedFiles[] = $b['filename'];
                $bytesFreed += $b['filesize_raw'];

                if (!$dryRun) {
                    @unlink($filePath);
                }
            }
        }
    }

    return [
        'purged'      => count($purgedFiles),
        'bytes_freed' => $bytesFreed,
        'files'       => $purgedFiles
    ];
}

/**
 * Eksekusi optimasi sistem penuh (Database, Storage, Purge Cache)
 */
function run_system_optimization(PDO $pdo, bool $purgeOrphans = true, ?int $retentionDays = null): array {
    if ($retentionDays === null) {
        $retentionDays = (int)get_setting('backup_retention_days', '14');
        if ($retentionDays < 1) $retentionDays = 14;
    }

    $startTime = microtime(true);

    // 1. Optimasi Tabel Database & Indeks
    $dbRes = optimize_database_tables($pdo);

    // 2. Pembersihan File Orphan
    $orphanRes = ['cleaned' => 0, 'bytes_freed' => 0, 'files' => []];
    if ($purgeOrphans) {
        $orphanRes = clean_orphan_uploads($pdo, false);
    }

    // 3. Rotasi Backup Lama
    $backupRes = purge_old_backups($retentionDays, 3, false);

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
    $totalBytesFreed = $orphanRes['bytes_freed'] + $backupRes['bytes_freed'];

    // Simpan ringkasan hasil optimasi ke app_settings
    $now = date('Y-m-d H:i:s');
    $summary = [
        'last_run'           => $now,
        'duration_ms'        => $durationMs,
        'tables_count'       => count($dbRes['tables']),
        'orphan_jobs'        => $dbRes['orphan_jobs_deleted'],
        'orphans_cleaned'    => $orphanRes['cleaned'],
        'orphans_bytes'      => $orphanRes['bytes_freed'],
        'backups_purged'     => $backupRes['purged'],
        'backups_bytes'      => $backupRes['bytes_freed'],
        'total_bytes_freed'  => $totalBytesFreed,
        'total_freed_format' => format_bytes($totalBytesFreed)
    ];

    set_setting('last_optimized_at', $now);
    set_setting('last_optimized_summary', json_encode($summary));

    return [
        'success'           => true,
        'timestamp'         => $now,
        'duration_ms'       => $durationMs,
        'db_results'        => $dbRes,
        'orphan_results'    => $orphanRes,
        'backup_results'    => $backupRes,
        'total_bytes_freed' => $totalBytesFreed,
        'summary'           => $summary,
        'message'           => "Optimasi sistem berhasil diselesaikan dalam {$durationMs} ms. Ruang disk dibersihkan: " . format_bytes($totalBytesFreed)
    ];
}

/**
 * Pengecekan otomatis berkala (Opportunistic Auto-Optimization)
 * Dijalankan otomatis di latar belakang saat interval waktu telah terlewati.
 */
function check_and_run_auto_optimization(PDO $pdo): ?array {
    $autoEnabled = get_setting('auto_optimize_enabled', '1') === '1';
    if (!$autoEnabled) {
        return null;
    }

    // Interval hari (default: 7 hari)
    $intervalDays = (int)get_setting('auto_optimize_interval', '7');
    if ($intervalDays < 1) $intervalDays = 7;

    $lastRun = get_setting('last_optimized_at', '');
    $shouldRun = false;

    if (empty($lastRun)) {
        $shouldRun = true;
    } else {
        $lastTimestamp = strtotime($lastRun);
        if ($lastTimestamp === false || (time() - $lastTimestamp) >= ($intervalDays * 86400)) {
            $shouldRun = true;
        }
    }

    if ($shouldRun) {
        return run_system_optimization($pdo);
    }

    return null;
}
