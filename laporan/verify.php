<?php
// laporan/verify.php - Halaman Verifikasi Keaslian Tanda Tangan Barcode QR
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$doc = trim($_GET['doc'] ?? '');

$report = null;
if (!empty($doc)) {
    $stmt = $pdo->prepare("
        SELECT l.*, s.nama_shift, m.nama_lokasi,
               u1.nama_lengkap AS nama_menyerahkan, u1.jabatan AS jabatan_menyerahkan,
               u2.nama_lengkap AS nama_menerima, u2.jabatan AS jabatan_menerima
        FROM laporan_serah_terima l
        JOIN master_shift s ON l.shift_id = s.id
        JOIN master_lokasi m ON l.lokasi_id = m.id
        JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
        LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
        WHERE l.nomor_laporan = :doc
        LIMIT 1
    ");
    $stmt->execute(['doc' => $doc]);
    $report = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verifikasi Tanda Tangan Digital — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="min-h-screen bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex items-center justify-center p-4 font-sans">

  <div class="w-full max-w-lg bg-white dark:bg-[#131b2e] rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8">
    
    <?php if ($report): ?>
      <!-- Verified Badge -->
      <div class="text-center mb-6">
        <div class="w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center text-4xl mb-3 shadow-inner">
          <i class="ph-bold ph-shield-check"></i>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 uppercase tracking-wider mb-2">
          Terverifikasi Sah Sistem
        </span>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">Dokumen Serah Terima Valid</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Tanda tangan digital ini tercatat resmi di sistem operasional OMIT</p>
      </div>

      <!-- Detail Box -->
      <div class="bg-slate-50 dark:bg-slate-900/60 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 space-y-3.5 text-xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <span class="text-slate-500 font-bold uppercase">No. Dokumen</span>
          <span class="font-mono font-extrabold text-[#6558f5] text-sm"><?= sanitize($report['nomor_laporan']) ?></span>
        </div>

        <div class="flex items-center justify-between">
          <span class="text-slate-500 font-bold uppercase">Waktu & Shift</span>
          <span class="font-bold text-slate-800 dark:text-slate-200">
            <?= format_tanggal($report['tanggal']) ?> • Shift <?= sanitize($report['nama_shift']) ?> (<?= format_jam($report['jam_serah_terima']) ?>)
          </span>
        </div>

        <div class="flex items-center justify-between">
          <span class="text-slate-500 font-bold uppercase">Lokasi / Unit</span>
          <span class="font-bold text-slate-800 dark:text-slate-200"><?= sanitize($report['nama_lokasi']) ?></span>
        </div>

        <div class="flex items-center justify-between">
          <span class="text-slate-500 font-bold uppercase">Status Dokumen</span>
          <div><?= badge_status_validasi($report['status_validasi']) ?></div>
        </div>

        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
          <div>
            <span class="text-[10px] text-slate-400 uppercase font-bold block">1. Ditandatangani Oleh (Shift Lama):</span>
            <span class="font-extrabold text-slate-800 dark:text-slate-200 text-sm">
              <?= sanitize($report['nama_menyerahkan']) ?>
            </span>
            <span class="text-slate-400 block text-[11px]"><?= sanitize($report['jabatan_menyerahkan'] ?? '-') ?></span>
          </div>

          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800">
            <span class="text-[10px] text-slate-400 uppercase font-bold block">2. Disahkan Oleh (Shift Baru Penerima):</span>
            <?php if ($report['status_validasi'] === 'TERVALIDASI'): ?>
              <span class="font-extrabold text-slate-800 dark:text-slate-200 text-sm">
                <?= sanitize($report['nama_menerima']) ?>
              </span>
              <span class="text-slate-400 block text-[11px]"><?= sanitize($report['jabatan_menerima'] ?? '-') ?></span>
              <span class="text-[10px] text-emerald-600 font-bold mt-0.5 block">
                Divalidasi pada <?= date('d/m/Y H:i', strtotime($report['waktu_divalidasi'])) ?> WIB
              </span>
            <?php else: ?>
              <span class="font-bold text-amber-600 italic">Menunggu Approval &amp; TTD Shift Baru</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col sm:flex-row items-center gap-3">
        <a href="<?= BASE_URL ?>laporan/detail.php?id=<?= $report['id'] ?>" 
           class="w-full py-2.5 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-bold text-center transition">
          Buka Dokumen Lengkap
        </a>
        <a href="<?= BASE_URL ?>laporan/print.php?id=<?= $report['id'] ?>" target="_blank"
           class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold text-center transition">
          Lihat Cetak PDF
        </a>
      </div>

    <?php else: ?>
      <!-- Invalid / Not Found -->
      <div class="text-center py-8">
        <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-600 mx-auto flex items-center justify-center text-3xl mb-3">
          <i class="ph-bold ph-warning-circle"></i>
        </div>
        <h2 class="text-lg font-black text-slate-900 dark:text-white">Dokumen Tidak Ditemukan</h2>
        <p class="text-xs text-slate-500 mt-1">Barcode atau nomor dokumen yang Anda scan tidak terdaftar di sistem.</p>
        <a href="<?= BASE_URL ?>" class="inline-block mt-5 px-5 py-2.5 bg-[#6558f5] text-white rounded-xl text-xs font-bold">
          Kembali ke Beranda
        </a>
      </div>
    <?php endif; ?>

  </div>

</body>
</html>
