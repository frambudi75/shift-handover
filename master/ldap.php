<?php
// master/ldap.php - Integrasi Autentikasi & Sinkronisasi User LDAP / Active Directory (Superadmin Only)
$current_page = 'master_ldap';
$page_title = 'Integrasi LDAP / Active Directory';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ldap_helper.php';

require_superadmin();

$ldapExtLoaded = extension_loaded('ldap');

// Helper upsert setting
function save_ldap_setting(PDO $pdo, string $key, string $val): void {
    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
    $stmt->execute(['k' => $key, 'v' => $val, 'v2' => $val]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    // Simpan konfigurasi terlebih dahulu untuk aksi save / test / sync
    $enabled     = isset($_POST['ldap_enabled']) ? '1' : '0';
    $type        = in_array($_POST['ldap_type'] ?? 'ad', ['ad', 'openldap', 'custom'], true) ? $_POST['ldap_type'] : 'ad';
    $host        = trim($_POST['ldap_host'] ?? '');
    $port        = (int)($_POST['ldap_port'] ?? 389) ?: 389;
    $useTls      = isset($_POST['ldap_use_tls']) ? '1' : '0';
    $baseDn      = trim($_POST['ldap_base_dn'] ?? '');
    $bindDn      = trim($_POST['ldap_bind_dn'] ?? '');
    $bindPassNew = $_POST['ldap_bind_pass'] ?? '';
    $userFilter  = trim($_POST['ldap_user_filter'] ?? '(sAMAccountName=%s)');
    $syncFilter  = trim($_POST['ldap_sync_filter'] ?? '(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))');
    $attrU       = trim($_POST['ldap_attr_username'] ?? 'samaccountname');
    $attrN       = trim($_POST['ldap_attr_name'] ?? 'displayname');
    $attrE       = trim($_POST['ldap_attr_email'] ?? 'mail');
    $attrT       = trim($_POST['ldap_attr_title'] ?? 'title');
    $defRole     = in_array($_POST['ldap_default_role'] ?? 'user', ['user', 'admin'], true) ? $_POST['ldap_default_role'] : 'user';

    save_ldap_setting($pdo, 'ldap_enabled', $enabled);
    save_ldap_setting($pdo, 'ldap_type', $type);
    save_ldap_setting($pdo, 'ldap_host', $host);
    save_ldap_setting($pdo, 'ldap_port', (string)$port);
    save_ldap_setting($pdo, 'ldap_use_tls', $useTls);
    save_ldap_setting($pdo, 'ldap_base_dn', $baseDn);
    save_ldap_setting($pdo, 'ldap_bind_dn', $bindDn);
    if ($bindPassNew !== '') {
        save_ldap_setting($pdo, 'ldap_bind_pass', $bindPassNew);
    }
    save_ldap_setting($pdo, 'ldap_user_filter', $userFilter);
    save_ldap_setting($pdo, 'ldap_sync_filter', $syncFilter);
    save_ldap_setting($pdo, 'ldap_attr_username', $attrU);
    save_ldap_setting($pdo, 'ldap_attr_name', $attrN);
    save_ldap_setting($pdo, 'ldap_attr_email', $attrE);
    save_ldap_setting($pdo, 'ldap_attr_title', $attrT);
    save_ldap_setting($pdo, 'ldap_default_role', $defRole);

    if (function_exists('cache_delete')) {
        cache_delete('app_settings_all');
    }
    $_app_settings_cache = null;

    $cfg = get_ldap_config();
    require_once __DIR__ . '/../includes/audit_helper.php';

    if ($action === 'test') {
        $testRes = ldap_test_connection($cfg);
        flash_set($testRes['success'] ? 'success' : 'error', $testRes['message']);
    } elseif ($action === 'sync') {
        $syncRes = ldap_sync_all_users($pdo, $cfg);
        log_activity($pdo, 'LDAP_SYNC', $syncRes['message']);
        flash_set($syncRes['success'] ? 'success' : 'error', $syncRes['message']);
    } else {
        log_activity($pdo, 'LDAP_SETTINGS_UPDATE', "Memperbarui konfigurasi LDAP (Status: " . ($enabled === '1' ? 'Aktif' : 'Nonaktif') . ").");
        flash_set('success', 'Konfigurasi integrasi LDAP berhasil disimpan!');
    }

    header('Location: ' . BASE_URL . 'master/ldap');
    exit;
}

$cfg = get_ldap_config();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-shield-check"></i>
        <span>Khusus Superadmin</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-tree-structure text-[#6558f5]"></i>
        <span>Integrasi Server LDAP / Active Directory</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Hubungkan login &amp; sinkronisasi daftar pengguna dengan server direktori terpusat (AD / OpenLDAP / FreeIPA)
      </p>
    </div>

    <div class="px-4 py-2.5 rounded-2xl border text-xs font-extrabold flex items-center gap-2 self-start sm:self-auto <?= $ldapExtLoaded ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' : 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300' ?>">
      <span class="w-2.5 h-2.5 rounded-full <?= $ldapExtLoaded ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse' ?>"></span>
      <span>PHP ext-ldap: <?= $ldapExtLoaded ? 'Terpasang &amp; Siap' : 'Belum Terpasang' ?></span>
    </div>
  </div>

  <?php if (!$ldapExtLoaded): ?>
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs flex items-start gap-3">
      <i class="ph-bold ph-warning text-lg shrink-0 mt-0.5"></i>
      <div>
        <strong>Ekstensi PHP <code>ext-ldap</code> belum aktif di lingkungan ini.</strong>
        <p class="mt-0.5 opacity-90">Konfigurasi tetap dapat disimpan di database. Untuk mengaktifkan koneksi LDAP langsung, pastikan container Docker sudah di-build ulang dengan ekstensi <code>ldap</code> (atau aktifkan <code>extension=ldap</code> di <code>php.ini</code> XAMPP).</p>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST" action="" class="space-y-6">

    <!-- Status & Preset -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-8 space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Status &amp; Mode Autentikasi</h3>
          <p class="text-xs text-slate-400 mt-0.5">Menggunakan mode Hybrid: akun lokal (seperti Superadmin) tetap bisa login jika server LDAP tidak terjangkau.</p>
        </div>
        <label class="inline-flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200 dark:border-slate-700 cursor-pointer select-none">
          <input type="checkbox" name="ldap_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-[#6558f5]" />
          <span class="text-xs font-extrabold text-[#181b2c] dark:text-white">Aktifkan Login LDAP</span>
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tipe Server (Preset)</label>
          <select name="ldap_type" id="ldap_type" onchange="applyLdapPreset(this.value)"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">
            <option value="ad" <?= $cfg['type'] === 'ad' ? 'selected' : '' ?>>Active Directory (Windows Server)</option>
            <option value="openldap" <?= $cfg['type'] === 'openldap' ? 'selected' : '' ?>>OpenLDAP / FreeIPA (Linux)</option>
            <option value="custom" <?= $cfg['type'] === 'custom' ? 'selected' : '' ?>>Custom LDAP</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Host / IP Server LDAP</label>
          <input type="text" name="ldap_host" value="<?= sanitize($cfg['host']) ?>" placeholder="ldap://192.168.1.10 atau ldaps://ad.kantor.local"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Port</label>
            <input type="number" name="ldap_port" value="<?= (int)$cfg['port'] ?>" placeholder="389"
                   class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
          </div>
          <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300">
              <input type="checkbox" name="ldap_use_tls" value="1" <?= $cfg['use_tls'] ? 'checked' : '' ?> class="rounded text-[#6558f5]" />
              <span>StartTLS</span>
            </label>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Base DN</label>
          <input type="text" name="ldap_base_dn" value="<?= sanitize($cfg['base_dn']) ?>" placeholder="dc=perusahaan,dc=local"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Bind DN (Service Account)</label>
          <input type="text" name="ldap_bind_dn" value="<?= sanitize($cfg['bind_dn']) ?>" placeholder="cn=svc_ldap,dc=perusahaan,dc=local"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Bind Password</label>
          <input type="password" name="ldap_bind_pass" placeholder="<?= $cfg['bind_pass'] !== '' ? '•••••••• (kosongkan jika tetap)' : 'Masukkan password Bind DN' ?>"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
        </div>
      </div>
    </div>

    <!-- Filter & Attribute Mapping -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 sm:p-8 space-y-5">
      <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
        Filter Pencarian &amp; Pemetaan Atribut User
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">User Login Filter (<code>%s</code> = username)</label>
          <input type="text" name="ldap_user_filter" id="ldap_user_filter" value="<?= sanitize($cfg['user_filter']) ?>"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Filter Impor / Sync Massal</label>
          <input type="text" name="ldap_sync_filter" id="ldap_sync_filter" value="<?= sanitize($cfg['sync_filter']) ?>"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Attr Username</label>
          <input type="text" name="ldap_attr_username" id="ldap_attr_username" value="<?= sanitize($cfg['attr_username']) ?>"
                 class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Attr Nama Lengkap</label>
          <input type="text" name="ldap_attr_name" id="ldap_attr_name" value="<?= sanitize($cfg['attr_name']) ?>"
                 class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Attr Email</label>
          <input type="text" name="ldap_attr_email" id="ldap_attr_email" value="<?= sanitize($cfg['attr_email']) ?>"
                 class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Attr Jabatan</label>
          <input type="text" name="ldap_attr_title" id="ldap_attr_title" value="<?= sanitize($cfg['attr_title']) ?>"
                 class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Role Default</label>
          <select name="ldap_default_role" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">
            <option value="user" <?= $cfg['default_role'] === 'user' ? 'selected' : '' ?>>User (Operator)</option>
            <option value="admin" <?= $cfg['default_role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
      <button type="submit" name="action" value="test"
              class="px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-plugs-connected text-base"></i>
        <span>Simpan &amp; Test Koneksi</span>
      </button>
      <button type="submit" name="action" value="sync"
              onclick="return confirm('Impor dan sinkronkan seluruh user dari server LDAP ke database lokal?')"
              class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-arrows-clockwise text-base"></i>
        <span>Impor / Sync User LDAP</span>
      </button>
      <button type="submit" name="action" value="save"
              class="px-7 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-floppy-disk text-base"></i>
        <span>Simpan Konfigurasi</span>
      </button>
    </div>

  </form>

</div>

<script>
  function applyLdapPreset(type) {
    if (type === 'ad') {
      document.getElementById('ldap_user_filter').value   = '(sAMAccountName=%s)';
      document.getElementById('ldap_sync_filter').value   = '(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))';
      document.getElementById('ldap_attr_username').value = 'samaccountname';
      document.getElementById('ldap_attr_name').value     = 'displayname';
      document.getElementById('ldap_attr_email').value    = 'mail';
      document.getElementById('ldap_attr_title').value    = 'title';
    } else if (type === 'openldap') {
      document.getElementById('ldap_user_filter').value   = '(uid=%s)';
      document.getElementById('ldap_sync_filter').value   = '(&(objectClass=inetOrgPerson)(uid=*))';
      document.getElementById('ldap_attr_username').value = 'uid';
      document.getElementById('ldap_attr_name').value     = 'cn';
      document.getElementById('ldap_attr_email').value    = 'mail';
      document.getElementById('ldap_attr_title').value    = 'title';
    }
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
