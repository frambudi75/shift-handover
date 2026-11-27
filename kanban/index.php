<?php
// kanban/index.php - Ruang Kerja Pribadi: Kanban Tugas, Kalender Jadwal Kerja & Catatan (100% Privat Per Akun)
$current_page = 'kanban';
$page_title = 'Kanban, Kalender Kerja & Catatan';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user   = current_user();
$userId = (int)$user['id'];

// Auto-migrating private tables
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `personal_kanban_tasks` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT(11) NOT NULL,
          `judul` VARCHAR(200) NOT NULL,
          `deskripsi` TEXT DEFAULT NULL,
          `prioritas` ENUM('Tinggi','Sedang','Rendah') NOT NULL DEFAULT 'Sedang',
          `status` ENUM('Belum Mulai','Berjalan','Selesai') NOT NULL DEFAULT 'Belum Mulai',
          `target_tanggal` DATE DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_pk_user_status` (`user_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `personal_notes` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT(11) NOT NULL,
          `judul` VARCHAR(150) NOT NULL,
          `isi` TEXT NOT NULL,
          `warna` ENUM('indigo','emerald','amber','rose') NOT NULL DEFAULT 'indigo',
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_pn_user` (`user_id`, `updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `personal_work_schedules` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT(11) NOT NULL,
          `tanggal` DATE NOT NULL,
          `judul` VARCHAR(160) NOT NULL,
          `kategori` ENUM('Shift Pagi','Shift Siang','Shift Malam','Lembur','Maintenance','Cuti / Izin','Lainnya') NOT NULL DEFAULT 'Shift Pagi',
          `jam_mulai` TIME DEFAULT NULL,
          `jam_selesai` TIME DEFAULT NULL,
          `lokasi` VARCHAR(120) DEFAULT NULL,
          `catatan` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_pws_user_date` (`user_id`, `tanggal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
}

$scheduleCategories = [
    'Shift Pagi'  => ['bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0', 'dot' => '#10b981', 'darkBg' => 'rgba(16,185,129,0.16)', 'darkText' => '#6ee7b7', 'icon' => 'ph-sun'],
    'Shift Siang' => ['bg' => '#fffbeb', 'text' => '#b45309', 'border' => '#fde68a', 'dot' => '#f59e0b', 'darkBg' => 'rgba(245,158,11,0.16)', 'darkText' => '#fcd34d', 'icon' => 'ph-sun-horizon'],
    'Shift Malam' => ['bg' => '#eef2ff', 'text' => '#4338ca', 'border' => '#c7d2fe', 'dot' => '#6558f5', 'darkBg' => 'rgba(101,88,245,0.2)',  'darkText' => '#a5b4fc', 'icon' => 'ph-moon-stars'],
    'Lembur'      => ['bg' => '#fff1f2', 'text' => '#be123c', 'border' => '#fecdd3', 'dot' => '#e11d48', 'darkBg' => 'rgba(225,29,72,0.18)',  'darkText' => '#fda4af', 'icon' => 'ph-lightning'],
    'Maintenance' => ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe', 'dot' => '#3b82f6', 'darkBg' => 'rgba(59,130,246,0.18)', 'darkText' => '#93c5fd', 'icon' => 'ph-wrench'],
    'Cuti / Izin' => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1', 'dot' => '#64748b', 'darkBg' => 'rgba(100,116,139,0.2)', 'darkText' => '#cbd5e1', 'icon' => 'ph-coffee'],
    'Lainnya'     => ['bg' => '#faf5ff', 'text' => '#7e22ce', 'border' => '#e9d5ff', 'dot' => '#a855f7', 'darkBg' => 'rgba(168,85,247,0.18)', 'darkText' => '#d8b4fe', 'icon' => 'ph-calendar-check'],
];

