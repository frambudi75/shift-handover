<?php
// includes/backup_helper.php - Core Engine Backup Database & Full System (Download, Email, Discord)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notifications.php';

/**
 * Format bytes to readable human unit (B, KB, MB, GB)
 */
function format_bytes(int|float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min((int)$pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get path to backup directory, ensure it exists and is protected
 */
function get_backup_dir(): string {
    $dir = realpath(__DIR__ . '/../storage/backups');
    if (!$dir) {
        $target = __DIR__ . '/../storage/backups';
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }
        $dir = realpath($target) ?: $target;
    }
    
    // Ensure security files exist
    $htaccess = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
    }
    $indexHtml = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'index.html';
    if (!file_exists($indexHtml)) {
        @file_put_contents($indexHtml, "<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>Access Denied</h1></body></html>");
    }

    return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
}

/**
 * Dump entire MySQL database to an SQL file using PDO
 */
function dump_mysql_database(PDO $pdo, string $destPath): array {
    $handle = @fopen($destPath, 'w');
    if (!$handle) {
        return ['success' => false, 'message' => "Gagal membuat file tujuan backup di {$destPath}"];
    }

    $dbName = 'db_serah_terima';
    $timeNow = date('Y-m-d H:i:s');
    $appName = get_app_name();

    // SQL Header
    $header = "-- ============================================================\n"
            . "-- {$appName} - Cadangan Database MySQL (Full Backup)\n"
            . "-- Tanggal Dibuat: {$timeNow} WIB\n"
            . "-- Server Database: MySQL / MariaDB via PDO\n"
            . "-- ============================================================\n\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n"
            . "SET time_zone = \"+00:00\";\n"
            . "SET NAMES utf8mb4;\n\n";
    fwrite($handle, $header);

    // Get list of tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        fwrite($handle, "-- ------------------------------------------------------------\n");
        fwrite($handle, "-- Struktur Tabel: `{$table}`\n");
        fwrite($handle, "-- ------------------------------------------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        if (!empty($createStmt[1])) {
            fwrite($handle, $createStmt[1] . ";\n\n");
        }

        // Dump Table Data in Batches
        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($rowCount > 0) {
            fwrite($handle, "-- Data untuk Tabel: `{$table}` ({$rowCount} baris)\n");

            $batchSize = 250;
            $offset = 0;

            while ($offset < $rowCount) {
                $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$offset}, {$batchSize}")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) break;

                $columns = array_keys($rows[0]);
                $colNames = '`' . implode('`, `', $columns) . '`';

                $valStrings = [];
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $colVal) {
                        if ($colVal === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($colVal);
                        }
                    }
                    $valStrings[] = "(" . implode(', ', $vals) . ")";
                }

                $insertSql = "INSERT INTO `{$table}` ({$colNames}) VALUES\n" . implode(",\n", $valStrings) . ";\n";
                fwrite($handle, $insertSql);
                $offset += $batchSize;
            }
            fwrite($handle, "\n");
        }
    }

    // SQL Footer
    $footer = "SET FOREIGN_KEY_CHECKS = 1;\n"
            . "-- ============================================================\n"
            . "-- Selesai mencadangkan " . count($tables) . " tabel pada {$timeNow}\n"
            . "-- ============================================================\n";
    fwrite($handle, $footer);
    fclose($handle);

    return [
        'success'   => true,
        'filepath'  => $destPath,
        'filename'  => basename($destPath),
        'filesize'  => filesize($destPath),
        'table_cnt' => count($tables)
    ];
}

/**
 * Generate a standalone database backup file (.sql)
 */
