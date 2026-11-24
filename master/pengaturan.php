<?php
// master/pengaturan.php - Pengaturan Aplikasi, Logo, Kompresi Foto, SMTP Email, dan WhatsApp Gateway
$current_page = 'master_pengaturan';
$page_title = 'Pengaturan Aplikasi, SMTP & WhatsApp';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$user = current_user();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $appName = trim($_POST['app_name'] ?? '');
        $appFullName = trim($_POST['app_full_name'] ?? '');
        $compressEnabled = isset($_POST['compress_enabled']) ? '1' : '0';
        $compressQuality = (int)($_POST['compress_quality'] ?? 80);
        $compressMaxWidth = (int)($_POST['compress_max_width'] ?? 1600);

        if (!empty($appName)) {
            set_setting('app_name', $appName);
        }
        if (!empty($appFullName)) {
            set_setting('app_full_name', $appFullName);
        }
        $appUrl = trim($_POST['app_url'] ?? '');
        set_setting('app_url', $appUrl);

        $rawDocPrefix = strtoupper(trim($_POST['doc_prefix'] ?? 'OMIT'));
        $docPrefix    = preg_replace('/[^A-Z0-9_-]/', '', $rawDocPrefix) ?: 'OMIT';
        $docResetMode = in_array($_POST['doc_reset_mode'] ?? 'daily', ['daily', 'monthly'], true) ? $_POST['doc_reset_mode'] : 'daily';
        set_setting('doc_prefix', $docPrefix);
        set_setting('doc_reset_mode', $docResetMode);

        require_once __DIR__ . '/../includes/audit_helper.php';
        log_activity($pdo, 'SETTINGS_UPDATE', "Memperbarui pengaturan sistem & identitas aplikasi ({$appName}).");

        set_setting('compress_enabled', $compressEnabled);
        set_setting('compress_quality', (string)$compressQuality);
        set_setting('compress_max_width', (string)$compressMaxWidth);

        // SMTP Email Settings
        $smtpEnabled   = isset($_POST['smtp_enabled']) ? '1' : '0';
        $smtpHost      = trim($_POST['smtp_host'] ?? '');
        $smtpPort      = trim($_POST['smtp_port'] ?? '587');
        $smtpUser      = trim($_POST['smtp_user'] ?? '');
        $smtpPass      = trim($_POST['smtp_pass'] ?? '');
        $smtpCrypto    = trim($_POST['smtp_crypto'] ?? 'tls');
        $smtpFromName  = trim($_POST['smtp_from_name'] ?? '');
        $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');

        set_setting('smtp_enabled', $smtpEnabled);
        set_setting('smtp_host', $smtpHost);
        set_setting('smtp_port', $smtpPort);
        set_setting('smtp_user', $smtpUser);
        if (!empty($smtpPass)) {
            set_setting('smtp_pass', $smtpPass);
        }
        set_setting('smtp_crypto', $smtpCrypto);
        set_setting('smtp_from_name', $smtpFromName);
        set_setting('smtp_from_email', $smtpFromEmail);

        // WhatsApp Notification Settings
        $waEnabled  = isset($_POST['wa_enabled']) ? '1' : '0';
        $waProvider = trim($_POST['wa_provider'] ?? 'direct');
        $waApiToken = trim($_POST['wa_api_token'] ?? '');
        $waApiUrl   = trim($_POST['wa_api_url'] ?? '');

        set_setting('wa_enabled', $waEnabled);
        set_setting('wa_provider', $waProvider);
        set_setting('wa_api_token', $waApiToken);
        set_setting('wa_api_url', $waApiUrl);

        // Discord Webhook Settings
        $discordWebhook = trim($_POST['discord_webhook_url'] ?? '');
        set_setting('discord_webhook_url', $discordWebhook);

        // Handle Logo Upload if provided
        if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['app_logo']['tmp_name']);

            if (in_array($mime, $allowedTypes)) {
                $ext = strtolower(pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION));
                $logoFileName = 'logo_' . time() . '.' . $ext;
                $destination = UPLOAD_DIR . $logoFileName;

                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0777, true);
                }

                // Preserve 100% original alpha transparency & crisp quality for branding logo
                move_uploaded_file($_FILES['app_logo']['tmp_name'], $destination);

                $oldLogo = get_setting('app_logo', '');
                if (!empty($oldLogo) && file_exists(UPLOAD_DIR . $oldLogo)) {
                    @unlink(UPLOAD_DIR . $oldLogo);
                }

                set_setting('app_logo', $logoFileName);
            } else {
                flash_set('warning', 'Format logo harus berupa PNG, JPG, WEBP, atau SVG.');
            }
        }

        flash_set('success', 'Semua pengaturan aplikasi, SMTP email, dan WhatsApp berhasil disimpan!');
        header('Location: ' . BASE_URL . 'master/pengaturan');
        exit;
    } elseif ($action === 'delete_logo') {
        $oldLogo = get_setting('app_logo', '');
        if (!empty($oldLogo) && file_exists(UPLOAD_DIR . $oldLogo)) {
            @unlink(UPLOAD_DIR . $oldLogo);
        }
        set_setting('app_logo', '');
        flash_set('success', 'Logo kustom dihapus. Menggunakan ikon logo default.');
        header('Location: ' . BASE_URL . 'master/pengaturan');
        exit;
    } elseif ($action === 'test_smtp') {
        require_once __DIR__ . '/../includes/notifications.php';
        $testEmail = trim($_POST['test_email'] ?? ($user['email'] ?? ''));
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Masukkan alamat email tujuan uji coba yang valid!');
        } else {
            $subject = "[UJI COBA SMTP] " . get_app_name() . " - Server Email Berfungsi";
            $htmlBody = "
                <div style='font-family:sans-serif;max-width:550px;margin:0 auto;padding:24px;background:#f7f7fb;border-radius:18px;'>
                  <div style='background:#6558f5;color:#fff;padding:16px 20px;border-radius:12px;text-align:center;'>
                    <h2 style='margin:0;'>Koneksi SMTP Berhasil!</h2>
                  </div>
                  <div style='padding:20px;background:#fff;margin-top:12px;border-radius:12px;font-size:13px;color:#334155;line-height:1.6;'>
                    <p>Halo <strong>" . sanitize($user['nama_lengkap']) . "</strong>,</p>
                    <p>Pesan ini mengonfirmasi bahwa server SMTP Anda pada sistem <strong>" . get_app_name() . "</strong> telah aktif dan berhasil mengirimkan email secara normal.</p>
                    <p>Notifikasi email untuk persetujuan (approval) serah terima shift kini siap digunakan.</p>
                  </div>
                </div>
            ";
            $res = send_smtp_email($testEmail, $user['nama_lengkap'], $subject, $htmlBody);
            if ($res['success']) {
                flash_set('success', "Sukses! Email uji coba berhasil dikirim ke {$testEmail}.");
            } else {
                flash_set('error', "Gagal mengirim email SMTP: " . $res['message']);
            }
        }
        header('Location: ' . BASE_URL . 'master/pengaturan');
        exit;
    }
}