// Active month & selected date
$todayStr = date('Y-m-d');
$reqMonth = $_GET['month'] ?? $_POST['redirect_month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reqMonth)) {
    $reqMonth = date('Y-m');
}
$reqDate = $_GET['date'] ?? $_POST['redirect_date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate)) {
    $reqDate = (strpos($todayStr, $reqMonth) === 0) ? $todayStr : ($reqMonth . '-01');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_task' || $action === 'save_task') {
        $tid     = (int)($_POST['task_id'] ?? 0);
        $judul   = trim($_POST['judul'] ?? '');
        if ($judul !== '') {
            $prioVal = $_POST['prioritas'] ?? 'Sedang';
            $statVal = $_POST['status'] ?? 'Belum Mulai';
            $prio    = in_array($prioVal, ['Tinggi', 'Sedang', 'Rendah'], true) ? $prioVal : 'Sedang';
            $stat    = in_array($statVal, ['Belum Mulai', 'Berjalan', 'Selesai'], true) ? $statVal : 'Belum Mulai';
            $target  = !empty($_POST['target_tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['target_tanggal']) ? $_POST['target_tanggal'] : null;

            if ($tid > 0) {
                $pdo->prepare("UPDATE personal_kanban_tasks SET judul = :j, deskripsi = :d, prioritas = :p, status = :s, target_tanggal = :t WHERE id = :id AND user_id = :uid")
                    ->execute([
                        'j'   => mb_substr($judul, 0, 200),
                        'd'   => trim($_POST['deskripsi'] ?? ''),
                        'p'   => $prio,
                        's'   => $stat,
                        't'   => $target,
                        'id'  => $tid,
                        'uid' => $userId,
                    ]);
                flash_set('success', 'Tugas pribadi berhasil diperbarui.');
            } else {
                $pdo->prepare("INSERT INTO personal_kanban_tasks (user_id, judul, deskripsi, prioritas, status, target_tanggal) VALUES (:uid, :j, :d, :p, :s, :t)")
                    ->execute([
                        'uid' => $userId,
                        'j'   => mb_substr($judul, 0, 200),
                        'd'   => trim($_POST['deskripsi'] ?? ''),
                        'p'   => $prio,
                        's'   => $stat,
                        't'   => $target,
                    ]);
                flash_set('success', 'Tugas pribadi berhasil ditambahkan.');
            }
        }
    } elseif ($action === 'move_task') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $st  = $_POST['new_status'] ?? '';
        if ($tid > 0 && in_array($st, ['Belum Mulai', 'Berjalan', 'Selesai'], true)) {
            $pdo->prepare("UPDATE personal_kanban_tasks SET status = :s WHERE id = :id AND user_id = :uid")
                ->execute(['s' => $st, 'id' => $tid, 'uid' => $userId]);
        }
    } elseif ($action === 'delete_task') {
        $tid = (int)($_POST['task_id'] ?? 0);
        if ($tid > 0) {
            $pdo->prepare("DELETE FROM personal_kanban_tasks WHERE id = :id AND user_id = :uid")
                ->execute(['id' => $tid, 'uid' => $userId]);
            flash_set('success', 'Tugas pribadi dihapus.');
        }
    } elseif ($action === 'save_schedule') {
        $sid     = (int)($_POST['schedule_id'] ?? 0);
        $judul   = trim($_POST['judul'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? '');
        $katVal  = $_POST['kategori'] ?? 'Shift Pagi';
        $kat     = array_key_exists($katVal, $scheduleCategories) ? $katVal : 'Shift Pagi';
        $jMulai  = !empty($_POST['jam_mulai']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
        $jSelesai= !empty($_POST['jam_selesai']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
        $lokasi  = trim($_POST['lokasi'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($judul !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $reqMonth = substr($tanggal, 0, 7);
            $reqDate  = $tanggal;
            if ($sid > 0) {
                $pdo->prepare("UPDATE personal_work_schedules SET tanggal = :tg, judul = :j, kategori = :k, jam_mulai = :jm, jam_selesai = :js, lokasi = :l, catatan = :c WHERE id = :id AND user_id = :uid")
                    ->execute([
                        'tg'  => $tanggal,
                        'j'   => mb_substr($judul, 0, 160),
                        'k'   => $kat,
                        'jm'  => $jMulai,
                        'js'  => $jSelesai,
                        'l'   => $lokasi !== '' ? mb_substr($lokasi, 0, 120) : null,
                        'c'   => $catatan !== '' ? $catatan : null,
                        'id'  => $sid,
                        'uid' => $userId,
                    ]);
                flash_set('success', 'Jadwal kerja berhasil diperbarui.');
            } else {
                $pdo->prepare("INSERT INTO personal_work_schedules (user_id, tanggal, judul, kategori, jam_mulai, jam_selesai, lokasi, catatan) VALUES (:uid, :tg, :j, :k, :jm, :js, :l, :c)")
                    ->execute([
                        'uid' => $userId,
                        'tg'  => $tanggal,
                        'j'   => mb_substr($judul, 0, 160),
                        'k'   => $kat,
                        'jm'  => $jMulai,
                        'js'  => $jSelesai,
                        'l'   => $lokasi !== '' ? mb_substr($lokasi, 0, 120) : null,
                        'c'   => $catatan !== '' ? $catatan : null,
                    ]);
                flash_set('success', 'Jadwal kerja berhasil ditambahkan.');
            }
        } else {
            flash_set('error', 'Judul jadwal dan tanggal wajib diisi.');
        }
    } elseif ($action === 'delete_schedule') {
        $sid = (int)($_POST['schedule_id'] ?? 0);
        if ($sid > 0) {
            $pdo->prepare("DELETE FROM personal_work_schedules WHERE id = :id AND user_id = :uid")
                ->execute(['id' => $sid, 'uid' => $userId]);
            flash_set('success', 'Jadwal kerja dihapus.');
        }
    } elseif ($action === 'save_note') {
        $nid   = (int)($_POST['note_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $isi   = trim($_POST['isi'] ?? '');
        $warna = in_array($_POST['warna'] ?? 'indigo', ['indigo', 'emerald', 'amber', 'rose'], true) ? $_POST['warna'] : 'indigo';
        if ($judul !== '' && $isi !== '') {
            if ($nid > 0) {
                $pdo->prepare("UPDATE personal_notes SET judul = :j, isi = :i, warna = :w WHERE id = :id AND user_id = :uid")
                    ->execute(['j' => mb_substr($judul, 0, 150), 'i' => $isi, 'w' => $warna, 'id' => $nid, 'uid' => $userId]);
                flash_set('success', 'Catatan pribadi diperbarui.');
            } else {
                $pdo->prepare("INSERT INTO personal_notes (user_id, judul, isi, warna) VALUES (:uid, :j, :i, :w)")
                    ->execute(['uid' => $userId, 'j' => mb_substr($judul, 0, 150), 'i' => $isi, 'w' => $warna]);
                flash_set('success', 'Catatan pribadi disimpan.');
            }
        }
    } elseif ($action === 'delete_note') {
        $nid = (int)($_POST['note_id'] ?? 0);
        if ($nid > 0) {
            $pdo->prepare("DELETE FROM personal_notes WHERE id = :id AND user_id = :uid")
                ->execute(['id' => $nid, 'uid' => $userId]);
            flash_set('success', 'Catatan pribadi dihapus.');
        }
    }

    $redirUrl = BASE_URL . 'kanban/index?month=' . urlencode($reqMonth) . '&date=' . urlencode($reqDate);
    header('Location: ' . $redirUrl);
    exit;
}

// Strictly fetch ONLY current user's tasks, schedules & notes
$stmtT = $pdo->prepare("SELECT * FROM personal_kanban_tasks WHERE user_id = :uid ORDER BY FIELD(prioritas,'Tinggi','Sedang','Rendah'), id DESC");
$stmtT->execute(['uid' => $userId]);
$allTasks = $stmtT->fetchAll();

$columns = ['Belum Mulai' => [], 'Berjalan' => [], 'Selesai' => []];
$tasksByDate = [];
foreach ($allTasks as $tk) {
    $columns[$tk['status']][] = $tk;
    if (!empty($tk['target_tanggal'])) {
        $tasksByDate[$tk['target_tanggal']][] = $tk;
    }
}

$stmtN = $pdo->prepare("SELECT * FROM personal_notes WHERE user_id = :uid ORDER BY updated_at DESC, id DESC");
$stmtN->execute(['uid' => $userId]);
$notes = $stmtN->fetchAll();

// Calendar calculations for $reqMonth
$monthStartTs   = strtotime($reqMonth . '-01');
$daysInMonth    = (int)date('t', $monthStartTs);
$firstWeekday   = (int)date('N', $monthStartTs); // 1 (Mon) .. 7 (Sun)
$monthEndStr    = $reqMonth . '-' . str_pad((string)$daysInMonth, 2, '0', STR_PAD_LEFT);
$prevMonthStr   = date('Y-m', strtotime('-1 month', $monthStartTs));
$nextMonthStr   = date('Y-m', strtotime('+1 month', $monthStartTs));

$indoMonths = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];
$indoDaysLong = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$monthLabel = $indoMonths[date('m', $monthStartTs)] . ' ' . date('Y', $monthStartTs);

// Fetch schedules for current month + next 7 days upcoming
$stmtS = $pdo->prepare("
    SELECT * FROM personal_work_schedules
    WHERE user_id = :uid AND tanggal BETWEEN :mstart AND :mend
    ORDER BY tanggal ASC, COALESCE(jam_mulai, '23:59:59') ASC, id ASC
");
$stmtS->execute(['uid' => $userId, 'mstart' => $reqMonth . '-01', 'mend' => $monthEndStr]);
$monthSchedules = $stmtS->fetchAll();

$schedulesByDate = [];
foreach ($monthSchedules as $sc) {
    $schedulesByDate[$sc['tanggal']][] = $sc;
}

$stmtUp = $pdo->prepare("
    SELECT * FROM personal_work_schedules
    WHERE user_id = :uid AND tanggal BETWEEN :t0 AND :t7
    ORDER BY tanggal ASC, COALESCE(jam_mulai, '23:59:59') ASC, id ASC
    LIMIT 6
");
$stmtUp->execute(['uid' => $userId, 't0' => $todayStr, 't7' => date('Y-m-d', strtotime('+7 days'))]);
$upcomingSchedules = $stmtUp->fetchAll();

// Also fetch schedules for selected date if outside viewed month
if (strpos($reqDate, $reqMonth) !== 0) {
    $stmtSel = $pdo->prepare("SELECT * FROM personal_work_schedules WHERE user_id = :uid AND tanggal = :tg ORDER BY COALESCE(jam_mulai, '23:59:59') ASC, id ASC");
    $stmtSel->execute(['uid' => $userId, 'tg' => $reqDate]);
    $selectedSchedules = $stmtSel->fetchAll();
} else {
    $selectedSchedules = $schedulesByDate[$reqDate] ?? [];
}
$selectedTasks = $tasksByDate[$reqDate] ?? [];
$selTs = strtotime($reqDate);
$selectedDateLabel = $indoDaysLong[(int)date('N', $selTs)] . ', ' . (int)date('d', $selTs) . ' ' . $indoMonths[date('m', $selTs)] . ' ' . date('Y', $selTs);

$activeTaskCount = count($columns['Belum Mulai']) + count($columns['Berjalan']);
$doneTaskCount   = count($columns['Selesai']);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .cal-grid-7 {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
  }
  .cal-day-cell {
    min-height: 98px;
    transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease;
  }
  .cal-day-cell:hover {
    border-color: #6558f5;
    box-shadow: 0 8px 20px -8px rgba(101, 88, 245, 0.25);
    z-index: 2;
  }
  .cal-day-cell .cal-quick-add {
    opacity: 0;
    transition: opacity 0.15s ease;
  }
  .cal-day-cell:hover .cal-quick-add {
    opacity: 1;
  }
  .cal-event-chip {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.35;
    border-width: 1px;
    border-style: solid;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
  @media (max-width: 639px) {
    .cal-day-cell {
      min-height: 64px;
      padding: 6px !important;
    }
    .cal-day-cell .cal-quick-add {
      display: none;
    }
  }
</style>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header & Quick Actions -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-lock-key"></i>
        <span>Ruang Kerja Privat (Hanya Anda yang Bisa Melihat)</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2">
        <i class="ph-fill ph-calendar-check text-[#6558f5]"></i>
        <span>Kalender Kerja, Kanban &amp; Catatan</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Atur jadwal shift kerja, pantau target tugas, dan simpan catatan pribadi untuk akun <strong><?= sanitize($user['nama_lengkap']) ?></strong>.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button type="button" onclick="openScheduleModal(null, '<?= sanitize($reqDate) ?>')"
              class="px-4 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-calendar-plus text-sm"></i>
        <span>Jadwal Kerja</span>
      </button>
      <button type="button" onclick="openTaskModal()"
              class="px-4 py-2.5 rounded-2xl bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-plus text-sm"></i>
        <span>Tugas Baru</span>
      </button>
      <button type="button" onclick="openNoteModal()"
              class="px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-note-pencil text-sm"></i>
        <span>Catatan</span>
      </button>
    </div>
  </div>

  <!-- Summary Metrics & View Filter -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 flex-1">
      <div class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center shrink-0">
          <i class="ph-fill ph-calendar-dots text-lg"></i>
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jadwal Bulan Ini</div>
          <div class="text-base font-extrabold text-[#181b2c] dark:text-white"><?= count($monthSchedules) ?></div>
        </div>
      </div>
      <div class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-300 flex items-center justify-center shrink-0">
          <i class="ph-fill ph-kanban text-lg"></i>
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tugas Aktif</div>
          <div class="text-base font-extrabold text-[#181b2c] dark:text-white"><?= $activeTaskCount ?></div>
        </div>
      </div>
      <div class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center shrink-0">
          <i class="ph-fill ph-check-circle text-lg"></i>
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tugas Selesai</div>
          <div class="text-base font-extrabold text-[#181b2c] dark:text-white"><?= $doneTaskCount ?></div>
        </div>
      </div>
      <div class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-300 flex items-center justify-center shrink-0">
          <i class="ph-fill ph-sticky-note text-lg"></i>
        </div>
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan</div>
          <div class="text-base font-extrabold text-[#181b2c] dark:text-white"><?= count($notes) ?></div>
        </div>
      </div>
    </div>

    <!-- Segmented View Switcher -->
    <div class="inline-flex items-center p-1 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 self-start md:self-auto" role="tablist">
      <button type="button" onclick="setWorkspaceTab('all')" data-tab="all" class="ws-tab px-3 py-1.5 rounded-xl text-xs font-extrabold transition cursor-pointer bg-[#6558f5] text-white">Semua</button>
      <button type="button" onclick="setWorkspaceTab('calendar')" data-tab="calendar" class="ws-tab px-3 py-1.5 rounded-xl text-xs font-extrabold transition cursor-pointer text-slate-500 dark:text-slate-400">Kalender</button>
      <button type="button" onclick="setWorkspaceTab('kanban')" data-tab="kanban" class="ws-tab px-3 py-1.5 rounded-xl text-xs font-extrabold transition cursor-pointer text-slate-500 dark:text-slate-400">Kanban</button>
      <button type="button" onclick="setWorkspaceTab('notes')" data-tab="notes" class="ws-tab px-3 py-1.5 rounded-xl text-xs font-extrabold transition cursor-pointer text-slate-500 dark:text-slate-400">Catatan</button>
    </div>
  </div>

  <!-- SECTION 1: KALENDER JADWAL KERJA + AGENDA PANEL -->
  <div id="sec-calendar" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

    <!-- Monthly Calendar Card (8 cols) -->
    <div class="lg:col-span-8 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-4 sm:p-6 space-y-4">
      <!-- Calendar Toolbar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center shrink-0">
            <i class="ph-fill ph-calendar-blank text-xl"></i>
          </div>
          <div>
            <h3 class="text-lg sm:text-xl font-extrabold text-[#181b2c] dark:text-white tracking-tight"><?= sanitize($monthLabel) ?></h3>
            <p class="text-[11px] text-slate-400">Klik tanggal untuk melihat agenda atau tambah jadwal kerja</p>
          </div>
        </div>

        <div class="flex items-center gap-1.5">
          <a href="<?= BASE_URL ?>kanban/index?month=<?= urlencode($prevMonthStr) ?>&date=<?= urlencode($prevMonthStr . '-01') ?>"
             class="w-9 h-9 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:border-[#6558f5] hover:text-[#6558f5] transition"
             title="Bulan Sebelumnya">
            <i class="ph-bold ph-caret-left"></i>
          </a>
          <a href="<?= BASE_URL ?>kanban/index?month=<?= urlencode(date('Y-m')) ?>&date=<?= urlencode($todayStr) ?>"
             class="px-3.5 h-9 rounded-xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-extrabold flex items-center justify-center hover:bg-[#6558f5] hover:text-white transition">
            Hari Ini
          </a>
          <a href="<?= BASE_URL ?>kanban/index?month=<?= urlencode($nextMonthStr) ?>&date=<?= urlencode($nextMonthStr . '-01') ?>"
             class="w-9 h-9 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:border-[#6558f5] hover:text-[#6558f5] transition"
             title="Bulan Berikutnya">
            <i class="ph-bold ph-caret-right"></i>
          </a>
        </div>
      </div>

      <!-- Day of Week Headers (Mon - Sun) -->
      <div class="cal-grid-7 gap-1.5 sm:gap-2 text-center">
        <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $idx => $dName): ?>
          <div class="py-1.5 text-[11px] font-extrabold uppercase tracking-wider <?= $idx >= 5 ? 'text-rose-500/80 dark:text-rose-400/80' : 'text-slate-400 dark:text-slate-500' ?>">
            <?= $dName ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Calendar Days Grid -->
      <div class="cal-grid-7 gap-1.5 sm:gap-2">
        <?php
          // Leading empty cells before the 1st of month
          for ($blank = 1; $blank < $firstWeekday; $blank++):
        ?>
          <div class="cal-day-cell rounded-2xl bg-slate-50/50 dark:bg-[#151828]/40 border border-dashed border-slate-200/50 dark:border-slate-800/50 p-2 opacity-50"></div>
        <?php endfor; ?>

        <?php
          for ($day = 1; $day <= $daysInMonth; $day++):
            $cellDate    = $reqMonth . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
            $weekdayNum  = (int)date('N', strtotime($cellDate));
            $isWeekend   = $weekdayNum >= 6;
            $isToday     = ($cellDate === $todayStr);
            $isSelected  = ($cellDate === $reqDate);
            $dayEvents   = $schedulesByDate[$cellDate] ?? [];
            $dayTasks    = $tasksByDate[$cellDate] ?? [];

            $cellClasses = 'cal-day-cell relative rounded-2xl border p-2 flex flex-col justify-between cursor-pointer ';
            if ($isSelected) {
                $cellClasses .= 'bg-[#efeffb]/75 dark:bg-indigo-950/40 border-[#6558f5] ring-2 ring-[#6558f5]/20 ';
            } elseif ($isToday) {
                $cellClasses .= 'bg-white dark:bg-[#1b1e30] border-[#6558f5]/60 ';
            } elseif ($isWeekend) {
                $cellClasses .= 'bg-slate-50/90 dark:bg-[#151828]/80 border-slate-100 dark:border-slate-800/80 ';
            } else {
                $cellClasses .= 'bg-[#f9f9fd] dark:bg-[#151828] border-slate-100 dark:border-slate-800/80 ';
            }
        ?>
          <div class="<?= $cellClasses ?>"
               onclick="window.location.href='<?= BASE_URL ?>kanban/index?month=<?= urlencode($reqMonth) ?>&date=<?= urlencode($cellDate) ?>'">
            <div class="flex items-center justify-between gap-1">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-xs font-extrabold <?= $isToday ? 'bg-[#6558f5] text-white shadow-sm' : ($isSelected ? 'text-[#6558f5] dark:text-indigo-300' : ($isWeekend ? 'text-rose-500 dark:text-rose-400' : 'text-slate-700 dark:text-slate-200')) ?>">
                <?= $day ?>
              </span>

              <button type="button"
                      onclick="event.stopPropagation(); openScheduleModal(null, '<?= $cellDate ?>')"
                      class="cal-quick-add w-5 h-5 rounded-md bg-[#6558f5] text-white flex items-center justify-center text-[11px] hover:bg-[#5244e8] cursor-pointer"
                      title="Tambah jadwal pada <?= $cellDate ?>">
                <i class="ph-bold ph-plus"></i>
              </button>
            </div>

            <!-- Desktop: Chips | Mobile: Compact Dots -->
            <div class="mt-1.5 space-y-1">
              <?php
                $shown = 0;
                foreach ($dayEvents as $ev):
                  if ($shown >= 2) break;
                  $shown++;
                  $meta = $scheduleCategories[$ev['kategori']] ?? $scheduleCategories['Lainnya'];
                  $timeShort = !empty($ev['jam_mulai']) ? substr($ev['jam_mulai'], 0, 5) . ' ' : '';
              ?>
                <div class="hidden sm:flex cal-event-chip"
                     style="background-color: <?= $meta['bg'] ?>; color: <?= $meta['text'] ?>; border-color: <?= $meta['border'] ?>;"
                     title="<?= sanitize($ev['kategori'] . ': ' . $ev['judul']) ?>">
                  <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: <?= $meta['dot'] ?>;"></span>
                  <span class="truncate"><?= sanitize($timeShort . $ev['judul']) ?></span>
                </div>
              <?php endforeach; ?>

              <?php if ($shown < 2 && !empty($dayTasks)): ?>
                <?php foreach ($dayTasks as $dt): ?>
                  <?php if ($shown >= 2) break; $shown++; ?>
                  <div class="hidden sm:flex cal-event-chip"
                       style="background-color: #f8fafc; color: #334155; border-color: #cbd5e1;"
                       title="Target Tugas: <?= sanitize($dt['judul']) ?>">
                    <i class="ph-bold ph-check-square text-[10px] shrink-0 text-[#6558f5]"></i>
                    <span class="truncate"><?= sanitize($dt['judul']) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <?php $totalItems = count($dayEvents) + count($dayTasks); ?>
              <?php if ($totalItems > 2): ?>
                <div class="hidden sm:block text-[9.5px] font-extrabold text-[#6558f5] dark:text-indigo-300 pl-1">
                  +<?= $totalItems - 2 ?> lainnya
                </div>
              <?php endif; ?>

              <!-- Mobile Dots -->
              <?php if ($totalItems > 0): ?>
                <div class="flex sm:hidden flex-wrap items-center gap-1 pt-0.5">
                  <?php foreach (array_slice($dayEvents, 0, 3) as $ev): $meta = $scheduleCategories[$ev['kategori']] ?? $scheduleCategories['Lainnya']; ?>
                    <span class="w-2 h-2 rounded-full" style="background-color: <?= $meta['dot'] ?>;"></span>
                  <?php endforeach; ?>
                  <?php if (!empty($dayTasks)): ?>
                    <span class="w-2 h-2 rounded-sm bg-[#6558f5]"></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Category Legend -->
      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-2">
        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mr-1">Legenda:</span>
        <?php foreach ($scheduleCategories as $catName => $meta): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-bold border"
                style="background-color: <?= $meta['bg'] ?>; color: <?= $meta['text'] ?>; border-color: <?= $meta['border'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $meta['dot'] ?>;"></span>
            <span><?= sanitize($catName) ?></span>
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Selected Day Agenda & Upcoming Panel (4 cols) -->
    <div class="lg:col-span-4 space-y-5">
      <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 space-y-4">
        <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#6558f5] dark:text-indigo-300">Agenda Tanggal</span>
            <h3 class="text-base font-extrabold text-[#181b2c] dark:text-white mt-0.5"><?= sanitize($selectedDateLabel) ?></h3>
          </div>
          <button type="button" onclick="openScheduleModal(null, '<?= sanitize($reqDate) ?>')"
                  class="px-3 py-1.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-[11px] font-extrabold shadow-sm transition flex items-center gap-1 shrink-0 cursor-pointer">
            <i class="ph-bold ph-plus"></i>
            <span>Jadwal</span>
          </button>
        </div>

        <?php if (empty($selectedSchedules) && empty($selectedTasks)): ?>
          <div class="p-6 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-dashed border-slate-200 dark:border-slate-800 text-center space-y-2">
            <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/50 text-[#6558f5] mx-auto flex items-center justify-center">
              <i class="ph-fill ph-calendar-plus text-xl"></i>
            </div>
            <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Belum ada jadwal di tanggal ini</p>
            <p class="text-[11px] text-slate-400">Klik tombol <strong>+ Jadwal</strong> untuk menambahkan shift atau agenda kerja.</p>
          </div>
        <?php else: ?>
          <div class="space-y-2.5">
            <?php foreach ($selectedSchedules as $sc): ?>
              <?php $meta = $scheduleCategories[$sc['kategori']] ?? $scheduleCategories['Lainnya']; ?>
              <div class="p-3.5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between gap-2">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border"
                        style="background-color: <?= $meta['bg'] ?>; color: <?= $meta['text'] ?>; border-color: <?= $meta['border'] ?>;">
                    <i class="ph-bold <?= $meta['icon'] ?>"></i>
                    <span><?= sanitize($sc['kategori']) ?></span>
                  </span>
                  <div class="flex items-center gap-1">
                    <button type="button"
                            onclick='openScheduleModal(<?= json_encode($sc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            class="p-1 text-slate-400 hover:text-[#6558f5] cursor-pointer" title="Edit Jadwal">
                      <i class="ph-bold ph-pencil-simple text-xs"></i>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Hapus jadwal kerja ini?')">
                      <input type="hidden" name="action" value="delete_schedule" />
                      <input type="hidden" name="schedule_id" value="<?= $sc['id'] ?>" />
                      <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
                      <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
                      <button type="submit" class="p-1 text-slate-400 hover:text-rose-500 cursor-pointer" title="Hapus Jadwal">
                        <i class="ph-bold ph-trash text-xs"></i>
                      </button>
                    </form>
                  </div>
                </div>

                <h4 class="text-xs font-extrabold text-[#181b2c] dark:text-white"><?= sanitize($sc['judul']) ?></h4>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                  <?php if (!empty($sc['jam_mulai'])): ?>
                    <span class="inline-flex items-center gap-1 font-mono font-semibold">
                      <i class="ph-bold ph-clock text-[#6558f5]"></i>
                      <?= substr($sc['jam_mulai'], 0, 5) ?><?= !empty($sc['jam_selesai']) ? ' - ' . substr($sc['jam_selesai'], 0, 5) : '' ?> WIB
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($sc['lokasi'])): ?>
                    <span class="inline-flex items-center gap-1">
                      <i class="ph-bold ph-map-pin text-emerald-500"></i>
                      <?= sanitize($sc['lokasi']) ?>
                    </span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($sc['catatan'])): ?>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 whitespace-pre-line pt-1 border-t border-slate-200/70 dark:border-slate-800">
                    <?= sanitize($sc['catatan']) ?>
                  </p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <?php if (!empty($selectedTasks)): ?>
              <div class="pt-2">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Target Tugas Kanban</div>
                <div class="space-y-2">
                  <?php foreach ($selectedTasks as $st): ?>
                    <div class="p-3 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200/70 dark:border-indigo-800/50 flex items-center justify-between gap-2">
                      <div class="min-w-0">
                        <div class="text-xs font-extrabold text-[#181b2c] dark:text-white truncate"><?= sanitize($st['judul']) ?></div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Status: <strong><?= sanitize($st['status']) ?></strong> · Prioritas <?= sanitize($st['prioritas']) ?></div>
                      </div>
                      <span class="px-2 py-0.5 rounded-md bg-white dark:bg-[#1b1e30] text-[10px] font-extrabold text-[#6558f5] shrink-0">Tugas</span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Upcoming 7 Days Card -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
            <i class="ph-fill ph-sparkle text-[#6558f5]"></i>
            <span>Jadwal 7 Hari Ke Depan</span>
          </h4>
          <span class="text-[10px] font-bold text-slate-400"><?= count($upcomingSchedules) ?> agenda</span>
        </div>

        <?php if (empty($upcomingSchedules)): ?>
          <p class="text-[11px] text-slate-400 italic py-2">Belum ada jadwal kerja dalam 7 hari ke depan.</p>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($upcomingSchedules as $up): ?>
              <?php $meta = $scheduleCategories[$up['kategori']] ?? $scheduleCategories['Lainnya']; ?>
              <a href="<?= BASE_URL ?>kanban/index?month=<?= urlencode(substr($up['tanggal'], 0, 7)) ?>&date=<?= urlencode($up['tanggal']) ?>"
                 class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] hover:border-[#6558f5]/40 border border-transparent transition">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: <?= $meta['dot'] ?>;"></span>
                  <div class="min-w-0">
                    <div class="text-xs font-bold text-[#181b2c] dark:text-white truncate"><?= sanitize($up['judul']) ?></div>
                    <div class="text-[10px] text-slate-400">
                      <?= format_tanggal($up['tanggal']) ?>
                      <?= !empty($up['jam_mulai']) ? ' · ' . substr($up['jam_mulai'], 0, 5) : '' ?>
                    </div>
                  </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0"
                      style="background-color: <?= $meta['bg'] ?>; color: <?= $meta['text'] ?>;">
                  <?= sanitize($up['kategori']) ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SECTION 2: 3-Column Personal Kanban Board -->
  <div id="sec-kanban" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?php
      $colMeta = [
        'Belum Mulai' => ['dot' => 'bg-slate-400',   'badge' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
        'Berjalan'    => ['dot' => 'bg-blue-500',    'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'],
        'Selesai'     => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'],
      ];
      foreach ($columns as $colName => $colItems):
        $m = $colMeta[$colName];
    ?>
      <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-4 sm:p-5 flex flex-col gap-3">
        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full <?= $m['dot'] ?>"></span>
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200"><?= $colName ?></h3>
          </div>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold <?= $m['badge'] ?>"><?= count($colItems) ?></span>
        </div>

        <div class="space-y-3">
          <?php if (empty($colItems)): ?>
            <p class="py-6 text-center text-[11px] text-slate-400 italic">Belum ada tugas di kolom ini.</p>
          <?php else: ?>
            <?php foreach ($colItems as $item): ?>
              <?php
                $prioCls = $item['prioritas'] === 'Tinggi'
                    ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-300'
                    : ($item['prioritas'] === 'Sedang'
                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400');
              ?>
              <div class="p-3.5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold <?= $prioCls ?>"><?= sanitize($item['prioritas']) ?></span>
                  <div class="flex items-center gap-1">
                    <button type="button"
                            onclick='openTaskModal(<?= json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            class="text-slate-400 hover:text-[#6558f5] p-0.5 cursor-pointer" title="Edit Tugas">
                      <i class="ph-bold ph-pencil-simple text-xs"></i>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Hapus tugas ini?')">
                      <input type="hidden" name="action" value="delete_task" />
                      <input type="hidden" name="task_id" value="<?= $item['id'] ?>" />
                      <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
                      <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
                      <button type="submit" class="text-slate-400 hover:text-rose-500 p-0.5 cursor-pointer" title="Hapus">
                        <i class="ph-bold ph-trash text-xs"></i>
                      </button>
                    </form>
                  </div>
                </div>

                <h4 class="text-xs font-extrabold text-[#181b2c] dark:text-white leading-snug"><?= sanitize($item['judul']) ?></h4>
                <?php if (!empty($item['deskripsi'])): ?>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 whitespace-pre-line"><?= sanitize($item['deskripsi']) ?></p>
                <?php endif; ?>

                <div class="pt-2 border-t border-slate-200/70 dark:border-slate-800 flex items-center justify-between gap-2">
                  <span class="text-[10px] text-slate-400 font-mono">
                    <?= !empty($item['target_tanggal']) ? format_tanggal($item['target_tanggal']) : 'Tanpa target' ?>
                  </span>
                  <form method="POST" action="">
                    <input type="hidden" name="action" value="move_task" />
                    <input type="hidden" name="task_id" value="<?= $item['id'] ?>" />
                    <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
                    <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
                    <select name="new_status" onchange="this.form.submit()"
                            class="px-2 py-1 rounded-lg bg-white dark:bg-[#1b1e30] border border-slate-200 dark:border-slate-700 text-[10.5px] font-bold cursor-pointer">
                      <?php foreach (['Belum Mulai', 'Berjalan', 'Selesai'] as $stOpt): ?>
                        <option value="<?= $stOpt ?>" <?= $item['status'] === $stOpt ? 'selected' : '' ?>><?= $stOpt ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- SECTION 3: Personal Sticky Notes -->
  <div id="sec-notes" class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 sm:p-7 space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-base font-extrabold text-[#181b2c] dark:text-white flex items-center gap-2">
          <i class="ph-fill ph-sticky-note text-amber-500 text-xl"></i>
          <span>Catatan Pribadi (<?= count($notes) ?>)</span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Simpan pengingat cepat, perintah teknis, atau catatan kerja pribadi Anda.</p>
      </div>
    </div>

    <?php if (empty($notes)): ?>
      <div class="p-8 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] text-center text-xs text-slate-400">
        Belum ada catatan pribadi. Klik <strong>Catatan</strong> di kanan atas untuk membuat.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php
          $noteThemes = [
            'indigo'  => 'bg-indigo-50/70 dark:bg-indigo-950/30 border-indigo-200/80 dark:border-indigo-800/60',
            'emerald' => 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-200/80 dark:border-emerald-800/60',
            'amber'   => 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-200/80 dark:border-amber-800/60',
            'rose'    => 'bg-rose-50/70 dark:bg-rose-950/30 border-rose-200/80 dark:border-rose-800/60',
          ];
          foreach ($notes as $nt):
            $cls = $noteThemes[$nt['warna']] ?? $noteThemes['indigo'];
        ?>
          <div class="p-4 rounded-2xl border <?= $cls ?> flex flex-col justify-between gap-3">
            <div>
              <div class="flex items-start justify-between gap-2 mb-1.5">
                <h4 class="text-xs font-extrabold text-slate-900 dark:text-white"><?= sanitize($nt['judul']) ?></h4>
                <div class="flex items-center gap-1 shrink-0">
                  <button type="button" onclick='openNoteModal(<?= json_encode($nt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          class="p-1 text-slate-500 hover:text-[#6558f5] cursor-pointer" title="Edit">
                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                  </button>
                  <form method="POST" action="" onsubmit="return confirm('Hapus catatan ini?')">
                    <input type="hidden" name="action" value="delete_note" />
                    <input type="hidden" name="note_id" value="<?= $nt['id'] ?>" />
                    <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
                    <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
                    <button type="submit" class="p-1 text-slate-500 hover:text-rose-500 cursor-pointer" title="Hapus">
                      <i class="ph-bold ph-trash text-xs"></i>
                    </button>
                  </form>
                </div>
              </div>
              <p class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed"><?= sanitize($nt['isi']) ?></p>
            </div>
            <span class="text-[10px] font-mono text-slate-400">Diperbarui <?= date('d/m/Y H:i', strtotime($nt['updated_at'])) ?> WIB</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Tambah / Edit Jadwal Kerja -->
<div id="scheduleModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('scheduleModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[26px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2" id="scheduleModalTitle">
        <i class="ph-fill ph-calendar-plus text-[#6558f5] text-lg"></i>
        <span>Tambah Jadwal Kerja</span>
      </h3>
      <button type="button" onclick="document.getElementById('scheduleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
        <i class="ph-bold ph-x"></i>
      </button>
    </div>

    <!-- Quick Preset Chips -->
    <div class="flex flex-wrap gap-1.5">
      <button type="button" onclick="applySchedulePreset('Shift Pagi', 'Shift Pagi', '07:00', '15:00')" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-[10.5px] font-bold hover:bg-emerald-100 cursor-pointer">Shift Pagi</button>
      <button type="button" onclick="applySchedulePreset('Shift Siang', 'Shift Siang', '15:00', '23:00')" class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-[10.5px] font-bold hover:bg-amber-100 cursor-pointer">Shift Siang</button>
      <button type="button" onclick="applySchedulePreset('Shift Malam', 'Shift Malam', '23:00', '07:00')" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[10.5px] font-bold hover:bg-indigo-100 cursor-pointer">Shift Malam</button>
      <button type="button" onclick="applySchedulePreset('Maintenance Terjadwal', 'Maintenance', '09:00', '12:00')" class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-[10.5px] font-bold hover:bg-blue-100 cursor-pointer">Maintenance</button>
      <button type="button" onclick="applySchedulePreset('Lembur Operasional', 'Lembur', '17:00', '21:00')" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-[10.5px] font-bold hover:bg-rose-100 cursor-pointer">Lembur</button>
    </div>

    <form method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="action" value="save_schedule" />
      <input type="hidden" name="schedule_id" id="sch_id" value="0" />
      <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
      <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />

      <div>
        <label class="block font-bold mb-1">Judul Jadwal</label>
        <input type="text" name="judul" id="sch_judul" required placeholder="Contoh: Shift Pagi - Ruang Server" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold mb-1">Tanggal</label>
          <input type="date" name="tanggal" id="sch_tanggal" required value="<?= sanitize($reqDate) ?>" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
        </div>
        <div>
          <label class="block font-bold mb-1">Kategori</label>
          <select name="kategori" id="sch_kategori" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
            <?php foreach (array_keys($scheduleCategories) as $catOpt): ?>
              <option value="<?= sanitize($catOpt) ?>"><?= sanitize($catOpt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold mb-1">Jam Mulai</label>
          <input type="time" name="jam_mulai" id="sch_jam_mulai" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
        </div>
        <div>
          <label class="block font-bold mb-1">Jam Selesai</label>
          <input type="time" name="jam_selesai" id="sch_jam_selesai" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
        </div>
      </div>

      <div>
        <label class="block font-bold mb-1">Lokasi / Pos (Opsional)</label>
        <input type="text" name="lokasi" id="sch_lokasi" placeholder="Contoh: Ruang NOC / Site A" class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>

      <div>
        <label class="block font-bold mb-1">Catatan (Opsional)</label>
        <textarea name="catatan" id="sch_catatan" rows="2" placeholder="Detail pekerjaan atau serah terima..." class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></textarea>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('scheduleModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold cursor-pointer">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold cursor-pointer">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah / Edit Tugas Pribadi -->
<div id="addTaskModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('addTaskModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[26px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" id="taskModalTitle">Tambah Tugas Kanban Pribadi</h3>
    <form method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="action" value="save_task" />
      <input type="hidden" name="task_id" id="task_id" value="0" />
      <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
      <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
      <div>
        <label class="block font-bold mb-1">Judul Tugas</label>
        <input type="text" name="judul" id="task_judul" required placeholder="Contoh: Cek ulang patch server NVR" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>
      <div>
        <label class="block font-bold mb-1">Deskripsi (Opsional)</label>
        <textarea name="deskripsi" id="task_deskripsi" rows="2" class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></textarea>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <div>
          <label class="block font-bold mb-1">Prioritas</label>
          <select name="prioritas" id="task_prioritas" class="w-full px-2.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
            <option value="Tinggi">Tinggi</option>
            <option value="Sedang" selected>Sedang</option>
            <option value="Rendah">Rendah</option>
          </select>
        </div>
        <div>
          <label class="block font-bold mb-1">Status</label>
          <select name="status" id="task_status" class="w-full px-2.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
            <option value="Belum Mulai" selected>Belum Mulai</option>
            <option value="Berjalan">Berjalan</option>
            <option value="Selesai">Selesai</option>
          </select>
        </div>
        <div>
          <label class="block font-bold mb-1">Target</label>
          <input type="date" name="target_tanggal" id="task_target" class="w-full px-2.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('addTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold cursor-pointer">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] text-white font-extrabold cursor-pointer">Simpan Tugas</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Catatan Pribadi -->
<div id="noteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('noteModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[26px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" id="noteModalTitle">Tambah Catatan Pribadi</h3>
    <form method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="action" value="save_note" />
      <input type="hidden" name="note_id" id="note_id" value="0" />
      <input type="hidden" name="redirect_month" value="<?= sanitize($reqMonth) ?>" />
      <input type="hidden" name="redirect_date" value="<?= sanitize($reqDate) ?>" />
      <div>
        <label class="block font-bold mb-1">Judul Catatan</label>
        <input type="text" name="judul" id="note_judul" required placeholder="Contoh: Perintah Restart Service" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>
      <div>
        <label class="block font-bold mb-1">Isi Catatan</label>
        <textarea name="isi" id="note_isi" rows="4" required class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></textarea>
      </div>
      <div>
        <label class="block font-bold mb-1">Warna Kartu</label>
        <select name="warna" id="note_warna" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
          <option value="indigo">Ungu (Indigo)</option>
          <option value="emerald">Hijau (Emerald)</option>
          <option value="amber">Kuning (Amber)</option>
          <option value="rose">Merah (Rose)</option>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('noteModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold cursor-pointer">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-extrabold cursor-pointer">Simpan Catatan</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openScheduleModal(sc, defaultDate) {
    document.getElementById('sch_id').value = sc ? sc.id : 0;
    document.getElementById('sch_judul').value = sc ? sc.judul : '';
    document.getElementById('sch_tanggal').value = sc ? sc.tanggal : (defaultDate || '<?= sanitize($reqDate) ?>');
    document.getElementById('sch_kategori').value = sc ? sc.kategori : 'Shift Pagi';
    document.getElementById('sch_jam_mulai').value = sc && sc.jam_mulai ? sc.jam_mulai.slice(0, 5) : '';
    document.getElementById('sch_jam_selesai').value = sc && sc.jam_selesai ? sc.jam_selesai.slice(0, 5) : '';
    document.getElementById('sch_lokasi').value = sc && sc.lokasi ? sc.lokasi : '';
    document.getElementById('sch_catatan').value = sc && sc.catatan ? sc.catatan : '';
    document.getElementById('scheduleModalTitle').innerHTML = sc
      ? '<i class="ph-fill ph-pencil-simple text-[#6558f5] text-lg"></i><span>Edit Jadwal Kerja</span>'
      : '<i class="ph-fill ph-calendar-plus text-[#6558f5] text-lg"></i><span>Tambah Jadwal Kerja</span>';
    document.getElementById('scheduleModal').classList.remove('hidden');
  }

  function applySchedulePreset(judul, kategori, mulai, selesai) {
    document.getElementById('sch_judul').value = judul;
    document.getElementById('sch_kategori').value = kategori;
    document.getElementById('sch_jam_mulai').value = mulai;
    document.getElementById('sch_jam_selesai').value = selesai;
  }

  function openTaskModal(tk) {
    document.getElementById('task_id').value = tk ? tk.id : 0;
    document.getElementById('task_judul').value = tk ? tk.judul : '';
    document.getElementById('task_deskripsi').value = tk && tk.deskripsi ? tk.deskripsi : '';
    document.getElementById('task_prioritas').value = tk ? tk.prioritas : 'Sedang';
    document.getElementById('task_status').value = tk ? tk.status : 'Belum Mulai';
    document.getElementById('task_target').value = tk && tk.target_tanggal ? tk.target_tanggal : '';
    document.getElementById('taskModalTitle').textContent = tk ? 'Edit Tugas Kanban Pribadi' : 'Tambah Tugas Kanban Pribadi';
    document.getElementById('addTaskModal').classList.remove('hidden');
  }

  function openNoteModal(nt) {
    document.getElementById('note_id').value = nt ? nt.id : 0;
    document.getElementById('note_judul').value = nt ? nt.judul : '';
    document.getElementById('note_isi').value = nt ? nt.isi : '';
    document.getElementById('note_warna').value = nt ? nt.warna : 'indigo';
    document.getElementById('noteModalTitle').textContent = nt ? 'Edit Catatan Pribadi' : 'Tambah Catatan Pribadi';
    document.getElementById('noteModal').classList.remove('hidden');
  }

  function setWorkspaceTab(tab) {
    var secCal   = document.getElementById('sec-calendar');
    var secKan   = document.getElementById('sec-kanban');
    var secNotes = document.getElementById('sec-notes');
    secCal.classList.toggle('hidden',   tab !== 'all' && tab !== 'calendar');
    secKan.classList.toggle('hidden',   tab !== 'all' && tab !== 'kanban');
    secNotes.classList.toggle('hidden', tab !== 'all' && tab !== 'notes');

    document.querySelectorAll('.ws-tab').forEach(function (btn) {
      var active = btn.getAttribute('data-tab') === tab;
      btn.classList.toggle('bg-[#6558f5]', active);
      btn.classList.toggle('text-white', active);
      btn.classList.toggle('text-slate-500', !active);
      btn.classList.toggle('dark:text-slate-400', !active);
    });
    try { localStorage.setItem('kanban_ws_tab', tab); } catch (e) {}
  }

  (function () {
    try {
      var saved = localStorage.getItem('kanban_ws_tab');
      if (saved && ['all', 'calendar', 'kanban', 'notes'].indexOf(saved) !== -1) {
        setWorkspaceTab(saved);
      }
    } catch (e) {}
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
