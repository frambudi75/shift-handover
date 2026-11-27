<?php
// laporan/share.php - Tautan Publik Baca-Saja Khusus 1 Laporan (Tanpa Login, Dilindungi Token HMAC)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id    = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

$report = null;
$tasks  = [];

if ($id > 0 && $token !== '') {
    $stmt = $pdo->prepare("
        SELECT l.*, s.nama_shift, s.jam_mulai, s.jam_selesai, m.nama_lokasi,
               u1.nama_lengkap AS nama_menyerahkan, u1.jabatan AS jabatan_menyerahkan,
               u2.nama_lengkap AS nama_menerima, u2.jabatan AS jabatan_menerima
        FROM laporan_serah_terima l
        JOIN master_shift s ON l.shift_id = s.id
        JOIN master_lokasi m ON l.lokasi_id = m.id
        JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
        LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
        WHERE l.id = :id AND l.status_validasi != 'DRAFT'
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $candidate = $stmt->fetch();

    if ($candidate && hash_equals(get_report_share_token((int)$candidate['id'], $candidate['nomor_laporan']), $token)) {
        $report = $candidate;
        $stmtTasks = $pdo->prepare("SELECT * FROM laporan_pekerjaan WHERE laporan_id = :id ORDER BY urutan ASC, id ASC");
        $stmtTasks->execute(['id' => $id]);
        $tasks = $stmtTasks->fetchAll();
    }
}

$appName     = get_app_name();
$appFullName = get_app_full_name();
$appLogo     = get_app_logo_url();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $report ? sanitize($report['nomor_laporan']) . ' — ' : '' ?><?= sanitize($appName) ?></title>
  <script>
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '281' ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/regular/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/bold/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/fill/style.css" />
</head>
<body class="min-h-screen bg-[#f5f5fa] dark:bg-[#121421] text-slate-800 dark:text-slate-100 font-sans py-6 px-4 sm:px-6">

  <div class="max-w-4xl mx-auto space-y-5">

    <!-- Top Brand Bar -->
    <div class="flex items-center justify-between gap-3 bg-white dark:bg-[#1b1e30] rounded-2xl px-5 py-3.5 border border-slate-200/80 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-[#6558f5] text-white flex items-center justify-center font-bold overflow-hidden p-1">
          <?php if ($appLogo): ?>
            <img src="<?= $appLogo ?>" alt="Logo" class="max-w-full max-h-full object-contain" />
          <?php else: ?>
            <i class="ph-fill ph-clipboard-text text-lg"></i>
          <?php endif; ?>
        </div>
        <div>
          <h1 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight"><?= sanitize($appName) ?></h1>
          <p class="text-[11px] text-slate-400">Tautan Laporan Serah Terima (Baca Saja)</p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>auth/login"
         class="px-4 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-extrabold transition flex items-center gap-1.5">
        <i class="ph-bold ph-sign-in"></i>
        <span>Masuk Sistem</span>
      </a>
    </div>

    <?php if (!$report): ?>
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200 dark:border-slate-800 p-10 text-center shadow-sm">
        <div class="w-16 h-16 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 mx-auto flex items-center justify-center text-3xl mb-4">
          <i class="ph-bold ph-lock-key"></i>
        </div>
        <h2 class="text-lg font-black text-slate-900 dark:text-white">Tautan Tidak Valid atau Akses Ditolak</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 max-w-md mx-auto">
          Pastikan Anda membuka tautan lengkap yang dibagikan dari sistem atau silakan masuk menggunakan akun Anda.
        </p>
      </div>
    <?php else: ?>

      <!-- Report Header -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div>
            <div class="flex items-center gap-2.5 flex-wrap">
              <span class="font-mono text-lg sm:text-xl font-black text-slate-900 dark:text-white"><?= sanitize($report['nomor_laporan']) ?></span>
              <?= badge_status_validasi($report['status_validasi']) ?>
            </div>
            <p class="text-xs text-slate-400 mt-1">
              Dibuat <?= date('d M Y, H:i', strtotime($report['created_at'])) ?> WIB oleh <strong><?= sanitize($report['nama_menyerahkan']) ?></strong>
            </p>
          </div>
          <div class="text-xs font-bold px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 self-start sm:self-auto">
            Kondisi: <?= sanitize($report['kondisi_operasional']) ?>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Tanggal</span>
            <strong class="text-slate-800 dark:text-white"><?= format_tanggal($report['tanggal']) ?></strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Shift &amp; Jam</span>
            <strong class="text-slate-800 dark:text-white"><?= sanitize($report['nama_shift']) ?> (<?= format_jam($report['jam_serah_terima']) ?> WIB)</strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Lokasi / Unit</span>
            <strong class="text-slate-800 dark:text-white"><?= sanitize($report['nama_lokasi']) ?></strong>
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Petugas Penerima</span>
            <strong class="text-slate-800 dark:text-white"><?= sanitize($report['nama_menerima'] ?: 'Menunggu Penerima') ?></strong>
          </div>
        </div>

        <?php if (!empty($report['catatan_penting'])): ?>
          <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-xs">
            <span class="font-extrabold text-amber-800 dark:text-amber-300 block mb-1">Catatan Penting Shift:</span>
            <p class="text-slate-700 dark:text-slate-200 whitespace-pre-line"><?= sanitize($report['catatan_penting']) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Tasks Table -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm">
        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white mb-4">Rincian Pekerjaan &amp; Tindak Lanjut (<?= count($tasks) ?>)</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs min-w-[620px]">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase text-[10px] font-extrabold">
              <tr>
                <th class="py-2.5 px-3">Tiket</th>
                <th class="py-2.5 px-3">Pekerjaan / Masalah</th>
                <th class="py-2.5 px-3">Prioritas</th>
                <th class="py-2.5 px-3">Status</th>
                <th class="py-2.5 px-3">Tindak Lanjut</th>
                <th class="py-2.5 px-3">PIC</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php if (empty($tasks)): ?>
                <tr><td colspan="6" class="py-6 text-center text-slate-400">Tidak ada rincian pekerjaan.</td></tr>
              <?php else: ?>
                <?php foreach ($tasks as $t): ?>
                  <tr>
                    <td class="py-2.5 px-3 font-mono font-bold text-[#6558f5]"><?= sanitize($t['no_tiket'] ?: '-') ?></td>
                    <td class="py-2.5 px-3 font-semibold text-slate-800 dark:text-slate-100"><?= sanitize($t['pekerjaan_masalah']) ?></td>
                    <td class="py-2.5 px-3"><?= badge_prioritas($t['prioritas']) ?></td>
                    <td class="py-2.5 px-3 font-bold"><?= sanitize($t['status']) ?></td>
                    <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300"><?= sanitize($t['kondisi_tindak_lanjut'] ?: '-') ?></td>
                    <td class="py-2.5 px-3"><?= sanitize($t['pic'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Photos (if any) -->
      <?php
        $photos = [];
        for ($p = 1; $p <= 3; $p++) {
            if (!empty($report["foto_{$p}"])) {
                $photos[] = ['url' => UPLOAD_URL . $report["foto_{$p}"], 'ket' => $report["ket_foto_{$p}"] ?? "Foto {$p}"];
            }
        }
      ?>
      <?php if (!empty($photos)): ?>
        <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm">
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-white mb-4">Dokumentasi Foto Lapangan</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <?php foreach ($photos as $ph): ?>
              <a href="<?= htmlspecialchars($ph['url']) ?>" target="_blank" class="block rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                <img src="<?= htmlspecialchars($ph['url']) ?>" alt="Dokumentasi" class="w-full h-44 object-cover" />
                <?php if ($ph['ket'] !== ''): ?>
                  <p class="p-2.5 text-[11px] font-semibold text-slate-600 dark:text-slate-300"><?= sanitize($ph['ket']) ?></p>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Signatures -->
      <div class="bg-white dark:bg-[#1b1e30] rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-6 text-center text-xs">
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50">
          <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Diserahkan Oleh (Shift Lama)</p>
          <div class="h-20 flex items-center justify-center">
            <?php if (!empty($report['ttd_menyerahkan'])): ?>
              <img src="<?= UPLOAD_URL . $report['ttd_menyerahkan'] ?>" alt="TTD Menyerahkan" class="max-h-20 object-contain" />
            <?php else: ?>
              <span class="text-slate-400 italic">Belum ada TTD</span>
            <?php endif; ?>
          </div>
          <p class="font-extrabold text-slate-800 dark:text-white mt-2"><?= sanitize($report['nama_menyerahkan']) ?></p>
          <p class="text-[10px] text-slate-400"><?= sanitize($report['jabatan_menyerahkan'] ?? '') ?></p>
        </div>
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50">
          <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Diterima &amp; Disahkan Oleh (Shift Baru)</p>
          <div class="h-20 flex items-center justify-center">
            <?php if ($report['status_validasi'] === 'TERVALIDASI' && !empty($report['ttd_menerima'])): ?>
              <img src="<?= UPLOAD_URL . $report['ttd_menerima'] ?>" alt="TTD Menerima" class="max-h-20 object-contain" />
            <?php else: ?>
              <span class="text-amber-600 font-bold">Menunggu Validasi</span>
            <?php endif; ?>
          </div>
          <p class="font-extrabold text-slate-800 dark:text-white mt-2"><?= sanitize($report['nama_menerima'] ?: '-') ?></p>
          <p class="text-[10px] text-slate-400"><?= sanitize($report['jabatan_menerima'] ?? '') ?></p>
        </div>
      </div>

    <?php endif; ?>

    <p class="text-center text-[11px] text-slate-400 pt-2">
      <?= sanitize($appFullName) ?> • Halaman Publik Baca-Saja
    </p>
  </div>

</body>
</html>
