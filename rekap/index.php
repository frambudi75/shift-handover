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

$where = ["l.tanggal BETWEEN :start AND :end"];
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
                class="flex-1 py-2 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-bold text-xs transition duration-200 flex items-center justify-center gap-1.5 shadow-sm">
          <i class="ph-bold ph-funnel"></i>
          <span>Terapkan Filter</span>
        </button>
        <a href="<?= BASE_URL ?>rekap/index.php" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold" title="Reset">
          <i class="ph-bold ph-arrow-counter-clockwise"></i>
        </a>
      </div>
    </form>
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
                  <a href="<?= BASE_URL ?>laporan/detail.php?id=<?= $t['laporan_id'] ?>" class="hover:underline">
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

