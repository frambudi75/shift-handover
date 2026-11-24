<?php
// dashboard.php - Main Overview Dashboard (Full Width, Responsive, Dark/Light Mode)
$current_page = 'dashboard';
$page_title = 'Dashboard Operasional';
require_once __DIR__ . '/includes/header.php';

// Safe user display name
$displayName = !empty($user['nama_lengkap']) ? $user['nama_lengkap'] : (!empty($user['username']) ? $user['username'] : 'Rekan Operasional');

// Detect currently active shift
$nowTime = date('H:i:s');
$activeShifts = $pdo->query("SELECT nama_shift, jam_mulai, jam_selesai FROM master_shift WHERE status = 'aktif' ORDER BY id ASC")->fetchAll();
$currentShiftLabel = 'Operasional Aktif';
foreach ($activeShifts as $sh) {
    $start = $sh['jam_mulai'];
    $end   = $sh['jam_selesai'];
    $isMatch = ($start <= $end)
        ? ($nowTime >= $start && $nowTime < $end)
        : ($nowTime >= $start || $nowTime < $end); // Overnight shift e.g. 23:00 - 07:00
    if ($isMatch) {
        $currentShiftLabel = 'Shift ' . $sh['nama_shift'] . ' (' . substr($start, 0, 5) . ' - ' . substr($end, 0, 5) . ')';
        break;
    }
}

// Fetch statistics
$totalLaporan = $pdo->query("SELECT COUNT(*) FROM laporan_serah_terima")->fetchColumn();

$today = date('Y-m-d');
$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM laporan_serah_terima WHERE tanggal = :today");
$stmtToday->execute(['today' => $today]);
$laporanHariIni = $stmtToday->fetchColumn();

// Total pekerjaan aktif (berdasarkan status terbaru tiap nomor tiket / pekerjaan)
$queryLatestAktif = "
    SELECT COUNT(*) 
    FROM laporan_pekerjaan p
    WHERE p.id IN (
        SELECT MAX(p2.id)
        FROM laporan_pekerjaan p2
        GROUP BY CASE WHEN p2.no_tiket IS NOT NULL AND p2.no_tiket != '' THEN p2.no_tiket ELSE CONCAT('ID_', p2.id) END
    )
    AND p.status != 'Selesai'
";
$pekerjaanAktif = $pdo->query($queryLatestAktif)->fetchColumn();

// Pekerjaan prioritas tinggi aktif (hanya yang status terbarunya belum selesai)
$queryLatestKritis = "
    SELECT COUNT(*) 
    FROM laporan_pekerjaan p
    WHERE p.id IN (
        SELECT MAX(p2.id)
        FROM laporan_pekerjaan p2
        GROUP BY CASE WHEN p2.no_tiket IS NOT NULL AND p2.no_tiket != '' THEN p2.no_tiket ELSE CONCAT('ID_', p2.id) END
    )
    AND p.prioritas = 'Tinggi / Kritis'
    AND p.status != 'Selesai'
";
$pekerjaanKritis = $pdo->query($queryLatestKritis)->fetchColumn();

// Laporan butuh validasi saya (jika user penerima)
$userId = $user['id'];
$stmtPendingVal = $pdo->prepare("SELECT COUNT(*) FROM laporan_serah_terima WHERE status_validasi = 'MENUNGGU VALIDASI' AND (petugas_menerima_id = :uid OR :is_admin = 1)");
$stmtPendingVal->execute(['uid' => $userId, 'is_admin' => is_admin() ? 1 : 0]);
$pendingMyValidation = $stmtPendingVal->fetchColumn();

