<?php
// laporan/index.php - Riwayat Laporan Serah Terima Shift
$current_page = 'laporan_list';
$page_title = 'Riwayat Laporan Serah Terima';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();

// Filter parameters
$search    = trim($_GET['search'] ?? '');
$filterShift  = (int)($_GET['shift_id'] ?? 0);
$filterLokasi = (int)($_GET['lokasi_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');
$filterDate    = trim($_GET['tanggal'] ?? '');
$filterKondisi = trim($_GET['kondisi'] ?? '');

// Master data for filters
$shifts  = $pdo->query("SELECT * FROM master_shift ORDER BY id ASC")->fetchAll();
$lokasis = $pdo->query("SELECT * FROM master_lokasi ORDER BY nama_lokasi ASC")->fetchAll();

// Build query
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(l.nomor_laporan LIKE :s1 OR u1.nama_lengkap LIKE :s2 OR u2.nama_lengkap LIKE :s3 OR l.catatan_penting LIKE :s4)";
    $likeVal = "%{$search}%";
    $params['s1'] = $likeVal;
    $params['s2'] = $likeVal;
    $params['s3'] = $likeVal;
    $params['s4'] = $likeVal;
}
if ($filterShift > 0) {
    $where[] = "l.shift_id = :shift_id";
    $params['shift_id'] = $filterShift;
}
if ($filterLokasi > 0) {
    $where[] = "l.lokasi_id = :lokasi_id";
    $params['lokasi_id'] = $filterLokasi;
}
if ($filterStatus !== '') {
    $where[] = "l.status_validasi = :status_validasi";
    $params['status_validasi'] = $filterStatus;
}
if ($filterDate !== '') {
    $where[] = "l.tanggal = :tanggal";
    $params['tanggal'] = $filterDate;
}
if ($filterKondisi !== '') {
    $where[] = "l.kondisi_operasional = :kondisi";
    $params['kondisi'] = $filterKondisi;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();

// Fetch reports
$query = "
    SELECT l.*, s.nama_shift, m.nama_lokasi, 
           u1.nama_lengkap AS nama_menyerahkan, 
           u2.nama_lengkap AS nama_menerima,
           (SELECT COUNT(*) FROM laporan_pekerjaan p WHERE p.laporan_id = l.id) AS total_tugas,
           (SELECT COUNT(*) FROM laporan_pekerjaan p WHERE p.laporan_id = l.id AND p.status != 'Selesai') AS total_aktif,
           (SELECT COUNT(*) FROM laporan_pekerjaan p WHERE p.laporan_id = l.id AND p.prioritas = 'Tinggi / Kritis' AND p.status != 'Selesai') AS total_kritis
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    WHERE {$whereSql}
    ORDER BY l.tanggal DESC, l.jam_serah_terima DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Top Title & Action Button -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-archive"></i>
        <span>Dokumentasi Resmi OMIT</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-folders text-[#6558f5]"></i>
        <span>Riwayat Laporan Serah Terima Shift</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Kelola, tinjau approval, dan cetak arsip formulir handover tugas operasional
      </p>
    </div>
    
    <a href="<?= BASE_URL ?>laporan/create" 
       class="px-5 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md hover:shadow-lg transition flex items-center gap-2 self-start sm:self-auto">
      <i class="ph-bold ph-plus-circle text-base"></i>
      <span>Buat Serah Terima Baru</span>
    </a>
  </div>

  <!-- Quick Filter Chips (1-Klik) -->
  <?php
    $todayDate = date('Y-m-d');
    $isAll     = ($search === '' && $filterShift === 0 && $filterLokasi === 0 && $filterStatus === '' && $filterDate === '' && $filterKondisi === '');
  ?>
  <div class="flex items-center gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>laporan/index"
       class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition flex items-center gap-1.5 <?= $isAll ? 'bg-[#6558f5] text-white shadow-sm' : 'bg-white dark:bg-[#1b1e30] text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 hover:border-[#6558f5]/40' ?>">
      <i class="ph-bold ph-folders"></i>
      <span>Semua</span>
    </a>
    <a href="<?= BASE_URL ?>laporan/index?tanggal=<?= $todayDate ?>"
       class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition flex items-center gap-1.5 <?= $filterDate === $todayDate ? 'bg-[#6558f5] text-white shadow-sm' : 'bg-white dark:bg-[#1b1e30] text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 hover:border-[#6558f5]/40' ?>">
      <i class="ph-bold ph-calendar-blank"></i>
      <span>Hari Ini</span>
    </a>
    <a href="<?= BASE_URL ?>laporan/index?status=MENUNGGU+VALIDASI"
       class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition flex items-center gap-1.5 <?= $filterStatus === 'MENUNGGU VALIDASI' ? 'bg-amber-500 text-white shadow-sm' : 'bg-white dark:bg-[#1b1e30] text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 hover:border-amber-400/50' ?>">
      <i class="ph-bold ph-hourglass-medium"></i>
      <span>Menunggu Validasi</span>
    </a>
    <a href="<?= BASE_URL ?>laporan/index?status=TERVALIDASI"
       class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition flex items-center gap-1.5 <?= $filterStatus === 'TERVALIDASI' ? 'bg-emerald-500 text-white shadow-sm' : 'bg-white dark:bg-[#1b1e30] text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 hover:border-emerald-400/50' ?>">
      <i class="ph-bold ph-check-circle"></i>
      <span>Tervalidasi</span>
    </a>
    <a href="<?= BASE_URL ?>laporan/index?kondisi=Ada+Gangguan"
       class="px-3.5 py-1.5 rounded-full text-xs font-extrabold transition flex items-center gap-1.5 <?= $filterKondisi === 'Ada Gangguan' ? 'bg-rose-500 text-white shadow-sm' : 'bg-white dark:bg-[#1b1e30] text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/80 hover:border-rose-400/50' ?>">
      <i class="ph-bold ph-warning-circle"></i>
      <span>Ada Gangguan</span>
    </a>
  </div>

  <!-- Filter Card -->
  <div class="p-5 sm:p-6 rounded-[26px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature widget-card">
    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
      <!-- Search Keyword -->
      <div class="lg:col-span-2">
        <label class="block text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Cari Nomor Laporan / Petugas</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="ph ph-magnifying-glass"></i>
          </span>
          <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="No. laporan, nama petugas..."
                 class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>
      </div>

      <!-- Shift -->
      <div>
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Shift</label>
        <select name="shift_id" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
          <option value="0">Semua Shift</option>
          <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $filterShift == $s['id'] ? 'selected' : '' ?>>
              Shift <?= sanitize($s['nama_shift']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status Validasi -->
      <div>
        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Status Validasi</label>
        <select name="status" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
          <option value="">Semua Status</option>
          <option value="TERVALIDASI" <?= $filterStatus === 'TERVALIDASI' ? 'selected' : '' ?>>Tervalidasi</option>
          <option value="MENUNGGU VALIDASI" <?= $filterStatus === 'MENUNGGU VALIDASI' ? 'selected' : '' ?>>Menunggu Validasi</option>
          <option value="DRAFT" <?= $filterStatus === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
        </select>
      </div>

      <!-- Submit & Reset Buttons -->
      <div class="flex items-end gap-2">
        <button type="submit" 
                class="flex-1 py-2 px-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-[#6558f5] text-[#6558f5] hover:text-white font-bold text-xs transition duration-200 flex items-center justify-center gap-1.5">
          <i class="ph-bold ph-funnel"></i>
          <span>Filter</span>
        </button>
        <a href="<?= BASE_URL ?>laporan/index" 
           class="py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-semibold text-xs transition" title="Reset Filter">
          <i class="ph-bold ph-arrow-counter-clockwise text-sm"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- Reports Table Card -->
  <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-100 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
      <div class="text-xs text-slate-500 font-medium">
        Menampilkan <strong><?= count($reports) ?></strong> dari total <strong><?= $totalRows ?></strong> laporan
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase tracking-wider">
          <tr>
            <th class="py-3 px-4">No. Dokumen</th>
            <th class="py-3 px-4">Waktu & Unit</th>
            <th class="py-3 px-4">Kondisi</th>
            <th class="py-3 px-4">Petugas Shift</th>
            <th class="py-3 px-4 text-center">Tugas Aktif</th>
            <th class="py-3 px-4">Status Validasi</th>
            <th class="py-3 px-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
          <?php if (empty($reports)): ?>
            <tr>
              <td colspan="7" class="py-12 text-center text-slate-400">
                <i class="ph ph-folder-open text-4xl mb-2 block"></i>
                Tidak ada laporan yang sesuai dengan kriteria filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($reports as $r): ?>
              <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/30 transition">
                <td class="py-3.5 px-4">
                  <a href="<?= BASE_URL ?>laporan/detail?id=<?= $r['id'] ?>" class="font-extrabold text-[#6558f5] hover:underline block font-mono">
                    <?= sanitize($r['nomor_laporan']) ?>
                  </a>
                  <span class="text-[10px] text-slate-400 font-mono"><?= date('H:i', strtotime($r['created_at'])) ?> WIB</span>
                </td>

                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-800 dark:text-slate-100"><?= format_tanggal($r['tanggal']) ?></div>
                  <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                    <span class="font-semibold text-indigo-600">Shift <?= sanitize($r['nama_shift']) ?></span>
                    <span>•</span>
                    <span><?= sanitize($r['nama_lokasi']) ?></span>
                  </div>
                </td>

                <td class="py-3.5 px-4">
                  <?= badge_kondisi_operasional($r['kondisi_operasional']) ?>
                </td>

                <td class="py-3.5 px-4">
                  <div class="text-[11px]">
                    <span class="text-slate-400">Lama:</span> <strong class="text-slate-700 dark:text-slate-200"><?= sanitize($r['nama_menyerahkan']) ?></strong>
                  </div>
                  <div class="text-[11px] mt-0.5">
                    <span class="text-slate-400">Baru:</span> <strong class="text-slate-700 dark:text-slate-200"><?= sanitize($r['nama_menerima'] ?? 'Menunggu') ?></strong>
                  </div>
                </td>

                <td class="py-3.5 px-4 text-center">
                  <div class="inline-flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 font-bold text-[11px]">
                      <?= $r['total_aktif'] ?> Aktif
                    </span>
                    <?php if ($r['total_kritis'] > 0): ?>
                      <span class="px-2 py-0.5 rounded-lg bg-rose-100 text-rose-700 font-extrabold text-[11px]" title="Pekerjaan Kritis">
                        <?= $r['total_kritis'] ?> Kritis
                      </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td class="py-3.5 px-4">
                  <?= badge_status_validasi($r['status_validasi']) ?>
                </td>

                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <?php if ($r['status_validasi'] === 'DRAFT' && ($r['created_by'] == $user['id'] || is_admin())): ?>
                      <a href="<?= BASE_URL ?>laporan/create?edit=<?= $r['id'] ?>"
                         title="Lanjutkan Edit Draft"
                         class="p-2 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-300 hover:bg-amber-500 hover:text-white transition">
                        <i class="ph-bold ph-pencil-simple text-sm"></i>
                      </a>
                    <?php endif; ?>
                    <!-- Detail -->
                    <a href="<?= BASE_URL ?>laporan/detail?id=<?= $r['id'] ?>" 
                       title="Lihat Detail & Pengesahan"
                       class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 hover:text-indigo-600 transition">
                      <i class="ph-bold ph-eye text-sm"></i>
                    </a>

                    <!-- Print PDF -->
                    <a href="<?= BASE_URL ?>laporan/print?id=<?= $r['id'] ?>" target="_blank"
                       title="Cetak Dokumen PDF Format Standar OMIT"
                       class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-[#6558f5] hover:bg-[#6558f5] hover:text-white transition">
                      <i class="ph-bold ph-printer text-sm"></i>
                    </a>

                    <!-- Delete (Admin Only) -->
                    <?php if (is_admin()): ?>
                      <a href="<?= BASE_URL ?>laporan/delete?id=<?= $r['id'] ?>" 
                         onclick="return confirm('Hapus laporan <?= sanitize($r['nomor_laporan']) ?> secara permanen?')"
                         title="Hapus Laporan (Admin)"
                         class="p-2 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white transition">
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