function generate_database_backup(PDO $pdo, bool $compressZip = false): array {
    $dir = get_backup_dir();
    $timestamp = date('Ymd_His');
    $sqlFilename = "backup_db_{$timestamp}.sql";
    $sqlPath = $dir . $sqlFilename;

    $res = dump_mysql_database($pdo, $sqlPath);
    if (!$res['success']) {
        return $res;
    }

    // Compress to ZIP if requested and ZipArchive is supported
    if ($compressZip && class_exists('ZipArchive')) {
        $zipFilename = "backup_db_{$timestamp}.zip";
        $zipPath = $dir . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($sqlPath, $sqlFilename);
            $zip->close();
            @unlink($sqlPath); // Remove uncompressed SQL

            return [
                'success'   => true,
                'type'      => 'database',
                'filepath'  => $zipPath,
                'filename'  => $zipFilename,
                'filesize'  => filesize($zipPath),
                'message'   => "Backup database berhasil dibuat (terkompresi ZIP)."
            ];
        }
    }

    return [
        'success'   => true,
        'type'      => 'database',
        'filepath'  => $sqlPath,
        'filename'  => $sqlFilename,
        'filesize'  => filesize($sqlPath),
        'message'   => "Backup database MySQL (.sql) berhasil dibuat."
    ];
}

/**
 * Generate a complete full system backup archive (.zip)
 * Contains all PHP source codes, assets, uploads, config, AND a fresh MySQL database dump!
 */
function generate_full_system_backup(PDO $pdo): array {
    $dir = get_backup_dir();
    $timestamp = date('Ymd_His');
    $zipFilename = "backup_full_system_{$timestamp}.zip";
    $zipPath = $dir . $zipFilename;

    // 1. Generate database dump first
    $tempSqlName = "database_dump_{$timestamp}.sql";
    $tempSqlPath = $dir . $tempSqlName;
    $dumpRes = dump_mysql_database($pdo, $tempSqlPath);
    if (!$dumpRes['success']) {
        return $dumpRes;
    }

    $projectRoot = realpath(__DIR__ . '/..');

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempSqlPath);
            return ['success' => false, 'message' => 'Gagal membuat file arsip ZIP untuk full system backup.'];
        }

        // Add the database dump into the root of zip
        $zip->addFile($tempSqlPath, 'database/dump.sql');

        // Excluded folders & files
        $excludeDirs = [
            'storage' . DIRECTORY_SEPARATOR . 'backups',
            'storage/backups',
            '.git',
            'node_modules',
            '.gemini'
        ];

        // Recursive directory scanner
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getRealPath();
            $relativePath = substr($itemPath, strlen($projectRoot) + 1);

            // Check exclusions
            $isExcluded = false;
            foreach ($excludeDirs as $exc) {
                if (str_contains($relativePath, $exc)) {
                    $isExcluded = true;
                    break;
                }
            }

            // Exclude backup zips themselves
            if (str_ends_with($relativePath, '.zip') || str_ends_with($relativePath, '.sql')) {
                if (str_contains($relativePath, 'storage') || str_contains($relativePath, 'backup')) {
                    $isExcluded = true;
                }
            }

            if ($isExcluded) continue;

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($item->isFile()) {
                $zip->addFile($itemPath, $relativePath);
            }
        }

        $zip->close();
        @unlink($tempSqlPath); // Remove temporary dump

        return [
            'success'  => true,
            'type'     => 'full_system',
            'filepath' => $zipPath,
            'filename' => $zipFilename,
            'filesize' => filesize($zipPath),
            'message'  => "Backup full sistem & database berhasil dibuat dalam format ZIP."
        ];
    } else {
        @unlink($tempSqlPath);
        return ['success' => false, 'message' => 'Ekstensi PHP ZipArchive tidak aktif pada server. Harap aktifkan extension=zip pada php.ini.'];
    }
}

/**
 * Scan backup directory and return list of all stored backups
 */
function get_backup_history(): array {
    $dir = get_backup_dir();
    $files = @scandir($dir);
    $history = [];

    if (!$files) return [];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') continue;

        $path = $dir . $file;
        if (!is_file($path)) continue;

        $size = filesize($path);
        $time = filemtime($path);

        $type = 'unknown';
        if (str_starts_with($file, 'backup_full_system_')) {
            $type = 'full_system';
        } elseif (str_starts_with($file, 'backup_db_')) {
            $type = 'database';
        }

        $history[] = [
            'filename'           => $file,
            'filepath'           => $path,
            'filesize'           => $size,
            'filesize_formatted' => format_bytes($size),
            'type'               => $type,
            'created_at'         => $time,
            'created_formatted'  => date('d/m/Y H:i:s', $time) . ' WIB',
            'extension'          => strtolower(pathinfo($file, PATHINFO_EXTENSION))
        ];
    }

    // Sort newest first
    usort($history, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

    return $history;
}

