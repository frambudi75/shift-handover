<?php
// master/backup.php - Manajemen Cadangan Sistem (Database MySQL & Full Aplikasi)
$current_page = 'master_backup';
$page_title = 'Pusat Backup & Disaster Recovery';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_helper.php';
require_once __DIR__ . '/../includes/optimizer_helper.php';
require_once __DIR__ . '/../includes/notifications.php';

require_superadmin();
$user = current_user();

// Handle Direct Download Action (Must be handled before header output)
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $filename = basename($_GET['file'] ?? '');
    $filePath = get_backup_dir() . $filename;

    if (!empty($filename) && file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        flash_set('error', 'File cadangan yang diminta tidak ditemukan atau telah dihapus.');
        header('Location: ' . BASE_URL . 'master/backup');
        exit;
    }
}

// Handle Form Submissions & Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'backup_db') {
        $compress = !empty($_POST['compress_zip']);
        $res = generate_database_backup($pdo, $compress);
        if ($res['success']) {
            flash_set('success', "Sukses! {$res['message']} (" . format_bytes($res['filesize']) . ")");
        } else {
            flash_set('error', "Gagal membuat backup database: {$res['message']}");
        }
    } elseif ($action === 'backup_system') {
        $res = generate_full_system_backup($pdo);
        if ($res['success']) {
            flash_set('success', "Sukses! {$res['message']} (" . format_bytes($res['filesize']) . ")");
        } else {
            flash_set('error', "Gagal membuat backup full sistem: {$res['message']}");
        }
    } elseif ($action === 'send_discord') {
        $filename = basename($_POST['filename'] ?? '');
        $filePath = get_backup_dir() . $filename;
        $type = $_POST['type'] ?? 'database';

        $res = send_backup_to_discord($filePath, $type, $user);
        if ($res['success']) {
            flash_set('success', $res['message']);
        } else {
            flash_set('error', $res['message']);
        }
    } elseif ($action === 'send_email') {
        $filename = basename($_POST['filename'] ?? '');
        $filePath = get_backup_dir() . $filename;
        $toEmail = trim($_POST['email'] ?? '');
        $type = $_POST['type'] ?? 'database';

        $res = send_backup_to_email($filePath, $type, $toEmail, $user);
        if ($res['success']) {
            flash_set('success', "Sukses! Berkas backup '{$filename}' berhasil dikirimkan ke {$toEmail}.");
        } else {
            flash_set('error', "Gagal mengirimkan backup via email: {$res['message']}");
        }
    } elseif ($action === 'delete') {
        $filename = basename($_POST['filename'] ?? '');
        $res = delete_backup_file($filename);
        if ($res['success']) {
            flash_set('success', $res['message']);
        } else {
            flash_set('error', $res['message']);
        }
    } elseif ($action === 'restore_existing') {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya Superadmin yang dapat melakukan Restore Sistem.');
        } else {
            $filename = basename($_POST['filename'] ?? '');
            $filePath = get_backup_dir() . $filename;
            $preserveConfig = !isset($_POST['overwrite_app_files']);
            $res = restore_system_from_backup($pdo, $filePath, $preserveConfig);
            require_once __DIR__ . '/../includes/audit_helper.php';
            log_activity($pdo, 'SYSTEM_RESTORE', ($res['success'] ? 'Berhasil' : 'Gagal') . " restore dari arsip '{$filename}'.");
            flash_set($res['success'] ? 'success' : 'error', $res['message']);
        }
    } elseif ($action === 'restore_upload') {
        if (!is_superadmin()) {
            flash_set('error', 'Hanya Superadmin yang dapat melakukan Restore / Migrasi Sistem.');
        } elseif (empty($_FILES['restore_file']['tmp_name']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'Silakan pilih file backup (.sql atau .zip) yang valid untuk di-restore.');
        } else {
            $origName = basename($_FILES['restore_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['sql', 'zip'], true)) {
                flash_set('error', 'Format file harus .sql atau .zip.');
            } else {
                $uploadedPath = get_backup_dir() . 'uploaded_restore_' . date('Ymd_His') . '.' . $ext;
                if (move_uploaded_file($_FILES['restore_file']['tmp_name'], $uploadedPath)) {
                    $preserveConfig = !empty($_POST['preserve_config']);
                    $res = restore_system_from_backup($pdo, $uploadedPath, $preserveConfig);
                    require_once __DIR__ . '/../includes/audit_helper.php';
                    log_activity($pdo, 'SYSTEM_RESTORE', ($res['success'] ? 'Berhasil' : 'Gagal') . " restore/migrasi dari file upload '{$origName}'.");
                    flash_set($res['success'] ? 'success' : 'error', $res['message']);
                } else {
                    flash_set('error', 'Gagal memindahkan file upload ke direktori penyimpanan.');
                }
            }
        }
    } elseif ($action === 'save_discord_config') {
        $webhook = trim($_POST['discord_webhook_url'] ?? '');
        set_setting('discord_webhook_url', $webhook);
        flash_set('success', 'URL Discord Webhook berhasil disimpan.');
    } elseif ($action === 'test_discord') {
        $webhook = trim(get_setting('discord_webhook_url', ''));
        if (empty($webhook)) {
            flash_set('error', 'Masukkan dan simpan URL Discord Webhook terlebih dahulu!');
        } else {
            $appName = get_app_name();
            $payload = [
                'username' => "{$appName} Bot",
                'embeds' => [
                    [
                        'title' => '🎮 Uji Coba Koneksi Discord Webhook Berhasil!',
                        'description' => "Webhook berhasil terhubung dengan sistem **{$appName}**. Notifikasi dan berkas cadangan otomatis kini siap dikirim ke saluran ini.",
                        'color' => 6641909,
                        'fields' => [
                            ['name' => 'Waktu Uji Coba', 'value' => date('d/m/Y H:i:s') . ' WIB', 'inline' => true],
                            ['name' => 'Operator', 'value' => $user['nama_lengkap'], 'inline' => true]
                        ],
                        'footer' => ['text' => 'OMIT Disaster Recovery System']
                    ]
                ]
            ];

            $ch = curl_init($webhook);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            unset($ch);

            if ($http >= 200 && $http < 300) {
                flash_set('success', 'Koneksi Discord Webhook berhasil! Pesan uji coba telah masuk ke server Discord Anda.');
            } else {
                flash_set('error', "Gagal menghubungi Discord (HTTP {$http}): " . ($err ?: substr($resp, 0, 200)));
            }
        }
    } elseif ($action === 'run_optimization') {
        $res = run_system_optimization($pdo);
        if ($res['success']) {
            $msg = "⚡ {$res['message']} (Tabel: " . count($res['db_results']['tables']) . ", File Orphan Dibersihkan: " . $res['orphan_results']['cleaned'] . ", Cadangan Dirotasi: " . $res['backup_results']['purged'] . ")";
            flash_set('success', $msg);
        } else {
            flash_set('error', "Gagal melakukan optimasi: " . ($res['message'] ?? 'Unknown error'));
        }
    } elseif ($action === 'save_optimize_settings') {
        $autoOpt = isset($_POST['auto_optimize_enabled']) ? '1' : '0';
        $interval = (int)($_POST['auto_optimize_interval'] ?? 7);
        $retention = (int)($_POST['backup_retention_days'] ?? 14);

        set_setting('auto_optimize_enabled', $autoOpt);
        set_setting('auto_optimize_interval', (string)max(1, $interval));
        set_setting('backup_retention_days', (string)max(1, $retention));

        flash_set('success', 'Konfigurasi Auto-Optimise dan retensi berkas cadangan berhasil diperbarui.');
    } elseif ($action === 'flush_cache') {
        cache_flush();
        flash_set('success', '⚡ Seluruh Redis & Memory Cache berhasil dikosongkan.');
    }

    header('Location: ' . BASE_URL . 'master/backup');
    exit;
}