// Recent 5 Laporan
$recentReports = $pdo->query("
    SELECT l.*, s.nama_shift, m.nama_lokasi, 
           u1.nama_lengkap AS nama_menyerahkan, 
           u2.nama_lengkap AS nama_menerima
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    ORDER BY l.tanggal DESC, l.jam_serah_terima DESC
    LIMIT 6
")->fetchAll();

// Critical tasks needing attention (berdasarkan status terbaru tiap tiket pekerjaan)
$queryCriticalTasks = "
    SELECT p.*, l.nomor_laporan, l.tanggal, s.nama_shift, m.nama_lokasi
    FROM laporan_pekerjaan p
    JOIN laporan_serah_terima l ON p.laporan_id = l.id
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    WHERE p.id IN (
        SELECT MAX(p2.id)
        FROM laporan_pekerjaan p2
        GROUP BY CASE WHEN p2.no_tiket IS NOT NULL AND p2.no_tiket != '' THEN p2.no_tiket ELSE CONCAT('ID_', p2.id) END
    )
    AND p.status != 'Selesai'
    ORDER BY CASE WHEN p.prioritas = 'Tinggi / Kritis' THEN 1 WHEN p.prioritas = 'Sedang' THEN 2 ELSE 3 END, p.id DESC
    LIMIT 5
";
$criticalTasks = $pdo->query($queryCriticalTasks)->fetchAll();
?>

<!-- PENDING VALIDATION ALERT -->
<?php if ($pendingMyValidation > 0): ?>
  <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 text-white shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-stagger-1">
    <div class="flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center text-2xl font-bold flex-shrink-0">
        <i class="ph-bold ph-hourglass-medium"></i>
      </div>
      <div>
        <h4 class="font-extrabold text-sm sm:text-base">Ada <?= $pendingMyValidation ?> Laporan Menunggu Pengesahan Anda!</h4>
        <p class="text-xs text-amber-100 mt-0.5">Harap periksa dan lakukan validasi penerimaan serah terima shift baru.</p>
      </div>
    </div>
    <a href="<?= BASE_URL ?>laporan/index?status=MENUNGGU+VALIDASI" 
       class="px-5 py-2.5 bg-white text-amber-800 font-extrabold text-xs rounded-xl hover:bg-amber-50 active:scale-95 transition shadow self-start sm:self-auto whitespace-nowrap">
      Periksa Sekarang
    </a>
  </div>
<?php endif; ?>

<?php if (!empty($bellFeed['banners'])): ?>
  <!-- BANNER PENGUMUMAN RESMI DARI SUPERADMIN -->
  <div class="space-y-3 mb-6 animate-stagger-1">
    <?php foreach ($bellFeed['banners'] as $b): ?>
      <?php
        $bannerStyle = match($b['category']) {
            'darurat' => 'bg-gradient-to-r from-rose-600 to-rose-500 text-white shadow-rose-500/20',
            'penting' => 'bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-amber-500/20',
            default   => 'bg-gradient-to-r from-[#5946ea] to-[#6558f5] text-white shadow-[#6558f5]/20',
        };
      ?>
      <div class="p-4 sm:p-5 rounded-3xl <?= $bannerStyle ?> shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
          <div class="w-11 h-11 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0">
            <i class="ph-fill ph-megaphone text-2xl"></i>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2 py-0.5 rounded-full bg-white/20 text-[9.5px] font-extrabold uppercase tracking-wider">
                PENGUMUMAN <?= strtoupper($b['category']) ?>
              </span>
              <span class="text-[11px] text-white/80 font-semibold">• Cek detail di ikon lonceng notifikasi atas</span>
            </div>
            <h4 class="font-extrabold text-sm sm:text-base mt-1 leading-snug"><?= sanitize($b['title']) ?></h4>
            <p class="text-xs text-white/90 mt-0.5 line-clamp-2"><?= sanitize($b['message']) ?></p>
          </div>
        </div>
        <button type="button"
                onclick='openReadAnnouncementModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                class="px-4 py-2 bg-white text-slate-900 font-extrabold text-xs rounded-xl hover:bg-slate-100 active:scale-95 transition shadow self-start sm:self-center whitespace-nowrap cursor-pointer">
          Baca Pengumuman
        </button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- NOTEFLOW HERO SECTION: GREETING + QUICK ADD + 3 ILLUSTRATED FEATURE CARDS -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch mb-8 animate-stagger-1">

  <!-- Left Greeting Column (4 cols) -->
  <div class="lg:col-span-4 flex flex-col justify-center pr-2 py-1">
    <div class="flex items-center gap-3 flex-wrap">
      <h1 class="text-[28px] sm:text-[32px] font-extrabold tracking-tight text-[#181b2c] dark:text-white leading-tight">
        Hi, <span><?= sanitize($displayName) ?></span>!
      </h1>
      <!-- Overlapping Dual Badges next to greeting (from NoteFlow reference) -->
      <div class="inline-flex items-center bg-[#ebebf8] dark:bg-slate-800 p-1 rounded-full shadow-sm">
        <div title="Role cluster" class="w-7 h-7 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center shadow-sm relative z-10">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <circle cx="9" cy="9" r="4" fill="#6558f5"/>
            <circle cx="16" cy="11" r="3" fill="#9a92f9"/>
            <circle cx="11" cy="16" r="3.5" fill="#4f43d9"/>
          </svg>
        </div>
        <div title="System status active" class="-ml-1.5 w-7 h-7 rounded-full bg-[#eeedfa] dark:bg-slate-800 border border-white dark:border-slate-700 flex items-center justify-center">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="8" stroke="#cbc7f7" stroke-width="2.2"/>
            <path d="M12 4 A8 8 0 1 1 5.5 16.5" stroke="#6558f5" stroke-width="2.2" stroke-linecap="round"/>
            <circle cx="12" cy="12" r="3" stroke="#6558f5" stroke-width="1.6"/>
          </svg>
        </div>
      </div>
    </div>

    <h2 class="text-[24px] sm:text-[28px] font-bold tracking-tight text-[#181b2c] dark:text-white leading-[1.2] mt-1">
      Pantau kelancaran<br/>operasional hari ini.
    </h2>

    <!-- Live Shift & Real-Time WIB Clock Pill -->
    <div class="mt-3 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-700/80 shadow-xs w-fit flex-wrap">
      <span class="pulse-radar-wrap w-2 h-2 shrink-0">
        <span class="pulse-radar-ring"></span>
        <span class="relative inline-flex rounded-full w-2 h-2 bg-emerald-500"></span>
      </span>
      <span class="text-[11px] font-extrabold text-[#181b2c] dark:text-slate-100"><?= sanitize($currentShiftLabel) ?></span>
      <span class="text-slate-300 dark:text-slate-600">•</span>
      <span id="live-wib-clock" class="text-[11px] font-mono font-extrabold text-[#6558f5] dark:text-indigo-400"><?= date('H:i:s') ?> WIB</span>
    </div>

    <p class="text-[12px] text-slate-500 dark:text-slate-400 mt-2.5 leading-relaxed max-w-[340px] font-medium">
      Kelola serah terima tugas operasional secara transparan, pantau tindak lanjut lapangan, dan unduh dokumen standar OMIT.
    </p>
  </div>

  <!-- Quick Add Dashed Box + 3 Feature Cards (8 cols) -->
  <div class="lg:col-span-8 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">

    <!-- Card 0: Dashed Lavender Quick Add Box -->
    <a href="<?= BASE_URL ?>laporan/create"
       class="dashed-lavender-box min-h-[175px] flex flex-col items-center justify-center cursor-pointer group hover:scale-[1.02] active:scale-95 transition-all duration-200 p-4"
       title="Buat Laporan Serah Terima Baru">
      <div class="w-10 h-10 rounded-[14px] bg-[#6c60f6] group-hover:bg-[#574ae2] text-white flex items-center justify-center shadow-md group-hover:rotate-90 transition-all duration-300">
        <i class="ph-bold ph-plus text-lg"></i>
      </div>
      <span class="text-xs font-extrabold text-[#6558f5] dark:text-indigo-300 mt-2.5">Serah Terima Baru</span>
      <span class="text-[10px] text-slate-400 font-medium">Formulir Shift OMIT</span>
    </a>

    <!-- Card 1: Pantau Shift -->
    <a href="<?= BASE_URL ?>laporan/index"
       class="widget-card bg-white dark:bg-[#1b1e30] rounded-[24px] p-5 shadow-feature flex flex-col justify-between group border border-transparent hover:border-[#6558f5]/20 cursor-pointer">
      <div class="h-20 flex items-center justify-center">
        <svg class="w-20 h-20 float-illustration group-hover:scale-105 transition-transform" viewBox="0 0 110 100" fill="none">
          <rect x="24" y="22" width="58" height="62" rx="6" transform="rotate(-6 24 22)" fill="#cbc7fc" opacity="0.55"/>
          <rect x="28" y="18" width="58" height="62" rx="6" fill="white" stroke="#181b2c" stroke-width="2.2"/>
          <path d="M40 32 H74" stroke="#181b2c" stroke-width="2" stroke-linecap="round"/>
          <path d="M40 42 H66" stroke="#6558f5" stroke-width="2" stroke-linecap="round"/>
          <path d="M40 52 H60" stroke="#a5a1d4" stroke-width="2" stroke-linecap="round"/>
          <circle cx="70" cy="56" r="8" fill="#68cfa3" stroke="#181b2c" stroke-width="2"/>
          <path d="M67 56 L69 58 L74 53" stroke="#181b2c" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <h3 class="text-xs font-extrabold text-[#181b2c] dark:text-white leading-tight">Pantau Shift</h3>
        <p class="text-[10.5px] text-slate-400 mt-0.5 leading-snug">Riwayat lengkap tugas serah terima</p>
      </div>
    </a>

    <!-- Card 2: Sinkron Logbook -->
    <a href="<?= BASE_URL ?>rekap/index"
       class="widget-card bg-white dark:bg-[#1b1e30] rounded-[24px] p-5 shadow-feature flex flex-col justify-between group border border-transparent hover:border-[#6558f5]/20 cursor-pointer">
      <div class="h-20 flex items-center justify-center">
        <svg class="w-20 h-20 float-illustration group-hover:scale-105 transition-transform" viewBox="0 0 110 100" fill="none">
          <line x1="16" y1="20" x2="94" y2="20" stroke="#cbc7fc" stroke-width="2" stroke-dasharray="3 3"/>
          <rect x="24" y="26" width="30" height="42" rx="4" transform="rotate(-8 24 26)" fill="white" stroke="#181b2c" stroke-width="2"/>
          <rect x="52" y="28" width="30" height="42" rx="4" transform="rotate(6 52 28)" fill="#efeffb" stroke="#6558f5" stroke-width="2"/>
          <circle cx="34" cy="22" r="2.5" fill="#181b2c"/>
          <circle cx="68" cy="24" r="2.5" fill="#6558f5"/>
        </svg>
      </div>
      <div>
        <h3 class="text-xs font-extrabold text-[#181b2c] dark:text-white leading-tight">Rekap Logbook</h3>
        <p class="text-[10.5px] text-slate-400 mt-0.5 leading-snug">Ekspor data ke format Excel .xls</p>
      </div>
    </a>

    <!-- Card 3: Validasi Digital -->
    <a href="<?= BASE_URL ?>laporan/index?status=MENUNGGU+VALIDASI"
       class="widget-card bg-white dark:bg-[#1b1e30] rounded-[24px] p-5 shadow-feature flex flex-col justify-between group border border-transparent hover:border-[#6558f5]/20 cursor-pointer">
      <div class="h-20 flex items-center justify-center">
        <svg class="w-20 h-20 float-illustration group-hover:scale-105 transition-transform" viewBox="0 0 110 100" fill="none">
          <rect x="22" y="22" width="66" height="54" rx="8" fill="white" stroke="#181b2c" stroke-width="2.2"/>
          <path d="M34 38 H76" stroke="#cbc7fc" stroke-width="2" stroke-linecap="round"/>
          <path d="M34 48 H62" stroke="#6558f5" stroke-width="2" stroke-linecap="round"/>
          <circle cx="72" cy="56" r="9" fill="#efeffb" stroke="#6558f5" stroke-width="2"/>
          <path d="M69 56 L71 58 L75 54" stroke="#6558f5" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <h3 class="text-xs font-extrabold text-[#181b2c] dark:text-white leading-tight">Approval &amp; TTD</h3>
        <p class="text-[10.5px] text-slate-400 mt-0.5 leading-snug">Barcode QR &amp; tanda tangan basah</p>
      </div>
    </a>

  </div>
</div>

<!-- 4 STATS CARDS (NOTEFLOW METRIC CARDS WITH HOVER LIFT) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8 animate-stagger-2">
  <!-- 1. Total Laporan -->
  <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
    <div class="flex items-center justify-between">
      <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Laporan</span>
      <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-400 flex items-center justify-center text-lg font-bold shadow-sm">
        <i class="ph-fill ph-files"></i>
      </div>
    </div>
    <div class="mt-4 flex items-baseline gap-2">
      <span class="text-3xl font-black text-[#181b2c] dark:text-white tracking-tight"><?= $totalLaporan ?></span>
      <span class="text-xs text-slate-400 font-semibold">laporan tercatat</span>
    </div>
  </div>

  <!-- 2. Laporan Hari Ini -->
  <div class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature">
    <div class="flex items-center justify-between">
      <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Laporan Hari Ini</span>
      <div class="w-10 h-10 rounded-2xl bg-[#e6f7f0] dark:bg-emerald-950/60 text-[#1c7a52] dark:text-emerald-400 flex items-center justify-center text-lg font-bold shadow-sm">
        <i class="ph-fill ph-calendar-check"></i>
      </div>
    </div>
    <div class="mt-4 flex items-baseline gap-2">
      <span class="text-3xl font-black text-[#181b2c] dark:text-white tracking-tight"><?= $laporanHariIni ?></span>
      <span class="inline-flex items-center gap-1.5 text-xs text-[#1c7a52] dark:text-emerald-400 font-extrabold">
        <span class="pulse-radar-wrap w-2 h-2">
          <span class="pulse-radar-ring"></span>
          <span class="relative inline-flex rounded-full w-2 h-2 bg-emerald-500"></span>
        </span>
        <span>shift aktif</span>
      </span>
    </div>
  </div>

  <!-- 3. Pekerjaan Aktif -->
  <a href="<?= BASE_URL ?>laporan/create" title="Klik untuk membuka formulir shift baru dengan pekerjaan outstanding"
     class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature hover:border-blue-400/50 hover:shadow-lg transition group block">
    <div class="flex items-center justify-between">
      <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-blue-600 transition">Pekerjaan Aktif</span>
      <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg font-bold shadow-sm group-hover:scale-110 transition">
        <i class="ph-fill ph-arrows-clockwise"></i>
      </div>
    </div>
    <div class="mt-4 flex items-baseline justify-between">
      <div class="flex items-baseline gap-2">
        <span class="text-3xl font-black text-[#181b2c] dark:text-white tracking-tight"><?= $pekerjaanAktif ?></span>
        <span class="text-xs text-slate-400 font-semibold">perlu dipantau</span>
      </div>
      <span class="text-[11px] font-extrabold text-blue-600 dark:text-blue-400 opacity-0 group-hover:opacity-100 transition flex items-center gap-1">
        <span>Bawa ke Shift</span>
        <i class="ph-bold ph-arrow-right"></i>
      </span>
    </div>
  </a>

  <!-- 4. Prioritas Kritis -->
  <a href="<?= BASE_URL ?>laporan/create" title="Klik untuk segera mengoperkan / menindaklanjuti pekerjaan kritis di shift baru"
     class="widget-card p-5 rounded-[24px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-feature hover:border-rose-400/50 hover:shadow-lg transition group block">
    <div class="flex items-center justify-between">
      <span class="text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-rose-600 transition">Tinggi / Kritis</span>
      <div class="w-10 h-10 rounded-2xl bg-[#fce8ec] dark:bg-rose-950/60 text-[#e0536c] dark:text-rose-400 flex items-center justify-center text-lg font-bold shadow-sm group-hover:scale-110 transition animate-pulse">
        <i class="ph-fill ph-warning-circle"></i>
      </div>
    </div>
    <div class="mt-4 flex items-baseline justify-between">
      <div class="flex items-baseline gap-2">
        <span class="text-3xl font-black text-[#e0536c] dark:text-rose-400 tracking-tight"><?= $pekerjaanKritis ?></span>
        <span class="text-xs text-[#e0536c] font-extrabold">butuh eskalasi</span>
      </div>
      <span class="text-[11px] font-extrabold text-[#e0536c] opacity-0 group-hover:opacity-100 transition flex items-center gap-1">
        <span>Bawa ke Shift</span>
        <i class="ph-bold ph-arrow-right"></i>
      </span>
    </div>
  </a>
</div>

<!-- 2 COLUMN SECTION: RECENT REPORTS & CRITICAL TASKS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-stagger-3">

  <!-- Left: Recent Handover Reports (2 cols) -->
  <div class="lg:col-span-2 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 sm:p-6 widget-card">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white flex items-center gap-2">
          <i class="ph-bold ph-clock-counter-clockwise text-[#6558f5]"></i>
          <span>Riwayat Laporan Serah Terima Terakhir</span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Daftar formulir serah terima shift operasional terbaru</p>
      </div>
      <a href="<?= BASE_URL ?>laporan/index" class="text-xs font-extrabold text-[#6558f5] hover:underline flex items-center gap-1">
        <span>Semua Laporan</span>
        <i class="ph-bold ph-arrow-right"></i>
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3 px-4 rounded-l-xl">No. Laporan</th>
            <th class="py-3 px-4">Tanggal / Shift</th>
            <th class="py-3 px-4">Lokasi / Unit</th>
            <th class="py-3 px-4">Petugas</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4 text-center rounded-r-xl">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
          <?php if (empty($recentReports)): ?>
            <tr>
              <td colspan="6" class="py-8 text-center text-slate-400">
                <i class="ph ph-tray text-3xl mb-2 block"></i>
                Belum ada data laporan serah terima.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentReports as $r): ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition">
                <td class="py-3.5 px-4 font-bold">
                  <a href="<?= BASE_URL ?>laporan/detail?id=<?= $r['id'] ?>" class="text-[#6558f5] hover:underline font-mono">
                    <?= sanitize($r['nomor_laporan']) ?>
                  </a>
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-extrabold text-[#181b2c] dark:text-slate-200"><?= format_tanggal($r['tanggal']) ?></div>
                  <div class="text-[11px] text-slate-400">Shift <?= sanitize($r['nama_shift']) ?> • <?= format_jam($r['jam_serah_terima']) ?></div>
                </td>
                <td class="py-3.5 px-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 font-extrabold text-[11px]">
                    <?= sanitize($r['nama_lokasi']) ?>
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <div class="text-slate-700 dark:text-slate-300">
                    <span class="font-bold text-[#1c7a52]">Lama:</span> <?= sanitize($r['nama_menyerahkan']) ?>
                  </div>
                  <div class="text-[11px] text-slate-400">
                    <span class="font-bold text-[#6558f5]">Baru:</span> <?= sanitize($r['nama_menerima'] ?? 'Menunggu') ?>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <?= badge_status_validasi($r['status_validasi']) ?>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <a href="<?= BASE_URL ?>laporan/detail?id=<?= $r['id'] ?>" 
                       title="Lihat Detail"
                       class="p-2 rounded-xl bg-[#f7f7fb] dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-[#efeffb] hover:text-[#6558f5] active:scale-95 transition">
                      <i class="ph-bold ph-eye text-sm"></i>
                    </a>
                    <a href="<?= BASE_URL ?>laporan/print?id=<?= $r['id'] ?>" target="_blank"
                       title="Cetak PDF Standar OMIT"
                       class="p-2 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] hover:bg-[#6558f5] hover:text-white active:scale-95 transition">
                      <i class="ph-bold ph-printer text-sm"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right: Critical Tasks Needing Follow-up (1 col) -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 sm:p-6 flex flex-col widget-card">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white flex items-center gap-2">
          <i class="ph-bold ph-warning text-[#e0536c]"></i>
          <span>Tindak Lanjut Aktif</span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Pekerjaan terbuka prioritas tinggi</p>
      </div>
      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#fce8ec] text-[#e0536c] dark:bg-rose-950 dark:text-rose-300">
        <?= count($criticalTasks) ?> Item
      </span>
    </div>

    <div class="flex-1 space-y-3">
      <?php if (empty($criticalTasks)): ?>
        <div class="p-8 text-center text-slate-400 my-auto">
          <i class="ph ph-check-circle text-3xl text-[#68cfa3] mb-2 block"></i>
          <p class="text-xs">Semua pekerjaan serah terima sudah selesai. Kondisi optimal!</p>
        </div>
      <?php else: ?>
        <?php foreach ($criticalTasks as $t): ?>
          <div class="p-3.5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800 hover:border-[#6558f5]/30 transition">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <span class="text-[11px] font-bold text-[#6558f5] dark:text-indigo-400 font-mono"><?= sanitize($t['no_tiket'] ?: 'Tugas') ?></span>
              <?= badge_prioritas($t['prioritas']) ?>
            </div>
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 leading-snug">
              <?= sanitize($t['pekerjaan_masalah']) ?>
            </h4>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
              <?= sanitize($t['kondisi_tindak_lanjut']) ?>
            </p>
            <div class="mt-2.5 pt-2 border-t border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-[10px] text-slate-400">
              <span>PIC: <strong class="text-slate-700 dark:text-slate-300"><?= sanitize($t['pic']) ?></strong></span>
              <span>Target: <strong class="text-slate-700 dark:text-slate-300"><?= sanitize($t['target_selesai'] ?: '-') ?></strong></span>
            </div>
            <div class="mt-2.5 pt-2 border-t border-dashed border-slate-200 dark:border-slate-800 flex items-center justify-end gap-1.5">
              <a href="<?= BASE_URL ?>laporan/detail?id=<?= $t['laporan_id'] ?>" 
                 class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-extrabold transition flex items-center gap-1">
                <i class="ph-bold ph-eye"></i>
                <span>Detail</span>
              </a>
              <a href="<?= BASE_URL ?>laporan/create?carry_from=<?= $t['laporan_id'] ?>" 
                 class="px-2.5 py-1 rounded-lg bg-[#6558f5] hover:bg-[#5244e8] text-white text-[10px] font-extrabold transition flex items-center gap-1 shadow-2xs">
                <i class="ph-bold ph-arrow-bend-down-right"></i>
                <span>Oper ke Shift Baru</span>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
