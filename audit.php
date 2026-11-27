<?php
// audit.php - Audit Trail & Log Aktivitas Sistem (Superadmin Only)
$current_page = 'audit';
$page_title = 'Audit Trail & Log Aktivitas';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_helper.php';

require_superadmin();
ensure_audit_table($pdo);

$filterAction = trim($_GET['action'] ?? '');
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$where  = ["1=1"];
$params = [];

if ($filterAction !== '') {
    $where[] = "action = :act";
    $params['act'] = $filterAction;
}
if ($search !== '') {
    $where[] = "(username LIKE :s1 OR description LIKE :s2 OR ip_address LIKE :s3)";
    $like = "%{$search}%";
    $params['s1'] = $like;
    $params['s2'] = $like;
    $params['s3'] = $like;
}

$whereSql = implode(' AND ', $where);

// Export filtered audit logs to CSV (Excel-friendly UTF-8 BOM)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStmt = $pdo->prepare("SELECT created_at, username, action, description, ip_address FROM activity_logs WHERE {$whereSql} ORDER BY id DESC LIMIT 5000");
    $expStmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_trail_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Waktu (WIB)', 'Username', 'Aksi', 'Deskripsi Aktivitas', 'Alamat IP']);
    while ($r = $expStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            date('d/m/Y H:i:s', strtotime($r['created_at'])),
            $r['username'],
            $r['action'],
            $r['description'],
            $r['ip_address'] ?: '-',
        ]);
    }
    fclose($out);
    exit;
}

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE {$whereSql}");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY id DESC LIMIT {$offset}, {$perPage}");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionsList = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
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
        <i class="ph-fill ph-fingerprint text-[#6558f5]"></i>
        <span>Audit Trail &amp; Log Aktivitas</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Rekam jejak login, pembuatan/validasi laporan serah terima, dan perubahan konfigurasi sistem
      </p>
    </div>

    <div class="px-4 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm text-xs font-extrabold flex items-center gap-2 self-start sm:self-auto">
      <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5]"></span>
      <span class="text-slate-500 dark:text-slate-400">Total Log:</span>
      <span class="text-[#6558f5] dark:text-indigo-400"><?= number_format($totalRows) ?></span>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="p-5 rounded-[26px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
      <div class="sm:col-span-5">
        <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Cari Username / Deskripsi / IP</label>
        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Kata kunci..."
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none" />
      </div>
      <div class="sm:col-span-4">
        <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Jenis Aktivitas</label>
        <select name="action" class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold">
          <option value="">Semua Aktivitas</option>
          <?php foreach ($actionsList as $act): ?>
            <option value="<?= sanitize($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= sanitize($act) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-3 flex gap-2">
        <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-sm transition cursor-pointer">
          Filter
        </button>
        <a href="?search=<?= urlencode($search) ?>&action=<?= urlencode($filterAction) ?>&export=csv"
           class="py-2.5 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs transition flex items-center gap-1 shadow-sm" title="Unduh CSV Audit Trail">
          <i class="ph-bold ph-download-simple"></i>
          <span>CSV</span>
        </a>
        <a href="<?= BASE_URL ?>audit" class="py-2.5 px-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs transition flex items-center">
          Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Table & Mobile Cards -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden">
    <!-- Mobile Card View (< 640px) -->
    <div class="sm:hidden divide-y divide-slate-100 dark:divide-slate-800/80">
      <?php if (empty($logs)): ?>
        <div class="py-10 text-center text-slate-400 text-xs">Belum ada catatan aktivitas yang cocok.</div>
      <?php else: ?>
        <?php foreach ($logs as $row): ?>
          <?php
            $badgeCls = str_contains($row['action'], 'FAIL') || str_contains($row['action'], 'DELETE')
                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                : (str_contains($row['action'], 'LOGIN')
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                    : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300');
          ?>
          <div class="p-4 space-y-1.5 text-xs">
            <div class="flex items-center justify-between gap-2">
              <span class="font-extrabold text-[#181b2c] dark:text-white">@<?= sanitize($row['username']) ?></span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $badgeCls ?>"><?= sanitize($row['action']) ?></span>
            </div>
            <p class="text-slate-700 dark:text-slate-200 leading-relaxed"><?= sanitize($row['description']) ?></p>
            <div class="flex items-center justify-between text-[10.5px] font-mono text-slate-400 pt-0.5">
              <span><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?> WIB</span>
              <span><?= sanitize($row['ip_address'] ?: '-') ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="hidden sm:block overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3.5 px-5">Waktu (WIB)</th>
            <th class="py-3.5 px-4">Username</th>
            <th class="py-3.5 px-4">Aksi</th>
            <th class="py-3.5 px-5">Deskripsi Aktivitas</th>
            <th class="py-3.5 px-4">Alamat IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" class="py-10 text-center text-slate-400">Belum ada catatan aktivitas yang cocok.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $row): ?>
              <?php
                $badgeCls = str_contains($row['action'], 'FAIL') || str_contains($row['action'], 'DELETE')
                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                    : (str_contains($row['action'], 'LOGIN')
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300');
              ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3 px-5 whitespace-nowrap font-mono text-[11px] text-slate-500 dark:text-slate-400">
                  <?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?>
                </td>
                <td class="py-3 px-4 whitespace-nowrap font-extrabold text-[#181b2c] dark:text-white">
                  @<?= sanitize($row['username']) ?>
                </td>
                <td class="py-3 px-4 whitespace-nowrap">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $badgeCls ?>">
                    <?= sanitize($row['action']) ?>
                  </span>
                </td>
                <td class="py-3 px-5 text-slate-700 dark:text-slate-200">
                  <?= sanitize($row['description']) ?>
                </td>
                <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-slate-400">
                  <?= sanitize($row['ip_address'] ?: '-') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400 font-semibold">Halaman <?= $page ?> dari <?= $totalPages ?></span>
        <div class="flex gap-1.5">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&action=<?= urlencode($filterAction) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold">Sebelumnya</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&action=<?= urlencode($filterAction) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 rounded-xl bg-[#6558f5] text-white font-bold">Berikutnya</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