/**
 * Send backup file to Discord Webhook with rich embed & file attachment
 */
function send_backup_to_discord(string $filePath, string $backupType, array $currentUser): array {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File backup tidak ditemukan.'];
    }

    $webhookUrl = trim(get_setting('discord_webhook_url', ''));
    if (empty($webhookUrl)) {
        return [
            'success' => false,
            'message' => 'URL Discord Webhook belum diatur. Silakan atur URL Webhook di Pengaturan Aplikasi terlebih dahulu.'
        ];
    }

    if (!filter_var($webhookUrl, FILTER_VALIDATE_URL) || (!str_contains($webhookUrl, 'discord.com/api/webhooks') && !str_contains($webhookUrl, 'discordapp.com/api/webhooks'))) {
        return [
            'success' => false,
            'message' => 'Format URL Discord Webhook tidak valid. Pastikan diawali dengan https://discord.com/api/webhooks/...'
        ];
    }

    $filesize = filesize($filePath);
    $formattedSize = format_bytes($filesize);
    $appName = get_app_name();
    $filename = basename($filePath);
    $typeLabel = ($backupType === 'database' || str_contains($filename, 'backup_db')) ? '💾 Database MySQL' : '📦 Full Sistem & Database';

    // Discord message payload with NoteFlow theme color (6641909 = #6558f5)
    $payload = [
        'username'   => "{$appName} Backup Engine",
        'avatar_url' => 'https://raw.githubusercontent.com/google/material-design-icons/master/png/action/backup/materialicons/48dp/2x/baseline_backup_white_48dp.png',
        'embeds'     => [
            [
                'title'       => "📦 Notifikasi Cadangan Sistem: {$typeLabel}",
                'description' => "File cadangan sistem serah terima telah dibuat dan dikirimkan secara otomatis dari server.",
                'color'       => 6641909,
                'fields'      => [
                    [
                        'name'   => '📋 Jenis Cadangan',
                        'value'  => $typeLabel,
                        'inline' => true
                    ],
                    [
                        'name'   => '📊 Ukuran File',
                        'value'  => $formattedSize,
                        'inline' => true
                    ],
                    [
                        'name'   => '⏰ Waktu Dibuat',
                        'value'  => date('d/m/Y H:i:s') . ' WIB',
                        'inline' => true
                    ],
                    [
                        'name'   => '👤 Eksekutor',
                        'value'  => $currentUser['nama_lengkap'] ?? 'Super Administrator',
                        'inline' => true
                    ],
                    [
                        'name'   => '📁 Nama Berkas',
                        'value'  => "`{$filename}`",
                        'inline' => false
                    ]
                ],
                'footer' => [
                    'text' => "{$appName} Automated Disaster Recovery System"
                ],
                'timestamp' => date('c')
            ]
        ]
    ];

    // Safe 8 MB threshold per part (compatible with all Discord 10MB/25MB webhook limits)
    $chunkSize = 8 * 1024 * 1024;

    if ($filesize <= $chunkSize) {
        $cfile = new CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $filename);
        $postData = [
            'payload_json' => json_encode($payload),
            'files[0]'     => $cfile
        ];
        $res = discord_post_multipart($webhookUrl, $postData);
        if (!$res['success']) {
            return $res;
        }
        return ['success' => true, 'message' => "Backup '{$filename}' berhasil dikirim ke saluran Discord!"];
    }

    // Multi-part split upload for files > 8 MB
    $totalParts = (int)ceil($filesize / $chunkSize);
    $handle = @fopen($filePath, 'rb');
    if (!$handle) {
        return ['success' => false, 'message' => 'Gagal membuka file backup untuk dipecah menjadi beberapa part.'];
    }

    $tmpDir = sys_get_temp_dir();
    $partIndex = 1;
    $sentParts = 0;

    while (!feof($handle)) {
        $buffer = fread($handle, $chunkSize);
        if ($buffer === false || $buffer === '') {
            break;
        }

        $partSuffix = sprintf('.part%03d', $partIndex);
        $partName   = $filename . $partSuffix;
        $tmpPart    = rtrim($tmpDir, '/\\') . DIRECTORY_SEPARATOR . uniqid('omit_dc_', true) . '_' . $partName;

        if (file_put_contents($tmpPart, $buffer) === false) {
            fclose($handle);
            return ['success' => false, 'message' => "Gagal menulis berkas sementara untuk Part {$partIndex}."];
        }
        unset($buffer); // Free RAM immediately

        if ($partIndex === 1) {
            $partPayload = $payload;
            $partPayload['embeds'][0]['title'] = "📦 Notifikasi Cadangan Sistem: {$typeLabel} (Part 1/{$totalParts})";
            $partPayload['embeds'][0]['fields'][] = [
                'name'   => '🧩 Pengiriman Multi-Part',
                'value'  => "Berkas dipecah menjadi **{$totalParts} bagian** (maks. 8 MB/part).\nGabungkan kembali: `cat {$filename}.part* > {$filename}` (Linux/Mac) atau `copy /b {$filename}.part* {$filename}` (Windows).",
                'inline' => false
            ];
        } else {
            $partPayload = [
                'username'   => "{$appName} Backup Engine",
                'avatar_url' => $payload['avatar_url'],
                'embeds'     => [
                    [
                        'title'       => "📦 Lanjutan Backup: {$filename} (Part {$partIndex}/{$totalParts})",
                        'description' => "Bagian {$partIndex} dari {$totalParts} • Berkas `{$partName}`",
                        'color'       => 6641909,
                        'footer'      => ['text' => "{$appName} Multi-Part Disaster Recovery"],
                        'timestamp'   => date('c')
                    ]
                ]
            ];
        }

        $cfile = new CURLFile($tmpPart, 'application/octet-stream', $partName);
        $res = discord_post_multipart($webhookUrl, [
            'payload_json' => json_encode($partPayload),
            'files[0]'     => $cfile
        ]);
        @unlink($tmpPart);

        if (!$res['success']) {
            fclose($handle);
            return ['success' => false, 'message' => "Gagal mengirim Part {$partIndex}/{$totalParts} ke Discord: " . $res['message']];
        }

        $sentParts++;
        $partIndex++;
        if (!feof($handle)) {
            usleep(350000); // 350ms pause to respect Discord rate limits
        }
    }

    fclose($handle);
    return [
        'success' => true,
        'message' => "Backup '{$filename}' ({$formattedSize}) berhasil dipecah dan dikirim ke Discord dalam {$sentParts} part!"
    ];
}