// Fetch Backup History
$backups = get_backup_history();

// Storage & System Optimization Information
$storageStats = get_system_storage_stats($pdo);
$autoOptimizeEnabled = get_setting('auto_optimize_enabled', '1') === '1';
$autoOptimizeInterval = (int)get_setting('auto_optimize_interval', '7');
$backupRetentionDays = (int)get_setting('backup_retention_days', '14');
$lastOptimizedAt = get_setting('last_optimized_at', '');
$lastOptimizedSummary = json_decode(get_setting('last_optimized_summary', '{}'), true);
$cronSecretKey = get_setting('cron_secret_key', '');
if (empty($cronSecretKey)) {
    $cronSecretKey = md5((defined('DB_PASS') ? DB_PASS : '') . (defined('DB_NAME') ? DB_NAME : 'db_serah_terima') . 'omit_cron_salt');
    set_setting('cron_secret_key', $cronSecretKey);
}

// Calculate total storage
$totalBytes = $storageStats['backups_bytes'];

$discordWebhookUrl = get_setting('discord_webhook_url', '');
$smtpEnabled       = (int)get_setting('smtp_enabled', 0);
$cacheStats        = cache_get_stats();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1 pb-12">

  <!-- HEADER BANNER & STATS CHIPS -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-shield-check"></i>
        <span>Disaster Recovery &amp; Pemeliharaan Otomatis</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-database text-[#6558f5]"></i>
        <span>Pusat Backup &amp; Pemulihan Sistem</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Cadangkan data operasional MySQL dan seluruh sistem aplikasi dengan opsi unduh langsung, kirim email, Discord Webhook, serta fitur Auto-Optimise
      </p>
    </div>

    <!-- Quick Status Pills -->
    <div class="flex items-center gap-2.5 flex-wrap">
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full <?= $autoOptimizeEnabled ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
        <span class="text-slate-500">Auto-Optimise:</span>
        <span class="<?= $autoOptimizeEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' ?>"><?= $autoOptimizeEnabled ? 'Aktif (' . $autoOptimizeInterval . 'h)' : 'Nonaktif' ?></span>
      </div>

      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5]"></span>
        <span class="text-slate-500">Berkas Cadangan:</span>
        <span class="text-[#6558f5] dark:text-indigo-400"><?= count($backups) ?> (<?= format_bytes($totalBytes) ?>)</span>
      </div>

      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full <?= !empty($discordWebhookUrl) ? 'bg-[#5865F2]' : 'bg-slate-400' ?>"></span>
        <span class="text-slate-500">Discord:</span>
        <span class="<?= !empty($discordWebhookUrl) ? 'text-[#5865F2]' : 'text-slate-400' ?>"><?= !empty($discordWebhookUrl) ? 'Terhubung' : 'Belum Diatur' ?></span>
      </div>
    </div>
  </div>

  <!-- SECTION 1: METRIK KAPASITAS PENYIMPANAN SISTEM -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Metric 1: Database MySQL -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[22px] border border-slate-100 dark:border-slate-800/80 p-5 shadow-feature widget-card flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400">Database MySQL</span>
        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold">
          <i class="ph-bold ph-database"></i>
        </div>
      </div>
      <div class="my-3">
        <div class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $storageStats['db_total_format'] ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5">Data: <?= format_bytes($storageStats['db_data_bytes']) ?> &bull; Indeks: <?= format_bytes($storageStats['db_index_bytes']) ?></div>
      </div>
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between text-[11px]">
        <span class="text-emerald-600 dark:text-emerald-400 font-extrabold flex items-center gap-1">
          <i class="ph-bold ph-check-circle"></i> Teroptimasi B-Tree
        </span>
        <span class="text-slate-400">6 Tabel</span>
      </div>
    </div>

    <!-- Metric 2: Uploads & Dokumentasi -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[22px] border border-slate-100 dark:border-slate-800/80 p-5 shadow-feature widget-card flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400">Berkas Upload &amp; Foto</span>
        <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center font-bold">
          <i class="ph-bold ph-images"></i>
        </div>
      </div>
      <div class="my-3">
        <div class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $storageStats['uploads_format'] ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5"><?= $storageStats['uploads_files'] ?> berkas fisik aktif</div>
      </div>
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between text-[11px]">
        <span class="text-slate-500 font-semibold flex items-center gap-1">
          <i class="ph-bold ph-sparkle text-amber-500"></i> Kompresi GD Aktif
        </span>
        <span class="text-slate-400">uploads/</span>
      </div>
    </div>

    <!-- Metric 3: Arsip Cadangan -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[22px] border border-slate-100 dark:border-slate-800/80 p-5 shadow-feature widget-card flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400">Ruang Cadangan (Backup)</span>
        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center font-bold">
          <i class="ph-bold ph-archive"></i>
        </div>
      </div>
      <div class="my-3">
        <div class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $storageStats['backups_format'] ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5"><?= $storageStats['backups_files'] ?> berkas arsip tersimpan</div>
      </div>
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between text-[11px]">
        <span class="text-slate-500 font-semibold flex items-center gap-1">
          <i class="ph-bold ph-clock-counter-clockwise text-indigo-500"></i> Retensi <?= $backupRetentionDays ?> Hari
        </span>
        <span class="text-slate-400">storage/backups/</span>
      </div>
    </div>

    <!-- Metric 4: Total Storage Terpakai -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[22px] border border-slate-100 dark:border-slate-800/80 p-5 shadow-feature widget-card flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400">Total Tracked Storage</span>
        <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 flex items-center justify-center font-bold">
          <i class="ph-bold ph-hard-drives"></i>
        </div>
      </div>
      <div class="my-3">
        <div class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $storageStats['total_all_format'] ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5">DB + Uploads + Backup lokal</div>
      </div>
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between text-[11px]">
        <span class="text-emerald-600 dark:text-emerald-400 font-extrabold flex items-center gap-1">
          <i class="ph-bold ph-activity"></i> Kondisi Sangat Ringan
        </span>
        <span class="text-slate-400">&lt; 1% Kuota</span>
      </div>
    </div>
  </div>

  <!-- SECTION 2: AUTO-OPTIMISE & PEMELIHARAAN SISTEM PANEL -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 widget-card">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
      
      <!-- Left Info -->
      <div class="space-y-3 max-w-2xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-lightning"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-[#181b2c] dark:text-white flex items-center gap-2">
              <span>Auto-Optimise &amp; Pemeliharaan Otomatis</span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black <?= $autoOptimizeEnabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-700' ?>">
                <?= $autoOptimizeEnabled ? 'OTOMATIS AKTIF' : 'NONAKTIF' ?>
              </span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Menjaga performa tetap secepat kilat: defragmentasi tabel MySQL, pembaruan statistik optimizer kueri, pembersihan file upload sampah (orphan), serta rotasi arsip cadangan lama secara otomatis.
            </p>
          </div>
        </div>

        <!-- Badges & Last Run Stats -->
        <div class="flex items-center gap-2 flex-wrap pt-1 text-xs">
          <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center gap-1.5 font-semibold">
            <i class="ph-bold ph-clock text-[#6558f5]"></i>
            <span>Jadwal: <strong>Tiap <?= $autoOptimizeInterval ?> Hari</strong></span>
          </div>

          <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center gap-1.5 font-semibold">
            <i class="ph-bold ph-shield-check text-emerald-500"></i>
            <span>Retensi Backup: <strong>Maks <?= $backupRetentionDays ?> Hari</strong></span>
          </div>

          <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center gap-1.5 font-semibold">
            <i class="ph-bold ph-calendar-check text-blue-500"></i>
            <span>Terakhir: <strong><?= !empty($lastOptimizedAt) ? date('d/m/Y H:i', strtotime($lastOptimizedAt)) : 'Belum pernah' ?></strong></span>
          </div>

          <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center gap-1.5 font-semibold">
            <i class="ph-bold ph-database text-rose-500"></i>
            <span>Cache: <strong class="<?= $cacheStats['active'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' ?>"><?= $cacheStats['active'] ? 'Redis Aktif (' . $cacheStats['memory_human'] . ')' : 'In-Memory (Safe Fallback)' ?></strong></span>
          </div>

          <?php if (!empty($lastOptimizedSummary['total_freed_format'])): ?>
            <div class="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 flex items-center gap-1.5 font-extrabold">
              <i class="ph-bold ph-trash"></i>
              <span>Hemat: <?= $lastOptimizedSummary['total_freed_format'] ?> (<?= $lastOptimizedSummary['duration_ms'] ?? 0 ?> ms)</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Action Buttons -->
      <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
        <!-- Form Manual Run Optimization -->
        <form method="POST" action="" onsubmit="return confirm('Jalankan proses optimasi database, defragmentasi, dan pembersihan file orphan sekarang?')">
          <input type="hidden" name="action" value="run_optimization" />
          <button type="submit" class="px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
            <i class="ph-bold ph-lightning text-base"></i>
            <span>Optimalkan Sekarang</span>
          </button>
        </form>

        <!-- Flush Cache Button -->
        <form method="POST" action="" onsubmit="return confirm('Kosongkan seluruh Redis & memory cache sekarang?')">
          <input type="hidden" name="action" value="flush_cache" />
          <button type="submit" class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-slate-700 dark:text-slate-200 hover:text-rose-600 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer" title="Bersihkan cache Redis & query memory">
            <i class="ph-bold ph-broom text-base text-rose-500"></i>
            <span>Flush Cache</span>
          </button>
        </form>

        <!-- Button Open Settings Modal -->
        <button type="button" onclick="openAutoOptimizeModal()" class="px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-sliders text-base text-[#6558f5]"></i>
          <span>Pengaturan Auto</span>
        </button>

        <!-- Button Open Cron Guide Modal -->
        <button type="button" onclick="openCronGuideModal()" class="px-4 py-2.5 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 hover:bg-[#e0e0fb] text-[#6558f5] dark:text-indigo-300 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-terminal-window text-base"></i>
          <span>Info CLI / Cron</span>
        </button>
      </div>

    </div>
  </div>

  <!-- ACTION CARDS: DATABASE BACKUP & FULL SYSTEM BACKUP -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- CARD 1: BACKUP DATABASE MYSQL -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 flex flex-col justify-between widget-card">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-2xl font-black shadow-sm">
            <i class="ph-bold ph-database"></i>
          </div>
          <span class="px-3 py-1 rounded-full bg-indigo-50 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300 text-[11px] font-extrabold">
            SQL &amp; Relasi Data
          </span>
        </div>

        <h3 class="text-base sm:text-lg font-black text-[#181b2c] dark:text-white">
          Cadangkan Database MySQL (.sql)
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
          Mencadangkan seluruh skema tabel, data laporan serah terima, rincian pekerjaan, riwayat approval, jadwal shift, dan pengguna. Sangat ringan dan cepat untuk pemulihan instan.
        </p>

        <ul class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
          <li class="flex items-center gap-2">
            <i class="ph-bold ph-check text-emerald-500"></i>
            <span>Mencakup seluruh tabel dan constraint integritas data.</span>
          </li>
          <li class="flex items-center gap-2">
            <i class="ph-bold ph-check text-emerald-500"></i>
            <span>Kompatibel untuk import via phpMyAdmin, CLI MySQL, atau DBeaver.</span>
          </li>
        </ul>
      </div>

      <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center gap-3 flex-wrap">
        <!-- Form Backup SQL Standard -->
        <form method="POST" action="" class="inline">
          <input type="hidden" name="action" value="backup_db" />
          <input type="hidden" name="compress_zip" value="0" />
          <button type="submit" class="px-5 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
            <i class="ph-bold ph-floppy-disk text-base"></i>
            <span>Backup Database (.sql)</span>
          </button>
        </form>

        <!-- Form Backup SQL Compressed ZIP -->
        <form method="POST" action="" class="inline">
          <input type="hidden" name="action" value="backup_db" />
          <input type="hidden" name="compress_zip" value="1" />
          <button type="submit" class="px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
            <i class="ph-bold ph-file-zip text-base text-[#6558f5]"></i>
            <span>Kompresi (.zip)</span>
          </button>
        </form>
      </div>
    </div>

    <!-- CARD 2: BACKUP FULL SISTEM APLIKASI -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 flex flex-col justify-between widget-card">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl font-black shadow-sm">
            <i class="ph-bold ph-archive"></i>
          </div>
          <span class="px-3 py-1 rounded-full bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-300 text-[11px] font-extrabold">
            Full Source Code &amp; Berkas
          </span>
        </div>

        <h3 class="text-base sm:text-lg font-black text-[#181b2c] dark:text-white">
          Cadangkan Full Sistem Aplikasi (.zip)
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
          Mencadangkan seluruh kode program, aset grafis, berkas unggahan bukti foto &amp; tanda tangan, konfigurasi, serta dump database MySQL terbaru dalam satu file ZIP utuh siap migrasi.
        </p>

        <ul class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
          <li class="flex items-center gap-2">
            <i class="ph-bold ph-check text-emerald-500"></i>
            <span>Semua folder: <code>uploads/</code>, <code>config/</code>, <code>includes/</code>, <code>laporan/</code>.</span>
          </li>
          <li class="flex items-center gap-2">
            <i class="ph-bold ph-check text-emerald-500"></i>
            <span>Dilengkapi salinan database <code>database/dump.sql</code> di dalam arsip.</span>
          </li>
        </ul>
      </div>

      <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6">
        <form method="POST" action="">
          <input type="hidden" name="action" value="backup_system" />
          <button type="submit" class="px-6 py-2.5 rounded-2xl bg-gradient-to-r from-purple-600 to-[#6558f5] hover:from-purple-700 hover:to-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
            <i class="ph-bold ph-package text-base"></i>
            <span>Buat Full Backup Sistem &amp; Data (.zip)</span>
          </button>
        </form>
      </div>
    </div>

  </div>

  <!-- INTEGRATION CONFIGURATION CARD: DISCORD WEBHOOK -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 widget-card">
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3 mb-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-[#5865F2]/10 text-[#5865F2] flex items-center justify-center text-2xl font-bold shadow-sm">
          <i class="ph-bold ph-discord-logo"></i>
        </div>
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Integrasi Pengiriman ke Saluran Discord Webhook</h3>
          <p class="text-xs text-slate-400">Kirim notifikasi otomatis dan lampiran berkas cadangan langsung ke channel server Discord tim Anda</p>
        </div>
      </div>

      <?php if (!empty($discordWebhookUrl)): ?>
        <form method="POST" action="" class="inline">
          <input type="hidden" name="action" value="test_discord" />
          <button type="submit" class="px-4 py-2 rounded-xl bg-[#5865F2]/10 text-[#5865F2] hover:bg-[#5865F2] hover:text-white font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
            <i class="ph-bold ph-paper-plane-tilt text-sm"></i>
            <span>Uji Coba Kirim ke Discord</span>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="save_discord_config" />
      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
          URL Webhook Discord
        </label>
        <div class="flex flex-col sm:flex-row gap-2.5">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <i class="ph-bold ph-link text-sm"></i>
            </span>
            <input type="url" name="discord_webhook_url" value="<?= sanitize($discordWebhookUrl) ?>" placeholder="https://discord.com/api/webhooks/1234567890/abcdefg..."
                   class="w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#5865F2]/40 focus:outline-none transition" />
          </div>
          <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#5865F2] hover:bg-[#4752c4] text-white font-extrabold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
            <i class="ph-bold ph-floppy-disk"></i>
            <span>Simpan Webhook</span>
          </button>
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5 leading-relaxed">
          💡 Cara membuat Webhook Discord: Buka Server Discord &gt; Pengaturan Saluran/Channel (&gt; Integrations) &gt; Webhooks &gt; New Webhook &gt; Copy Webhook URL.
        </p>
      </div>
    </form>
  </div>

  <!-- BACKUP HISTORY TABLE -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden widget-card">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5] animate-ping"></span>
        <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">
          Daftar Arsip Cadangan Tersimpan (<?= count($backups) ?> Berkas)
        </h3>
      </div>
      <span class="text-[11px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full">
        Folder: storage/backups/ (Dilindungi .htaccess)
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3.5 px-5 whitespace-nowrap">Nama Berkas</th>
            <th class="py-3.5 px-4 whitespace-nowrap">Jenis Cadangan</th>
            <th class="py-3.5 px-4 whitespace-nowrap">Ukuran</th>
            <th class="py-3.5 px-4 whitespace-nowrap">Waktu Dibuat</th>
            <th class="py-3.5 px-5 text-center whitespace-nowrap min-w-[280px]">Opsi Pengiriman &amp; Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
          <?php if (empty($backups)): ?>
            <tr>
              <td colspan="5" class="py-12 text-center text-slate-400">
                <div class="flex flex-col items-center justify-center gap-2">
                  <i class="ph-thin ph-database text-4xl text-slate-300 dark:text-slate-600"></i>
                  <p class="text-xs font-semibold">Belum ada file cadangan yang dibuat.</p>
                  <p class="text-[11px] text-slate-400">Pilih salah satu opsi cadangan di atas untuk membuat file backup pertama Anda.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($backups as $b): ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition">
                <td class="py-3.5 px-5 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 shadow-2xs <?= $b['type'] === 'full_system' ? 'bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-indigo-50 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300' ?>">
                      <i class="ph-bold <?= $b['type'] === 'full_system' ? 'ph-package' : 'ph-file-sql' ?>"></i>
                    </div>
                    <div>
                      <div class="font-bold text-[#181b2c] dark:text-white font-mono text-xs">
                        <?= sanitize($b['filename']) ?>
                      </div>
                      <div class="text-[10.5px] text-slate-400">
                        Format: .<?= strtoupper($b['extension']) ?>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <?php if ($b['type'] === 'full_system'): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800">
                      <i class="ph-bold ph-package"></i>
                      <span>Full Sistem &amp; DB</span>
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-[#6558f5] dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800">
                      <i class="ph-bold ph-database"></i>
                      <span>Database MySQL</span>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap font-mono font-bold text-slate-700 dark:text-slate-200">
                  <?= $b['filesize_formatted'] ?>
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 dark:text-slate-400 text-xs">
                  <?= $b['created_formatted'] ?>
                </td>
                <td class="py-3.5 px-5 text-center whitespace-nowrap">
                  <div class="inline-flex items-center justify-center gap-1.5">

                    <!-- Direct Download Button -->
                    <a href="?action=download&file=<?= urlencode($b['filename']) ?>" 
                       title="Unduh Berkas ke Komputer"
                       class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-[#efeffb] hover:bg-[#e0dffc] dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-[#6558f5] dark:text-indigo-300 transition flex items-center gap-1 shadow-2xs">
                      <i class="ph-bold ph-download-simple text-xs"></i>
                      <span>Unduh</span>
                    </a>

                    <!-- Send to Email Button -->
                    <button type="button" 
                            onclick="openSendEmailModal('<?= htmlspecialchars($b['filename'], ENT_QUOTES) ?>', '<?= $b['type'] ?>')"
                            title="Kirim ke Email via SMTP"
                            class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 transition flex items-center gap-1 border border-emerald-200/80 dark:border-emerald-800 shadow-2xs cursor-pointer">
                      <i class="ph-bold ph-envelope-simple text-xs"></i>
                      <span>Email</span>
                    </button>

                    <!-- Discord Button -->
                    <button type="button" 
                            onclick="openSendDiscordModal('<?= htmlspecialchars($b['filename'], ENT_QUOTES) ?>', '<?= $b['type'] ?>')"
                            title="Kirim Berkas ke Saluran Discord"
                            class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-[#5865F2]/10 hover:bg-[#5865F2]/20 text-[#5865F2] dark:text-[#7983f5] transition flex items-center gap-1 border border-[#5865F2]/30 shadow-2xs cursor-pointer">
                      <i class="ph-bold ph-discord-logo text-xs"></i>
                      <span>Discord</span>
                    </button>

                    <?php if (is_superadmin()): ?>
                      <form method="POST" action="" class="inline"
                            onsubmit="return confirm('PERHATIAN: Restore dari <?= htmlspecialchars($b['filename'], ENT_QUOTES) ?> akan menimpa database saat ini (snapshot pengaman dibuat otomatis). Lanjutkan?')">
                        <input type="hidden" name="action" value="restore_existing" />
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($b['filename'], ENT_QUOTES) ?>" />
                        <button type="submit" title="Restore Sistem dari Arsip Ini (Superadmin)"
                                class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 transition flex items-center gap-1 border border-amber-200/80 dark:border-amber-800 shadow-2xs cursor-pointer">
                          <i class="ph-bold ph-arrow-counter-clockwise text-xs"></i>
                          <span>Restore</span>
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Delete Button -->
                    <button type="button" 
                            onclick="openDeleteBackupModal('<?= htmlspecialchars($b['filename'], ENT_QUOTES) ?>')"
                            title="Hapus Berkas Cadangan"
                            class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 transition flex items-center gap-1 border border-rose-200/80 dark:border-rose-800 shadow-2xs cursor-pointer">
                      <i class="ph-bold ph-trash text-xs"></i>
                      <span>Hapus</span>
                    </button>

                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (is_superadmin()): ?>
    <!-- RESTORE & MIGRASI SISTEM CARD (Superadmin Only) -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-amber-200/80 dark:border-amber-800/60 shadow-feature p-6 sm:p-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0">
            <i class="ph-fill ph-arrow-counter-clockwise"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-[#181b2c] dark:text-white">Restore &amp; Migrasi Sistem (Upload File Backup)</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Unggah file <code>.sql</code> atau <code>.zip</code> hasil backup dari server lama untuk memulihkan database &amp; foto lampiran sekaligus (snapshot pengaman dibuat otomatis).
            </p>
          </div>
        </div>
        <span class="px-3 py-1 rounded-full bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-300 text-[10px] font-extrabold uppercase self-start sm:self-auto">
          Khusus Superadmin
        </span>
      </div>

      <form method="POST" action="" enctype="multipart/form-data"
            onsubmit="return confirm('PERINGATAN: Proses Restore akan menimpa database (dan folder uploads jika arsip Full System). Snapshot pengaman akan dibuat otomatis sebelum restore. Yakin lanjutkan?')"
            class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <input type="hidden" name="action" value="restore_upload" />
        <div class="md:col-span-5">
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Pilih File Backup (.sql / .zip)</label>
          <input type="file" name="restore_file" accept=".sql,.zip" required
                 class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-[#6558f5] file:text-white file:font-bold file:cursor-pointer" />
        </div>
        <div class="md:col-span-4 flex items-center pb-2">
          <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer">
            <input type="checkbox" name="preserve_config" value="1" checked class="rounded text-[#6558f5]" />
            <span>Pertahankan konfigurasi DB server ini (Migrasi Aman)</span>
          </label>
        </div>
        <div class="md:col-span-3">
          <button type="submit"
                  class="w-full py-2.5 px-5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
            <i class="ph-bold ph-upload-simple text-base"></i>
            <span>Restore Sekarang</span>
          </button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</div>

<!-- MODAL SEND TO EMAIL -->
<div id="sendEmailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeSendEmailModal()"></div>

  <div class="relative w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-envelope-simple"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Kirim Cadangan via Email</h3>
          <p class="text-[11px] text-slate-400">Kirimkan lampiran file backup melalui server SMTP</p>
        </div>
      </div>
      <button type="button" onclick="closeSendEmailModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <!-- Target File Info -->
    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 flex items-center gap-3">
      <i class="ph-bold ph-file-zip text-2xl text-[#6558f5]"></i>
      <div class="overflow-hidden">
        <div class="font-extrabold text-xs text-slate-900 dark:text-white truncate" id="modalEmailFilename">nama_file.zip</div>
        <div class="text-[10.5px] text-slate-400">Akan dilampirkan langsung pada pesan email</div>
      </div>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="send_email" />
      <input type="hidden" name="filename" id="modalEmailTargetFilename" value="" />
      <input type="hidden" name="type" id="modalEmailTargetType" value="" />

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Alamat Email Penerima *</label>
        <input type="email" name="email" required value="<?= sanitize($user['email'] ?? '') ?>" placeholder="admin@operasional.id"
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-emerald-500/40 focus:outline-none transition" />
        <p class="text-[10.5px] text-slate-400 mt-1">Batas lampiran maksimal penyedia email adalah 20-25 MB.</p>
      </div>

      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeSendEmailModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-paper-plane-tilt"></i>
          <span>Kirim Sekarang</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL SEND TO DISCORD -->
<div id="sendDiscordModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeSendDiscordModal()"></div>

  <div class="relative w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-[#5865F2]/10 text-[#5865F2] flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-discord-logo"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Kirim ke Saluran Discord</h3>
          <p class="text-[11px] text-slate-400">Unggah berkas cadangan ke channel server Discord</p>
        </div>
      </div>
      <button type="button" onclick="closeSendDiscordModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <!-- Target File Info -->
    <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-slate-900/60 border border-indigo-100 dark:border-slate-800 flex items-center gap-3">
      <i class="ph-bold ph-package text-2xl text-[#5865F2]"></i>
      <div class="overflow-hidden">
        <div class="font-extrabold text-xs text-slate-900 dark:text-white truncate" id="modalDiscordFilename">nama_file.zip</div>
        <div class="text-[10.5px] text-slate-500 dark:text-slate-400">Akan diunggah langsung ke Discord Webhook</div>
      </div>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="send_discord" />
      <input type="hidden" name="filename" id="modalDiscordTargetFilename" value="" />
      <input type="hidden" name="type" id="modalDiscordTargetType" value="" />

      <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
        Saluran Discord akan menerima ringkasan pesan Rich Embed beserta lampiran berkas cadangan (hingga ukuran 25 MB).
      </p>

      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeSendDiscordModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-[#5865F2] hover:bg-[#4752c4] active:scale-95 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-upload-simple"></i>
          <span>Unggah ke Discord</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL PENGATURAN AUTO-OPTIMISE -->
<div id="autoOptimizeModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAutoOptimizeModal()"></div>

  <div class="relative w-full max-w-lg bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-5 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-sliders"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Pengaturan Auto-Optimise Sistem</h3>
          <p class="text-[11px] text-slate-400">Jadwal pembersihan dan retensi ruang server</p>
        </div>
      </div>
      <button type="button" onclick="closeAutoOptimizeModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
        <i class="ph-bold ph-x text-base"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="save_optimize_settings" />

      <!-- Toggle Auto-Optimise Enabled -->
      <div class="flex items-center justify-between p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800">
        <div>
          <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">Jalankan Auto-Optimise Berkala</h4>
          <p class="text-[11px] text-slate-400 mt-0.5">Otomatis defragment tabel, bersihkan sampah &amp; rotasi cadangan</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="auto_optimize_enabled" value="1" <?= $autoOptimizeEnabled ? 'checked' : '' ?> class="sr-only peer" />
          <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
        </label>
      </div>

      <!-- Select Interval Hari -->
      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
          Interval Rutinitas Pemeliharaan
        </label>
        <select name="auto_optimize_interval" class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/40 focus:outline-none cursor-pointer">
          <option value="3" <?= $autoOptimizeInterval === 3 ? 'selected' : '' ?>>Setiap 3 Hari (Sangat Aktif)</option>
          <option value="7" <?= $autoOptimizeInterval === 7 ? 'selected' : '' ?>>Setiap 7 Hari / 1 Minggu (Rekomendasi Standar)</option>
          <option value="14" <?= $autoOptimizeInterval === 14 ? 'selected' : '' ?>>Setiap 14 Hari / 2 Minggu</option>
          <option value="30" <?= $autoOptimizeInterval === 30 ? 'selected' : '' ?>>Setiap 30 Hari / 1 Bulan</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-1">Sistem akan memeriksa dan mengeksekusi optimasi jika interval hari telah terlewati.</p>
      </div>

      <!-- Select Retention Days -->
      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
          Masa Retensi Berkas Cadangan (Backup Purge)
        </label>
        <select name="backup_retention_days" class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/40 focus:outline-none cursor-pointer">
          <option value="7" <?= $backupRetentionDays === 7 ? 'selected' : '' ?>>Simpan 7 Hari Terakhir</option>
          <option value="14" <?= $backupRetentionDays === 14 ? 'selected' : '' ?>>Simpan 14 Hari Terakhir (Rekomendasi Standar)</option>
          <option value="30" <?= $backupRetentionDays === 30 ? 'selected' : '' ?>>Simpan 30 Hari Terakhir (1 Bulan)</option>
          <option value="60" <?= $backupRetentionDays === 60 ? 'selected' : '' ?>>Simpan 60 Hari Terakhir (2 Bulan)</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-1">Arsip cadangan yang lebih lama dari waktu di atas akan dibersihkan otomatis. Sistem menjamin minimal 3 berkas cadangan terbaru akan selalu dipertahankan.</p>
      </div>

      <div class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/40 text-[11.5px] text-amber-900 dark:text-amber-200 leading-relaxed">
        <strong>Apa yang dilakukan oleh Auto-Optimise?</strong><br>
        1. <code>OPTIMIZE TABLE</code> &amp; <code>ANALYZE TABLE</code> pada 6 tabel utama MySQL.<br>
        2. Mendeteksi dan menghapus file foto/gambar orphan yang tidak lagi memiliki tautan di database.<br>
        3. Menjaga ruang penyimpanan server tetap lega dan mencegah disk penuh.
      </div>

      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeAutoOptimizeModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
          <i class="ph-bold ph-floppy-disk"></i>
          <span>Simpan Kebijakan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL PANDUAN CRON / CLI -->
<div id="cronGuideModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCronGuideModal()"></div>

  <div class="relative w-full max-w-lg bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-terminal-window"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Panduan Background Cron / CLI</h3>
          <p class="text-[11px] text-slate-400">Jalankan optimasi otomatis via server scheduler</p>
        </div>
      </div>
      <button type="button" onclick="closeCronGuideModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
        <i class="ph-bold ph-x text-base"></i>
      </button>
    </div>

    <div class="space-y-4 text-xs text-slate-600 dark:text-slate-300">
      <div>
        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-1.5">
          <i class="ph-bold ph-database text-indigo-500"></i>
          <span>1. Crontab Backup Otomatis (Docker Host Ubuntu):</span>
        </h4>
        <div class="p-3 rounded-xl bg-slate-900 text-emerald-400 font-mono text-[10.5px] select-all break-all leading-relaxed space-y-1.5">
          <div># Backup Database (.zip) setiap hari pukul 02:00 + kirim Discord:</div>
          <div>0 2 * * * docker exec omit_shift_app php /var/www/html/cron/auto_backup.php --type=db --discord=1 &gt; /dev/null 2&gt;&amp;1</div>
          <div class="pt-1"># Backup Full System (.zip) setiap Minggu pukul 02:30 + kirim Discord:</div>
          <div>30 2 * * 0 docker exec omit_shift_app php /var/www/html/cron/auto_backup.php --type=full --discord=1 &gt; /dev/null 2&gt;&amp;1</div>
        </div>
      </div>

      <div>
        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-1.5">
          <i class="ph-bold ph-linux-logo text-amber-500"></i>
          <span>2. Crontab Auto-Optimise &amp; Rotasi Retensi (03:00 WIB):</span>
        </h4>
        <div class="p-3 rounded-xl bg-slate-900 text-emerald-400 font-mono text-[10.5px] select-all break-all leading-relaxed">
          0 3 * * * docker exec omit_shift_app php /var/www/html/cron/auto_optimize.php &gt; /dev/null 2&gt;&amp;1
        </div>
      </div>

      <div>
        <h4 class="font-extrabold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-1.5">
          <i class="ph-bold ph-globe text-purple-500"></i>
          <span>3. Webhook / URL Cron (Eksternal UptimeBot / EasyCron / curl):</span>
        </h4>
        <div class="p-3 rounded-xl bg-slate-900 text-amber-300 font-mono text-[10.5px] select-all break-all leading-relaxed space-y-1.5">
          <div><?= htmlspecialchars(get_full_web_url('cron/auto_backup?token=' . $cronSecretKey . '&type=db&discord=1')) ?></div>
          <div><?= htmlspecialchars(get_full_web_url('cron/auto_optimize?token=' . $cronSecretKey)) ?></div>
        </div>
      </div>

      <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-[11px] leading-relaxed">
        <strong>Tanpa Cron Manual?</strong> Tidak masalah! Sistem secara otomatis menjalankan pemeliharaan saat administrator mengakses halaman web jika interval waktu telah tiba.
      </div>
    </div>

    <div class="pt-2 flex justify-end">
      <button type="button" onclick="closeCronGuideModal()" class="px-5 py-2.5 rounded-xl bg-[#6558f5] text-white font-extrabold text-xs shadow-md transition">
        Tutup Panduan
      </button>
    </div>
  </div>
</div>

<!-- MODAL CONFIRM DELETE -->
<div id="deleteBackupModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDeleteBackupModal()"></div>

  <div class="relative w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl font-bold">
        <i class="ph-bold ph-trash"></i>
      </div>
      <div>
        <h3 class="font-black text-base text-slate-900 dark:text-white">Hapus Berkas Cadangan</h3>
        <p class="text-[11px] text-slate-400">Tindakan ini tidak dapat dibatalkan</p>
      </div>
    </div>

    <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/40">
      <p class="text-xs text-rose-900 dark:text-rose-200 leading-relaxed">
        Apakah Anda yakin ingin menghapus berkas cadangan <strong id="deleteTargetLabel" class="font-mono">nama_file.zip</strong> secara permanen dari server?
      </p>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="filename" id="deleteTargetFilename" value="" />

      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeDeleteBackupModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 active:scale-95 cursor-pointer">
          <i class="ph-bold ph-trash"></i>
          <span>Ya, Hapus Cadangan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  function openSendEmailModal(filename, type) {
    document.getElementById('modalEmailTargetFilename').value = filename;
    document.getElementById('modalEmailTargetType').value = type;
    document.getElementById('modalEmailFilename').innerText = filename;
    document.getElementById('sendEmailModal').classList.remove('hidden');
  }

  function closeSendEmailModal() {
    document.getElementById('sendEmailModal').classList.add('hidden');
  }

  function openSendDiscordModal(filename, type) {
    document.getElementById('modalDiscordTargetFilename').value = filename;
    document.getElementById('modalDiscordTargetType').value = type;
    document.getElementById('modalDiscordFilename').innerText = filename;
    document.getElementById('sendDiscordModal').classList.remove('hidden');
  }

  function closeSendDiscordModal() {
    document.getElementById('sendDiscordModal').classList.add('hidden');
  }

  function openDeleteBackupModal(filename) {
    document.getElementById('deleteTargetFilename').value = filename;
    document.getElementById('deleteTargetLabel').innerText = filename;
    document.getElementById('deleteBackupModal').classList.remove('hidden');
  }

  function closeDeleteBackupModal() {
    document.getElementById('deleteBackupModal').classList.add('hidden');
  }

  function openAutoOptimizeModal() {
    document.getElementById('autoOptimizeModal').classList.remove('hidden');
  }

  function closeAutoOptimizeModal() {
    document.getElementById('autoOptimizeModal').classList.add('hidden');
  }

  function openCronGuideModal() {
    document.getElementById('cronGuideModal').classList.remove('hidden');
  }

  function closeCronGuideModal() {
    document.getElementById('cronGuideModal').classList.add('hidden');
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