// Current Values
$currentAppName     = get_setting('app_name', 'OMIT Shift Handover');
$currentAppFullName = get_setting('app_full_name', 'Sistem Laporan Serah Terima Shift Operasional');
$currentAppUrl      = get_setting('app_url', '');
$currentDocPrefix   = get_setting('doc_prefix', 'OMIT');
$currentDocReset    = get_setting('doc_reset_mode', 'daily');
$currentLogo        = get_app_logo_url();
$compressEnabled    = get_setting('compress_enabled', '1') === '1';
$compressQuality    = (int)get_setting('compress_quality', '80');
$compressMaxWidth   = (int)get_setting('compress_max_width', '1600');

// SMTP Settings
$smtpEnabled   = get_setting('smtp_enabled', '0') === '1';
$smtpHost      = get_setting('smtp_host', 'smtp.gmail.com');
$smtpPort      = get_setting('smtp_port', '587');
$smtpUser      = get_setting('smtp_user', '');
$smtpPass      = get_setting('smtp_pass', '');
$smtpCrypto    = get_setting('smtp_crypto', 'tls');
$smtpFromName  = get_setting('smtp_from_name', get_app_name());
$smtpFromEmail = get_setting('smtp_from_email', 'no-reply@operasional.local');

// WhatsApp Settings
$waEnabled     = get_setting('wa_enabled', '1') === '1';
$waProvider    = get_setting('wa_provider', 'direct');
$waApiToken    = get_setting('wa_api_token', '');
$waApiUrl      = get_setting('wa_api_url', 'https://api.fonnte.com/send');