/**
 * Helper to execute multipart/form-data POST to Discord Webhook
 */
function discord_post_multipart(string $webhookUrl, array $postData): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => "Gagal menghubungi Discord Webhook: {$curlErr}"];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'OK'];
    }
    return ['success' => false, 'message' => "HTTP {$httpCode}: " . substr((string)$response, 0, 250)];
}

/**
 * Send backup file via Email (SMTP) with attachment
 */
function send_backup_to_email(string $filePath, string $backupType, string $toEmail, array $currentUser): array {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File backup tidak ditemukan.'];
    }

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Alamat email tujuan tidak valid.'];
    }

    $enabled = (int)get_setting('smtp_enabled', 0);
    if (!$enabled) {
        return ['success' => false, 'message' => 'Layanan SMTP dinonaktifkan di Pengaturan Aplikasi.'];
    }

    $appName = get_app_name();
    $filename = basename($filePath);
    $filesize = filesize($filePath);
    $formattedSize = format_bytes($filesize);
    $typeLabel = ($backupType === 'database' || str_contains($filename, 'backup_db')) ? 'Database MySQL (.sql)' : 'Full Sistem & Database (.zip)';

    $subject = "[BACKUP SISTEM] {$appName} - {$filename}";

    $maxEmailAttach = 20 * 1024 * 1024; // 20 MB standard email attachment ceiling

    $htmlBody = "
    <div style='font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#f7f7fb;color:#181b2c;'>
      <div style='background:linear-gradient(135deg,#6558f5,#5143eb);padding:28px 24px;border-radius:18px;text-align:center;color:#ffffff;'>
        <h2 style='margin:0;font-size:20px;font-weight:800;'>CADANGAN SISTEM BERHASIL DIBUAT</h2>
        <p style='margin:6px 0 0;font-size:13px;opacity:0.9;'>{$appName} — Disaster Recovery Archive</p>
      </div>

      <div style='background:#ffffff;margin-top:16px;padding:24px;border-radius:18px;border:1px solid #ebebf7;font-size:13px;line-height:1.6;'>
        <p>Halo <strong>" . sanitize($currentUser['nama_lengkap'] ?? 'Administrator') . "</strong>,</p>
        <p>Berikut adalah berkas cadangan data sistem serah terima yang dihasilkan secara otomatis:</p>

        <table style='width:100%;border-collapse:collapse;margin:18px 0;font-size:12.5px;'>
          <tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;color:#64748b;font-weight:bold;width:40%;'>Jenis Cadangan</td><td style='padding:8px 0;font-weight:bold;color:#181b2c;'>{$typeLabel}</td></tr>
          <tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;color:#64748b;font-weight:bold;'>Nama Berkas</td><td style='padding:8px 0;font-family:monospace;color:#6558f5;font-weight:bold;'>{$filename}</td></tr>
          <tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;color:#64748b;font-weight:bold;'>Ukuran Berkas</td><td style='padding:8px 0;font-weight:bold;'>{$formattedSize}</td></tr>
          <tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;color:#64748b;font-weight:bold;'>Waktu Pembuatan</td><td style='padding:8px 0;'>" . date('d/m/Y H:i:s') . " WIB</td></tr>
        </table>
    ";

    $attachFile = null;
    if ($filesize <= $maxEmailAttach) {
        $attachFile = $filePath;
        $htmlBody .= "<p style='color:#1c7a52;background:#e6f7f0;padding:12px 14px;border-radius:12px;font-weight:bold;margin:16px 0;font-size:12px;'>✓ Berkas cadangan terlampir langsung pada email ini.</p>";
    } else {
        $htmlBody .= "<p style='color:#b45309;background:#fef3c7;padding:12px 14px;border-radius:12px;font-weight:bold;margin:16px 0;font-size:12px;'>⚠️ Berkas berukuran {$formattedSize} (melebihi limit lampiran email 20 MB). Berkas tersimpan di server dan dapat diunduh melalui panel Manajemen Backup OMIT.</p>";
    }

    $htmlBody .= "
      </div>
      <div style='text-align:center;padding:18px;font-size:11px;color:#94a3b8;'>
        Email ini dikirimkan secara otomatis oleh {$appName}. Simpan berkas cadangan ini di tempat yang aman.
      </div>
    </div>
    ";

    return send_smtp_email($toEmail, $currentUser['nama_lengkap'] ?? 'Admin', $subject, $htmlBody, $attachFile);
}

/**
 * Safely delete a backup file
 */
function delete_backup_file(string $filename): array {
    $clean = basename($filename);
    $dir = get_backup_dir();
    $path = $dir . $clean;

    if (!file_exists($path)) {
        return ['success' => false, 'message' => "Berkas '{$clean}' tidak ditemukan."];
    }

    if (@unlink($path)) {
        return ['success' => true, 'message' => "Berkas cadangan '{$clean}' berhasil dihapus permanen."];
    }

    return ['success' => false, 'message' => "Gagal menghapus berkas '{$clean}'."];
}
