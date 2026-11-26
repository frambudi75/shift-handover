<?php
// rekap/index.php - Rekapitulasi Harian Tugas Antar Shift (Sesuai Sheet Rekap_Harian Excel)
$current_page = 'rekap';
$page_title = 'Rekap Harian Operasional';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Filter date range
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));
$lokasiId  = (int)($_GET['lokasi_id'] ?? 0);

$where = ["l.tanggal BETWEEN :start AND :end", "l.status_validasi != 'DRAFT'"];
$params = ['start' => $startDate, 'end' => $endDate];

if ($lokasiId > 0) {
    $where[] = "l.lokasi_id = :lokasi_id";
    $params['lokasi_id'] = $lokasiId;
}
$whereSql = implode(' AND ', $where);

// Fetch all task rows linked to handover reports
$query = "
    SELECT p.*, l.nomor_laporan, l.tanggal, l.jam_serah_terima,
           s.nama_shift, m.nama_lokasi,
           u1.nama_lengkap AS nama_menyerahkan,
           u2.nama_lengkap AS nama_menerima
    FROM laporan_pekerjaan p
    JOIN laporan_serah_terima l ON p.laporan_id = l.id
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    WHERE {$whereSql}
    ORDER BY l.tanggal DESC, l.jam_serah_terima DESC, p.urutan ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rekapTasks = $stmt->fetchAll();

// Export CSV / Excel compatible download
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap_evaluasi_' . $startDate . '_sd_' . $endDate . '.xls"');
    echo "<table border='1'><tr><th>No</th><th>Tanggal</th><th>No Laporan</th><th>Shift</th><th>Lokasi</th><th>No Tiket</th><th>Pekerjaan / Masalah</th><th>Prioritas</th><th>Status</th><th>Tindak Lanjut</th><th>PIC</th><th>Target</th></tr>";
    foreach ($rekapTasks as $idx => $t) {
        echo "<tr><td>" . ($idx + 1) . "</td><td>" . sanitize($t['tanggal']) . "</td><td>" . sanitize($t['nomor_laporan']) . "</td><td>" . sanitize($t['nama_shift']) . "</td><td>" . sanitize($t['nama_lokasi']) . "</td><td>" . sanitize($t['no_tiket']) . "</td><td>" . sanitize($t['pekerjaan_masalah']) . "</td><td>" . sanitize($t['prioritas']) . "</td><td>" . sanitize($t['status']) . "</td><td>" . sanitize($t['kondisi_tindak_lanjut']) . "</td><td>" . sanitize($t['pic']) . "</td><td>" . sanitize($t['target_selesai']) . "</td></tr>";
    }
    echo "</table>";
    exit;
}