// Discord Settings
$discordWebhook = get_setting('discord_webhook_url', '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1 pb-10">

  <!-- HEADER -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-sliders"></i>
        <span>Preferensi &amp; Integrasi Notifikasi</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-gear text-[#6558f5]"></i>
        <span>Pengaturan Aplikasi, SMTP &amp; WhatsApp</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Kustomisasi nama sistem, logo instansi, server email SMTP, notifikasi WhatsApp, serta optimasi gambar
      </p>
    </div>

    <!-- Quick Master Links -->
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>master/backup" class="px-3.5 py-2 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-900 shadow-sm hover:shadow text-xs font-extrabold text-[#6558f5] flex items-center gap-2 hover:bg-[#e0e0fb] transition">
        <i class="ph-fill ph-database text-base"></i>
        <span>Pusat Backup Sistem</span>
      </a>
      <a href="<?= BASE_URL ?>master/kondisi" class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow text-xs font-extrabold text-slate-700 dark:text-slate-200 flex items-center gap-2 hover:bg-[#efeffb] dark:hover:bg-slate-800 transition">
        <i class="ph-fill ph-activity text-base text-slate-400"></i>
        <span>Kondisi Operasional</span>
      </a>
      <a href="<?= BASE_URL ?>master/shift" class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow text-xs font-extrabold text-slate-700 dark:text-slate-200 flex items-center gap-2 hover:bg-[#efeffb] dark:hover:bg-slate-800 transition">
        <i class="ph-fill ph-clock text-base text-slate-400"></i>
        <span>Manajemen Shift</span>
      </a>
      <a href="<?= BASE_URL ?>master/lokasi" class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow text-xs font-extrabold text-slate-700 dark:text-slate-200 flex items-center gap-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        <i class="ph-fill ph-map-pin text-base text-slate-400"></i>
        <span>Master Lokasi</span>
      </a>
    </div>
  </div>

  <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="action" value="save_settings" />

    <!-- ROW 1: IDENTITAS & KOMPRESI GAMBAR -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

      <!-- LEFT COLUMN: IDENTITAS & LOGO (7 COLS) -->
      <div class="xl:col-span-7 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 space-y-6 widget-card">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-identification-badge"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Identitas &amp; Logo Aplikasi</h3>
            <p class="text-xs text-slate-400">Identitas resmi yang tampil di sidebar, topbar, halaman login, dan cetak PDF</p>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Nama Aplikasi Singkat -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Nama Singkat Aplikasi *
            </label>
            <input type="text" name="app_name" value="<?= sanitize($currentAppName) ?>" required
                   placeholder="Contoh: OMIT Shift Handover"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
            <p class="text-[11px] text-slate-400 mt-1">Ditampilkan pada tab browser, top navigation bar, dan drawer navigasi.</p>
          </div>

          <!-- Nama Lengkap / Instansi -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Nama Lengkap / Instansi Operasional *
            </label>
            <input type="text" name="app_full_name" value="<?= sanitize($currentAppFullName) ?>" required
                   placeholder="Contoh: Sistem Laporan Serah Terima Shift Operasional"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
            <p class="text-[11px] text-slate-400 mt-1">Ditampilkan di header formulir cetak PDF standar OMIT dan login.</p>
          </div>

          <!-- URL Domain Publik / Akses Jaringan -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center justify-between">
              <span>URL Domain Publik / Akses Jaringan (Opsional)</span>
              <span class="text-[10px] text-slate-400 font-normal">Contoh: http://192.168.1.100:2032 atau https://handover.perusahaan.com</span>
            </label>
            <input type="text" name="app_url" value="<?= sanitize($currentAppUrl) ?>"
                   placeholder="Kosongkan untuk deteksi otomatis dari alamat browser saat ini"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
            <p class="text-[11px] text-slate-400 mt-1">
              Digunakan pada tautan dokumen yang dikirimkan via <strong>WhatsApp dan Email</strong> agar rekan kerja yang menerima di smartphone dapat langsung membuka dokumen. Jika dikosongkan, sistem otomatis mendeteksi URL aktif dari browser.
            </p>
          </div>

          <!-- Penomoran Dokumen Otomatis -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div>
              <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
                Prefix Nomor Dokumen
              </label>
              <input type="text" name="doc_prefix" id="inputDocPrefix" maxlength="15"
                     value="<?= sanitize($currentDocPrefix) ?>" placeholder="OMIT"
                     oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ''); updateDocPreview();"
                     class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 uppercase tracking-wider focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
              <p class="text-[11px] text-slate-400 mt-1">Huruf kapital / kode divisi (Contoh: <code>OMIT</code>, <code>AVI</code>, <code>PLANT1</code>).</p>
            </div>

            <div>
              <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
                Siklus Reset Nomor Urut
              </label>
              <select name="doc_reset_mode" id="inputDocReset" onchange="updateDocPreview()"
                      class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition cursor-pointer">
                <option value="daily" <?= $currentDocReset === 'daily' ? 'selected' : '' ?>>Harian (YYYYMMDD-001)</option>
                <option value="monthly" <?= $currentDocReset === 'monthly' ? 'selected' : '' ?>>Bulanan (YYYYMM-001)</option>
              </select>
              <p class="text-[11px] text-slate-400 mt-1">
                Pratinjau format: <code id="docFormatPreview" class="px-1.5 py-0.5 rounded bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 font-bold"><?= sanitize($currentDocPrefix) ?>-<?= $currentDocReset === 'monthly' ? date('Ym') : date('Ymd') ?>-001</code>
              </p>
            </div>
          </div>
        </div>

        <!-- Upload Logo Aplikasi -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-3">
            Logo Resmi Aplikasi
          </label>

          <div class="flex flex-col sm:flex-row items-center gap-5">
            <!-- Logo Preview Box -->
            <div class="w-28 h-28 rounded-2xl border-2 border-dashed border-[#6558f5]/30 bg-[#f7f7fb] dark:bg-[#151828] flex items-center justify-center p-3 overflow-hidden shrink-0 shadow-inner">
              <?php if ($currentLogo): ?>
                <img src="<?= $currentLogo ?>" alt="Logo Aplikasi" class="max-w-full max-h-full object-contain" id="previewLogoImg" />
              <?php else: ?>
                <div class="text-center text-slate-400" id="previewLogoPlaceholder">
                  <i class="ph-fill ph-clipboard-text text-3xl text-[#6558f5]"></i>
                  <span class="text-[10px] block mt-1 font-bold">Logo Default</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Upload Controls -->
            <div class="space-y-3 flex-1 text-xs">
              <div>
                <input type="file" name="app_logo" id="inputAppLogo" accept="image/*" class="hidden" onchange="previewAppLogo(this)" />
                <button type="button" onclick="document.getElementById('inputAppLogo').click()"
                        class="px-4 py-2.5 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 hover:bg-[#6558f5] text-[#6558f5] hover:text-white font-extrabold active:scale-95 transition flex items-center gap-2">
                  <i class="ph-bold ph-upload-simple text-sm"></i>
                  <span>Pilih File Logo Baru</span>
                </button>
                <p class="text-[11px] text-slate-400 mt-1.5 leading-relaxed">
                  Format disarankan: PNG transparan atau SVG. Ukuran file otomatis dioptimalkan.
                </p>
              </div>

              <?php if ($currentLogo): ?>
                <div>
                  <button type="submit" form="formDeleteLogo" class="text-rose-600 dark:text-rose-400 hover:underline font-bold flex items-center gap-1 text-[11px]">
                    <i class="ph-bold ph-trash"></i>
                    <span>Hapus logo kustom &amp; pakai ikon default</span>
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: OPTIMASI KOMPRESI GAMBAR (5 COLS) -->
      <div class="xl:col-span-5 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 space-y-6 widget-card">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-[#e6f7f0] dark:bg-emerald-950/60 text-[#1c7a52] dark:text-emerald-300 flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-file-zip"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Kompresi Foto Otomatis</h3>
            <p class="text-xs text-slate-400">Pustaka GD mengompres foto HP hingga ~84% lebih ringan</p>
          </div>
        </div>

        <!-- Toggle Switch Kompresi -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800">
          <div>
            <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">Status Kompresi GD</h4>
            <p class="text-[11px] text-slate-400 mt-0.5">Otomatis perkecil ukuran foto serah terima saat diupload</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="compress_enabled" value="1" <?= $compressEnabled ? 'checked' : '' ?> class="sr-only peer" />
            <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#6558f5]"></div>
          </label>
        </div>

        <div class="space-y-4">
          <!-- Kualitas Kompresi -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Kualitas JPEG Output
            </label>
            <select name="compress_quality" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
              <option value="70" <?= $compressQuality == 70 ? 'selected' : '' ?>>70% — Hemat Kuota Maksimal (~80-120 KB)</option>
              <option value="80" <?= $compressQuality == 80 ? 'selected' : '' ?>>80% — Optimal &amp; Sangat Tajam (Rekomendasi, ~150-200 KB)</option>
              <option value="88" <?= $compressQuality == 88 ? 'selected' : '' ?>>88% — Kualitas Tinggi (~250-400 KB)</option>
              <option value="95" <?= $compressQuality == 95 ? 'selected' : '' ?>>95% — Nyaris Tanpa Kompresi (~700 KB - 1 MB)</option>
            </select>
          </div>

          <!-- Maksimal Dimensi Lebar -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Batas Dimensi Maksimal (Lebar / Tinggi)
            </label>
            <select name="compress_max_width" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
              <option value="1200" <?= $compressMaxWidth == 1200 ? 'selected' : '' ?>>1200 px — Standar Web Cepat</option>
              <option value="1600" <?= $compressMaxWidth == 1600 ? 'selected' : '' ?>>1600 px — Rekomendasi (Sempurna untuk Cetak Dokumen A4)</option>
              <option value="1920" <?= $compressMaxWidth == 1920 ? 'selected' : '' ?>>1920 px — Full HD High Resolution</option>
            </select>
          </div>
        </div>

        <div class="p-3.5 rounded-2xl bg-[#e6f7f0]/60 dark:bg-emerald-950/30 border border-[#68cfa3]/30 text-xs text-slate-700 dark:text-slate-200 space-y-1">
          <div class="font-extrabold flex items-center gap-1.5 text-[#1c7a52] dark:text-emerald-400">
            <i class="ph-bold ph-check-circle"></i>
            <span>Keuntungan Kompresi GD Aktif:</span>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
            • Mempercepat proses unggah foto dari ponsel saat sinyal lapangan lemah.<br>
            • Menghemat ruang penyimpanan server secara signifikan.<br>
            • Dokumen PDF A4 tercetak instan tanpa macet.
          </p>
        </div>
      </div>

    </div>

    <!-- ROW 2: NOTIFIKASI EMAIL SMTP & INTEGRASI WHATSAPP -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

      <!-- LEFT COLUMN: SMTP EMAIL NOTIFICATION (7 COLS) -->
      <div class="xl:col-span-7 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 space-y-6 widget-card">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-envelope-simple"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Server Email SMTP (Notifikasi Approval)</h3>
            <p class="text-xs text-slate-400">Kirim email otomatis ke shift penerima saat ada serah terima baru butuh approval</p>
          </div>
        </div>

        <!-- Toggle Switch SMTP -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800">
          <div>
            <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">Status Notifikasi Email SMTP</h4>
            <p class="text-[11px] text-slate-400 mt-0.5">Aktifkan pengiriman email otomatis saat ada serah terima shift</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="smtp_enabled" value="1" <?= $smtpEnabled ? 'checked' : '' ?> class="sr-only peer" />
            <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#6558f5]"></div>
          </label>
        </div>

        <!-- Panduan Khusus Gmail / Yahoo / Outlook App Password -->
        <div class="p-4 rounded-2xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200/90 dark:border-amber-800/60 text-xs">
          <div class="flex items-center gap-2 font-black text-amber-900 dark:text-amber-300 uppercase tracking-wider text-[11px] mb-1.5">
            <i class="ph-bold ph-shield-check text-base text-amber-600"></i>
            <span>Penting untuk Pengguna Gmail (smtp.gmail.com): Gunakan Sandi Aplikasi (App Password)</span>
          </div>
          <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11.5px]">
            Google menonaktifkan login password biasa untuk SMTP sejak 2022 (Error <code>535 Bad Credentials</code>). Untuk mengirim email via Gmail, Anda <strong>wajib menggunakan Sandi Aplikasi 16 Karakter</strong>:
          </p>
          <ol class="list-decimal list-inside space-y-1 mt-2 text-[11px] text-slate-700 dark:text-slate-300 font-medium">
            <li>Aktifkan <strong>Verifikasi 2 Langkah</strong> di akun Google Anda.</li>
            <li>Buka halaman Sandi Aplikasi Google: <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-[#6558f5] dark:text-indigo-400 font-bold underline">myaccount.google.com/apppasswords</a>.</li>
            <li>Buat sandi baru (beri nama misalnya: <em>Serah Terima OMIT</em>), lalu klik <strong>Buat</strong>.</li>
            <li>Salin <strong>16 karakter</strong> yang muncul (contoh: <code>abcd efgh ijkl mnop</code>), lalu tempelkan ke kolom <strong>SMTP Password</strong> di bawah.</li>
          </ol>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- SMTP Host -->
          <div class="md:col-span-2">
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">SMTP Host / Server</label>
            <input type="text" name="smtp_host" value="<?= sanitize($smtpHost) ?>" placeholder="smtp.gmail.com"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>

          <!-- SMTP Port -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Port &amp; Enkripsi</label>
            <div class="flex gap-2">
              <input type="text" name="smtp_port" value="<?= sanitize($smtpPort) ?>" placeholder="587"
                     class="w-20 px-3 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
              <select name="smtp_crypto" class="flex-1 px-2.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer">
                <option value="tls" <?= $smtpCrypto === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= $smtpCrypto === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="none" <?= $smtpCrypto === 'none' ? 'selected' : '' ?>>None</option>
              </select>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- SMTP Username -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">SMTP Username / Email</label>
            <input type="text" name="smtp_user" value="<?= sanitize($smtpUser) ?>" placeholder="user@gmail.com"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>

          <!-- SMTP Password -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">SMTP Password / App Password</label>
            <input type="password" name="smtp_pass" placeholder="••••••••"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
            <p class="text-[10px] text-slate-400 mt-1">Kosongkan jika tidak ingin mengubah password yang tersimpan.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- From Name -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Pengirim (From Name)</label>
            <input type="text" name="smtp_from_name" value="<?= sanitize($smtpFromName) ?>" placeholder="OMIT Shift Handover"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>

          <!-- From Email -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Email Pengirim (From Email)</label>
            <input type="email" name="smtp_from_email" value="<?= sanitize($smtpFromEmail) ?>" placeholder="no-reply@operasional.id"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>
        </div>

        <!-- UJI COBA SMTP BOX -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="text-xs text-slate-500">
            Ingin menguji apakah pengaturan SMTP sudah berjalan normal?
          </div>
          <button type="button" onclick="openTestSmtpModal()"
                  class="px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 font-extrabold text-xs active:scale-95 transition flex items-center gap-1.5">
            <i class="ph-bold ph-paper-plane-tilt"></i>
            <span>Kirim Uji Coba Email</span>
          </button>
        </div>
      </div>

      <!-- RIGHT COLUMN: NOTIFIKASI WHATSAPP (5 COLS) -->
      <div class="xl:col-span-5 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 space-y-6 widget-card">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-[#e6f7f0] dark:bg-emerald-950/60 text-[#1c7a52] dark:text-emerald-300 flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-whatsapp-logo"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Notifikasi WhatsApp Serah Terima</h3>
            <p class="text-xs text-slate-400">Kirim pemberitahuan shift &amp; tautan approval langsung ke WhatsApp</p>
          </div>
        </div>

        <!-- Toggle Switch WhatsApp -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800">
          <div>
            <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">Status Fitur WhatsApp</h4>
            <p class="text-[11px] text-slate-400 mt-0.5">Tombol 1-klik kirim ke WA &amp; otomatisasi API</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="wa_enabled" value="1" <?= $waEnabled ? 'checked' : '' ?> class="sr-only peer" />
            <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#25D366]"></div>
          </label>
        </div>

        <div class="space-y-4">
          <!-- Provider WhatsApp -->
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Metode Pengiriman WhatsApp
            </label>
            <select name="wa_provider" id="selectWaProvider" onchange="toggleWaProviderInputs()"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
              <option value="direct" <?= $waProvider === 'direct' ? 'selected' : '' ?>>Tautan Langsung wa.me (Gratis, Praktis, Tanpa Gateway)</option>
              <option value="fonnte" <?= $waProvider === 'fonnte' ? 'selected' : '' ?>>Fonnte WhatsApp Gateway (Otomatis API)</option>
              <option value="generic_api" <?= $waProvider === 'generic_api' ? 'selected' : '' ?>>Webhook / Custom REST API</option>
            </select>
          </div>

          <!-- Token API (Conditional) -->
          <div id="waTokenWrap">
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              API Token / Auth Key
            </label>
            <input type="password" name="wa_api_token" value="<?= sanitize($waApiToken) ?>" placeholder="Masukkan token API gateway Anda"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>

          <!-- Endpoint URL (Conditional) -->
          <div id="waUrlWrap">
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
              Endpoint URL API WhatsApp
            </label>
            <input type="text" name="wa_api_url" value="<?= sanitize($waApiUrl) ?>" placeholder="https://api.fonnte.com/send"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>
        </div>

        <div class="p-3.5 rounded-2xl bg-[#e6f7f0]/60 dark:bg-emerald-950/30 border border-[#68cfa3]/30 text-xs text-slate-700 dark:text-slate-200 space-y-1">
          <div class="font-extrabold flex items-center gap-1.5 text-[#1c7a52] dark:text-emerald-400">
            <i class="ph-bold ph-chats-circle"></i>
            <span>Cara Kerja Notifikasi WhatsApp:</span>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
            • <strong>Mode wa.me:</strong> Tombol hijau di detail laporan otomatis membuka WhatsApp Web/HP dengan pesan siap kirim.<br>
            • <strong>Mode API:</strong> Sistem langsung mengirim pesan WhatsApp otomatis di latar belakang ke nomor petugas penerima saat laporan serah terima disimpan.
          </p>
        </div>
      </div>

    </div>

    <!-- ROW 3: DISCORD WEBHOOK INTEGRATION & BACKUP SISTEM -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-7 space-y-6 widget-card">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-[#5865F2]/10 text-[#5865F2] flex items-center justify-center font-bold text-lg shadow-sm">
            <i class="ph-bold ph-discord-logo"></i>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Integrasi Discord Webhook &amp; Arsip Cadangan</h3>
            <p class="text-xs text-slate-400">Kirim notifikasi serah terima dan kirim file backup database / full sistem langsung ke channel Discord</p>
          </div>
        </div>
        <a href="<?= BASE_URL ?>master/backup" class="px-4 py-2 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 hover:bg-[#e0e0fb] text-[#6558f5] dark:text-indigo-300 font-extrabold text-xs flex items-center gap-1.5 transition">
          <i class="ph-fill ph-database"></i>
          <span>Pusat Backup &amp; Auto-Optimise &rarr;</span>
        </a>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
        <div class="md:col-span-8">
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
            URL Webhook Discord Channel
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#5865F2]">
              <i class="ph-bold ph-link text-base"></i>
            </span>
            <input type="url" name="discord_webhook_url" value="<?= sanitize($discordWebhook) ?>"
                   placeholder="https://discord.com/api/webhooks/1234567890/abcdefg..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-mono font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#5865F2]/40 focus:outline-none transition" />
          </div>
          <p class="text-[11px] text-slate-400 mt-1">Dapatkan URL webhook dari Discord: Edit Channel &rarr; Integrations &rarr; Webhooks &rarr; New Webhook &rarr; Copy Webhook URL.</p>
        </div>
        <div class="md:col-span-4 p-3.5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 text-[11px] text-slate-600 dark:text-slate-300 space-y-1">
          <div class="font-extrabold text-[#6558f5] flex items-center gap-1">
            <i class="ph-bold ph-lightning"></i>
            <span>Fitur Tersedia:</span>
          </div>
          <p>&bull; Notifikasi status backup database (.sql / .zip)</p>
          <p>&bull; Pengiriman otomatis arsip full zip aplikasi ke Discord</p>
          <p>&bull; Auto-Optimise MySQL &amp; Rotasi Sampah</p>
        </div>
      </div>
    </div>

    <!-- SUBMIT BAR -->
    <div class="pt-4 flex items-center justify-end gap-3">
      <a href="<?= BASE_URL ?>dashboard" 
         class="px-5 py-3 rounded-2xl bg-[#efeffb] dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-extrabold text-xs active:scale-95 transition">
        Batal
      </a>
      <button type="submit" 
              class="px-7 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md hover:shadow-lg transition flex items-center gap-2">
        <i class="ph-bold ph-floppy-disk text-base"></i>
        <span>Simpan Semua Pengaturan</span>
      </button>
    </div>

  </form>

  <!-- FORM TERPISAH UNTUK HAPUS LOGO -->
  <?php if ($currentLogo): ?>
    <form id="formDeleteLogo" method="POST" action="" onsubmit="return confirm('Hapus logo kustom dan kembali ke default?')">
      <input type="hidden" name="action" value="delete_logo" />
    </form>
  <?php endif; ?>

</div>

<!-- MODAL UJI COBA SMTP -->
<div id="modalTestSmtp" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeTestSmtpModal()"></div>
  <div class="relative w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[28px] shadow-2xl border border-slate-100 dark:border-slate-800 p-6 z-10 space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 flex items-center justify-center font-bold">
          <i class="ph-bold ph-paper-plane-tilt"></i>
        </div>
        <div>
          <h3 class="font-extrabold text-sm text-[#181b2c] dark:text-white">Uji Coba Pengiriman Email</h3>
          <p class="text-[11px] text-slate-400">Kirim email tes menggunakan konfigurasi SMTP tersimpan</p>
        </div>
      </div>
      <button type="button" onclick="closeTestSmtpModal()" class="text-slate-400 hover:text-slate-600">
        <i class="ph-bold ph-x text-base"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="test_smtp" />
      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Alamat Email Tujuan Tes</label>
        <input type="email" name="test_email" required value="<?= sanitize($user['email'] ?? '') ?>" placeholder="email-anda@gmail.com"
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none" />
        <p class="text-[11px] text-slate-400 mt-1">Pastikan Anda telah mengklik "Simpan Semua Pengaturan" sebelum melakukan tes.</p>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeTestSmtpModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-bold shadow-md">Kirim Tes</button>
      </div>
    </form>
  </div>
</div>

<script>
  function updateDocPreview() {
    const prefix = (document.getElementById('inputDocPrefix')?.value || 'OMIT').trim() || 'OMIT';
    const mode   = document.getElementById('inputDocReset')?.value || 'daily';
    const dateStr = mode === 'monthly' ? '<?= date('Ym') ?>' : '<?= date('Ymd') ?>';
    const el = document.getElementById('docFormatPreview');
    if (el) el.textContent = `${prefix}-${dateStr}-001`;
  }

  function previewAppLogo(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        let img = document.getElementById('previewLogoImg');
        const placeholder = document.getElementById('previewLogoPlaceholder');
        if (!img) {
          img = document.createElement('img');
          img.id = 'previewLogoImg';
          img.className = 'max-w-full max-h-full object-contain';
          if (placeholder) placeholder.replaceWith(img);
        }
        img.src = e.target.result;
      }
      reader.readAsDataURL(input.files[0]);
    }
  }

  function toggleWaProviderInputs() {
    const provider = document.getElementById('selectWaProvider').value;
    const tokenWrap = document.getElementById('waTokenWrap');
    const urlWrap = document.getElementById('waUrlWrap');
    if (provider === 'direct') {
      tokenWrap.style.display = 'none';
      urlWrap.style.display = 'none';
    } else {
      tokenWrap.style.display = 'block';
      urlWrap.style.display = 'block';
    }
  }

  function openTestSmtpModal() {
    document.getElementById('modalTestSmtp').classList.remove('hidden');
  }

  function closeTestSmtpModal() {
    document.getElementById('modalTestSmtp').classList.add('hidden');
  }

  document.addEventListener('DOMContentLoaded', toggleWaProviderInputs);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
