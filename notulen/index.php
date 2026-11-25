<?php
// notulen/index.php - Daftar Notulen Meeting & Briefing
$current_page = 'notulen';
$page_title = 'Notulen Meeting & Briefing';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

require_login();
$user = current_user();
ensure_notulen_tables($pdo);

// Filters
$q      = trim($_GET['q'] ?? '');
$jenis  = trim($_GET['jenis'] ?? '');
$status = trim($_GET['status'] ?? '');

$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(n.nomor_notulen LIKE :q1 OR n.judul LIKE :q2 OR n.pimpinan_rapat LIKE :q3 OR n.lokasi LIKE :q4)";
    $like = "%{$q}%";
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($jenis !== '') {
    $where[] = "n.jenis = :jenis";
    $params['jenis'] = $jenis;
}
if ($status !== '') {
    $where[] = "n.status = :status";
    $params['status'] = $status;
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT n.*, u.nama_lengkap AS pembuat_nama,
           (SELECT COUNT(*) FROM notulen_action_items a WHERE a.notulen_id = n.id) AS total_items,
           (SELECT COUNT(*) FROM notulen_action_items a WHERE a.notulen_id = n.id AND a.status = 'Selesai') AS done_items
    FROM notulen_meeting n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE {$whereSql}
    ORDER BY n.tanggal DESC, n.jam_mulai DESC, n.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary stats
$stats = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS total_draft,
      SUM(CASE WHEN status = 'FINAL' THEN 1 ELSE 0 END) AS total_final
    FROM notulen_meeting
")->fetch();
$openActions = (int)$pdo->query("SELECT COUNT(*) FROM notulen_action_items WHERE status != 'Selesai'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-notepad text-[#6558f5]"></i>
        <span>Notulen Meeting &amp; Briefing</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Arsip catatan briefing shift, meeting operasional, keputusan rapat, dan pelacakan tindak lanjut
      </p>
    </div>

    <a href="<?= BASE_URL ?>notulen/create"
       class="px-5 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 transition flex items-center justify-center gap-2 active:scale-95 self-start sm:self-auto">
      <i class="ph-bold ph-plus-circle text-base"></i>
      <span>Catat Notulen Baru</span>
    </a>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Total Notulen</p>
      <p class="text-2xl font-black text-[#181b2c] dark:text-white mt-1"><?= (int)($stats['total'] ?? 0) ?></p>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Final</p>
      <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1"><?= (int)($stats['total_final'] ?? 0) ?></p>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">Draft</p>
      <p class="text-2xl font-black text-slate-600 dark:text-slate-300 mt-1"><?= (int)($stats['total_draft'] ?? 0) ?></p>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">Action Item Aktif</p>
      <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1"><?= $openActions ?></p>
    </div>
  </div>

  <!-- Filter & Search -->
  <div class="p-5 rounded-[26px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature space-y-4">
    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
      <div class="sm:col-span-5">
        <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Cari Nomor / Topik / Pimpinan / Lokasi</label>
        <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Kata kunci..."
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none" />
      </div>
      <div class="sm:col-span-3">
        <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Jenis</label>
        <select name="jenis" class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold">
          <option value="">Semua Jenis</option>
          <?php foreach (['Briefing Shift', 'Meeting Operasional', 'Evaluasi', 'Koordinasi'] as $jOpt): ?>
            <option value="<?= $jOpt ?>" <?= $jenis === $jOpt ? 'selected' : '' ?>><?= $jOpt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold">
          <option value="">Semua</option>
          <option value="FINAL" <?= $status === 'FINAL' ? 'selected' : '' ?>>Final</option>
          <option value="DRAFT" <?= $status === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
        </select>
      </div>
      <div class="sm:col-span-2 flex gap-2">
        <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs transition cursor-pointer">
          Filter
        </button>
        <a href="<?= BASE_URL ?>notulen/index" class="py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center">
          Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs min-w-[780px]">
        <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3.5 px-5">Nomor &amp; Waktu</th>
            <th class="py-3.5 px-4">Topik / Judul &amp; Lokasi</th>
            <th class="py-3.5 px-4">Pimpinan &amp; Peserta</th>
            <th class="py-3.5 px-4 text-center">Action Items</th>
            <th class="py-3.5 px-4">Status</th>
            <th class="py-3.5 px-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400">
                <i class="ph ph-notepad text-4xl block mx-auto mb-2 opacity-40"></i>
                Belum ada catatan notulen meeting atau briefing.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $pIds = json_decode($r['peserta_ids'] ?? '[]', true);
                $pCount = is_array($pIds) ? count($pIds) : 0;
                $totalIt = (int)$r['total_items'];
                $doneIt  = (int)$r['done_items'];
              ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3.5 px-5 whitespace-nowrap">
                  <a href="<?= BASE_URL ?>notulen/detail?id=<?= $r['id'] ?>" class="font-mono font-extrabold text-[#6558f5] hover:underline">
                    <?= sanitize($r['nomor_notulen']) ?>
                  </a>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    <?= format_tanggal($r['tanggal']) ?> • <?= substr($r['jam_mulai'], 0, 5) ?><?= $r['jam_selesai'] ? '-' . substr($r['jam_selesai'], 0, 5) : '' ?> WIB
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[10px] font-extrabold mb-1">
                    <?= sanitize($r['jenis']) ?>
                  </span>
                  <div class="font-extrabold text-[#181b2c] dark:text-white text-xs">
                    <?= sanitize($r['judul']) ?>
                  </div>
                  <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                    <i class="ph-bold ph-map-pin"></i>
                    <span><?= sanitize($r['lokasi']) ?></span>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-700 dark:text-slate-200"><?= sanitize($r['pimpinan_rapat']) ?></div>
                  <div class="text-[11px] text-slate-400 mt-0.5"><?= $pCount ?> peserta internal<?= !empty($r['peserta_eksternal']) ? ' + eksternal' : '' ?></div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <?php if ($totalIt === 0): ?>
                    <span class="text-slate-400 text-[11px]">-</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-lg <?= $doneIt === $totalIt ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' ?> font-extrabold text-[11px]">
                      <?= $doneIt ?>/<?= $totalIt ?> Selesai
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-4">
                  <?= badge_status_notulen($r['status']) ?>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <a href="<?= BASE_URL ?>notulen/detail?id=<?= $r['id'] ?>" title="Lihat Detail"
                       class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 transition">
                      <i class="ph-bold ph-eye text-sm"></i>
                    </a>
                    <a href="<?= BASE_URL ?>notulen/create?edit=<?= $r['id'] ?>" title="Edit / Update Notulen"
                       class="p-2 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-300 hover:bg-amber-500 hover:text-white transition">
                      <i class="ph-bold ph-pencil-simple text-sm"></i>
                    </a>
                    <a href="<?= BASE_URL ?>notulen/print?id=<?= $r['id'] ?>" target="_blank" title="Cetak PDF"
                       class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-[#6558f5] hover:bg-[#6558f5] hover:text-white transition">
                      <i class="ph-bold ph-printer text-sm"></i>
                    </a>
                    <?php if (is_admin() || $r['created_by'] == $user['id']): ?>
                      <a href="<?= BASE_URL ?>notulen/delete?id=<?= $r['id'] ?>"
                         onclick="return confirm('Hapus notulen <?= sanitize($r['nomor_notulen']) ?> secara permanen?')"
                         title="Hapus Notulen"
                         class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-500 hover:text-white transition">
                        <i class="ph-bold ph-trash text-sm"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