// Analytics & Monthly Evaluation Queries
$stmtRep = $pdo->prepare("
    SELECT l.id, l.tanggal, l.status_validasi, l.kondisi_operasional, s.nama_shift, m.nama_lokasi
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    WHERE {$whereSql}
    ORDER BY l.tanggal ASC
");
$stmtRep->execute($params);
$periodReports = $stmtRep->fetchAll();

$totalPeriodReports   = count($periodReports);
$totalValidated       = 0;
$totalIssues          = 0;
$dailyTrend           = []; // tanggal => ['reports' => int, 'issues' => int]
$condDistribution     = [];
$shiftDistribution    = [];

foreach ($periodReports as $pr) {
    if ($pr['status_validasi'] === 'TERVALIDASI') $totalValidated++;
    $isIssue = (stripos($pr['kondisi_operasional'], 'Normal') === false);
    if ($isIssue) $totalIssues++;

    $d = $pr['tanggal'];
    if (!isset($dailyTrend[$d])) $dailyTrend[$d] = ['reports' => 0, 'issues' => 0];
    $dailyTrend[$d]['reports']++;
    if ($isIssue) $dailyTrend[$d]['issues']++;

    $cond = $pr['kondisi_operasional'] ?: 'Normal';
    $condDistribution[$cond] = ($condDistribution[$cond] ?? 0) + 1;

    $shName = $pr['nama_shift'] ?: '-';
    $shiftDistribution[$shName] = ($shiftDistribution[$shName] ?? 0) + 1;
}

$validationRate = $totalPeriodReports > 0 ? round(($totalValidated / $totalPeriodReports) * 100) : 0;

$totalTasksCount = count($rekapTasks);
$taskStatusCount = ['Selesai' => 0, 'Berjalan' => 0, 'Tertunda' => 0, 'Belum Mulai' => 0];
foreach ($rekapTasks as $rt) {
    $st = $rt['status'] ?? 'Berjalan';
    $taskStatusCount[$st] = ($taskStatusCount[$st] ?? 0) + 1;
}
$completionRate = $totalTasksCount > 0 ? round(($taskStatusCount['Selesai'] / $totalTasksCount) * 100) : 0;

// Notulen count in period
require_once __DIR__ . '/../includes/notulen_helper.php';
ensure_notulen_tables($pdo);
$stmtNot = $pdo->prepare("SELECT COUNT(*) FROM notulen_meeting WHERE tanggal BETWEEN :start AND :end");
$stmtNot->execute(['start' => $startDate, 'end' => $endDate]);
$totalNotulenPeriod = (int)$stmtNot->fetchColumn();

$masterLokasi = $pdo->query("SELECT * FROM master_lokasi ORDER BY nama_lokasi ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header Banner -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-calendar-check"></i>
        <span>Logbook Multi-Shift</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-calendar-check text-[#6558f5]"></i>
        <span>Rekapitulasi Harian Pekerjaan Operasional</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Daftar akumulasi tindak lanjut pekerjaan lapangan antar shift (Format Standar OMIT)
      </p>
    </div>
  </div>

  <!-- Filter Card -->
  <div class="p-5 sm:p-6 rounded-[26px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature widget-card">
    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
      <div>
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Dari Tanggal</label>
        <input type="date" name="start_date" value="<?= sanitize($startDate) ?>"
               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5] focus:outline-none" />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
        <input type="date" name="end_date" value="<?= sanitize($endDate) ?>"
               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5] focus:outline-none" />
      </div>

      <div>
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lokasi / Unit</label>
        <select name="lokasi_id" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
          <option value="0">Semua Unit</option>
          <?php foreach ($masterLokasi as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $lokasiId == $l['id'] ? 'selected' : '' ?>>
              <?= sanitize($l['nama_lokasi']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <button type="submit" 
                class="flex-1 py-2 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-bold text-xs transition duration-200 flex items-center justify-center gap-1.5 shadow-sm cursor-pointer">
          <i class="ph-bold ph-funnel"></i>
          <span>Terapkan Filter</span>
        </button>
        <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&lokasi_id=<?= $lokasiId ?>&export=xls"
           class="py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1 shadow-sm" title="Unduh Excel .xls">
          <i class="ph-bold ph-microsoft-excel-logo"></i>
          <span>Excel</span>
        </a>
        <a href="<?= BASE_URL ?>rekap/index" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold" title="Reset">
          <i class="ph-bold ph-arrow-counter-clockwise"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- ANALITIK & EVALUASI PERIODE -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="p-4 sm:p-5 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <div class="flex items-center justify-between">
        <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Laporan &amp; Validasi</span>
        <i class="ph-fill ph-shield-check text-lg text-[#6558f5]"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $totalPeriodReports ?></span>
        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400"><?= $validationRate ?>% Tervalidasi</span>
      </div>
    </div>

    <div class="p-4 sm:p-5 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <div class="flex items-center justify-between">
        <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Penyelesaian Tiket</span>
        <i class="ph-fill ph-check-circle text-lg text-emerald-500"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $completionRate ?>%</span>
        <span class="text-xs font-semibold text-slate-400">(<?= $taskStatusCount['Selesai'] ?>/<?= $totalTasksCount ?>)</span>
      </div>
    </div>

    <div class="p-4 sm:p-5 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <div class="flex items-center justify-between">
        <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Shift Non-Normal</span>
        <i class="ph-fill ph-warning-circle text-lg text-rose-500"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-black text-rose-600 dark:text-rose-400"><?= $totalIssues ?></span>
        <span class="text-xs font-semibold text-slate-400">laporan pantauan/gangguan</span>
      </div>
    </div>

    <div class="p-4 sm:p-5 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <div class="flex items-center justify-between">
        <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Notulen / Briefing</span>
        <i class="ph-fill ph-notepad text-lg text-indigo-500"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-black text-[#181b2c] dark:text-white"><?= $totalNotulenPeriod ?></span>
        <span class="text-xs font-semibold text-slate-400">catatan rapat</span>
      </div>
    </div>
  </div>

  <!-- GRAFIK TREN HARIAN & DISTRIBUSI KONDISI / STATUS -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Grafik Batang Tren Harian -->
    <div class="lg:col-span-7 bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-100 dark:border-slate-800/80 shadow-card p-5 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Tren Laporan Harian</h3>
          <p class="text-[11px] text-slate-400">Jumlah serah terima &amp; temuan gangguan per tanggal</p>
        </div>
        <div class="flex items-center gap-3 text-[10px] font-bold">
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-[#6558f5] inline-block"></span> Laporan</span>
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500 inline-block"></span> Gangguan</span>
        </div>
      </div>

      <?php if (empty($dailyTrend)): ?>
        <div class="py-10 text-center text-xs text-slate-400">Belum ada data laporan pada rentang tanggal ini.</div>
      <?php else: ?>
        <?php $maxDaily = max(1, max(array_column($dailyTrend, 'reports'))); ?>
        <div class="flex items-end gap-2 h-40 overflow-x-auto pb-2 pt-4">
          <?php foreach ($dailyTrend as $dt => $vals): ?>
            <?php
              $hTotal = max(10, (int)round(($vals['reports'] / $maxDaily) * 100));
              $hIssue = $vals['issues'] > 0 ? max(8, (int)round(($vals['issues'] / $maxDaily) * 100)) : 0;
            ?>
            <div class="flex flex-col items-center gap-1 min-w-[38px] flex-1">
              <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-300"><?= $vals['reports'] ?></span>
              <div class="w-full max-w-[28px] h-24 bg-slate-100 dark:bg-slate-800 rounded-lg flex items-end justify-center gap-0.5 p-0.5 overflow-hidden">
                <div class="flex-1 bg-[#6558f5] rounded-md transition-all" style="height: <?= $hTotal ?>%" title="<?= $vals['reports'] ?> laporan"></div>
                <?php if ($hIssue > 0): ?>
                  <div class="w-1.5 bg-rose-500 rounded-md" style="height: <?= $hIssue ?>%" title="<?= $vals['issues'] ?> gangguan"></div>
                <?php endif; ?>
              </div>
              <span class="text-[9.5px] font-mono text-slate-400"><?= date('d/m', strtotime($dt)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Distribusi Status Tugas & Kondisi Operasional -->
    <div class="lg:col-span-5 bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-100 dark:border-slate-800/80 shadow-card p-5 sm:p-6 space-y-5">
      <div>
        <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white mb-3">Rasio Status Pekerjaan</h3>
        <div class="space-y-2.5 text-xs">
          <?php
            $statusColors = ['Selesai' => 'bg-emerald-500', 'Berjalan' => 'bg-blue-500', 'Tertunda' => 'bg-amber-500', 'Belum Mulai' => 'bg-slate-400'];
            foreach ($taskStatusCount as $stLabel => $stVal):
              $pct = $totalTasksCount > 0 ? round(($stVal / $totalTasksCount) * 100) : 0;
          ?>
            <div>
              <div class="flex justify-between font-bold mb-1">
                <span class="text-slate-600 dark:text-slate-300"><?= $stLabel ?></span>
                <span class="text-slate-800 dark:text-white"><?= $stVal ?> (<?= $pct ?>%)</span>
              </div>
              <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full <?= $statusColors[$stLabel] ?? 'bg-indigo-500' ?>" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($condDistribution)): ?>
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
          <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Distribusi Kondisi Shift</h4>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($condDistribution as $cName => $cCnt): ?>
              <span class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-[11px] font-bold">
                <?= sanitize($cName) ?>: <strong class="text-[#6558f5]"><?= $cCnt ?></strong>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tasks Rekap Table Card -->
  <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-100 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
      <div class="text-xs text-slate-500 font-medium">
        Total <strong><?= count($rekapTasks) ?></strong> baris pekerjaan tercatat pada rentang periode ini
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase tracking-wider">
          <tr>
            <th class="py-3 px-3">Tanggal / Shift</th>
            <th class="py-3 px-3">Unit</th>
            <th class="py-3 px-3">No / Tiket</th>
            <th class="py-3 px-3">Pekerjaan / Masalah</th>
            <th class="py-3 px-3">Prioritas</th>
            <th class="py-3 px-3">Status</th>
            <th class="py-3 px-3">Tindak Lanjut</th>
            <th class="py-3 px-3">PIC</th>
            <th class="py-3 px-3">Target</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
          <?php if (empty($rekapTasks)): ?>
            <tr>
              <td colspan="9" class="py-10 text-center text-slate-400">
                <i class="ph ph-tray text-3xl mb-1 block"></i>
                Tidak ada catatan pekerjaan pada periode yang dipilih.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rekapTasks as $t): ?>
              <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-900/30 transition">
                <td class="py-3 px-3 whitespace-nowrap">
                  <div class="font-bold text-slate-800 dark:text-slate-100"><?= format_tanggal($t['tanggal']) ?></div>
                  <div class="text-[10px] text-indigo-600 font-semibold">Shift <?= sanitize($t['nama_shift']) ?></div>
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#131b2e] text-slate-700 dark:text-slate-300 font-medium text-[11px]">
                    <?= sanitize($t['nama_lokasi']) ?>
                  </span>
                </td>
                <td class="py-3 px-3 font-mono font-bold text-[#6558f5] whitespace-nowrap">
                  <a href="<?= BASE_URL ?>laporan/detail?id=<?= $t['laporan_id'] ?>" class="hover:underline">
                    <?= sanitize($t['no_tiket'] ?: 'Tugas') ?>
                  </a>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-800 dark:text-slate-100 max-w-xs">
                  <?= sanitize($t['pekerjaan_masalah']) ?>
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <?= badge_prioritas($t['prioritas']) ?>
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <?= badge_status_pekerjaan($t['status']) ?>
                </td>
                <td class="py-3 px-3 text-slate-600 dark:text-slate-300 max-w-xs">
                  <?= sanitize($t['kondisi_tindak_lanjut']) ?>
                </td>
                <td class="py-3 px-3 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                  <?= sanitize($t['pic']) ?>
                </td>
                <td class="py-3 px-3 text-slate-500 whitespace-nowrap">
                  <?= sanitize($t['target_selesai'] ?: '-') ?>
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

