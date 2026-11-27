<?php
// aset/print_qr.php - Cetak Massal Label Stiker QR Kunci & Aset (Grid A4)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/aset_helper.php';

require_login();
ensure_aset_tables($pdo);

$assets = $pdo->query("SELECT * FROM shift_assets ORDER BY kategori ASC, kode_aset ASC, nama_aset ASC")->fetchAll();
$appName = get_app_name();
$masterToken = get_aset_qr_token(0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cetak Label QR Kunci &amp; Aset — <?= sanitize($appName) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '280' ?>" />
  <script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>
  <style>
    body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f5f5fa; color: #181b2c; }
    .qr-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
    }
    .qr-sticker {
      background: #ffffff;
      border: 1.5px dashed #cbd5e1;
      border-radius: 16px;
      padding: 14px 12px;
      text-align: center;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .qr-box {
      width: 132px;
      height: 132px;
      margin: 8px auto;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .qr-box canvas, .qr-box img {
      width: 132px !important;
      height: 132px !important;
    }
    @media (max-width: 768px) {
      .qr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
      .qr-grid { grid-template-columns: 1fr; }
    }
    @media print {
      body { background: #ffffff !important; padding: 0 !important; }
      .no-print { display: none !important; }
      .qr-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 10px !important; }
      .qr-sticker { border: 1.5px dashed #94a3b8 !important; }
    }
  </style>
</head>
<body class="p-4 sm:p-6">
  <div class="max-w-5xl mx-auto space-y-5">
    <!-- Toolbar -->
    <div class="no-print bg-white rounded-2xl border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
      <div>
        <h1 class="text-base font-extrabold text-slate-900">Cetak Massal Stiker QR Kunci &amp; Aset</h1>
        <p class="text-xs text-slate-500">Total <?= count($assets) + 1 ?> label (1 QR Meja Tamu + <?= count($assets) ?> QR item kunci/aset). Siap cetak kertas A4.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>aset/index" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">Kembali</a>
        <button type="button" onclick="window.print()" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-extrabold cursor-pointer">
          Cetak Semua (A4)
        </button>
      </div>
    </div>

    <!-- Sticker Grid -->
    <div class="qr-grid">
      <!-- Master Desk QR -->
      <div class="qr-sticker" style="border-color: #6558f5;">
        <div class="text-[9.5px] font-extrabold uppercase tracking-wider text-[#6558f5]">PORTAL TAMU / RAK KUNCI</div>
        <div class="text-xs font-extrabold text-slate-900 mt-0.5 leading-snug">Scan Semua Kunci &amp; Aset</div>
        <div class="text-[10px] font-mono text-slate-500 mt-0.5">MEJA-RAK</div>
        <div class="qr-box" data-qr-url="<?= sanitize(BASE_URL . 'aset/scan?t=' . $masterToken) ?>"></div>
        <div class="text-[9.5px] text-slate-400">Scan kamera HP untuk catat pinjam/kembali</div>
      </div>

      <!-- Per-Key QR Stickers -->
      <?php foreach ($assets as $a): ?>
        <?php $tok = get_aset_qr_token((int)$a['id']); ?>
        <div class="qr-sticker">
          <div class="text-[9.5px] font-extrabold uppercase tracking-wider text-[#6558f5]"><?= sanitize($a['kategori']) ?></div>
          <div class="text-xs font-extrabold text-slate-900 mt-0.5 leading-snug"><?= sanitize($a['nama_aset']) ?></div>
          <div class="text-[10px] font-mono text-slate-500 mt-0.5"><?= sanitize($a['kode_aset']) ?></div>
          <div class="qr-box" data-qr-url="<?= sanitize(BASE_URL . 'aset/scan?id=' . (int)$a['id'] . '&t=' . $tok) ?>"></div>
          <div class="text-[9.5px] text-slate-400">Scan kamera HP untuk pinjam/kembali</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    document.querySelectorAll('.qr-box').forEach(function (el) {
      var rel = el.getAttribute('data-qr-url') || '';
      var full = window.location.origin + rel;
      new QRCode(el, {
        text: full,
        width: 132,
        height: 132,
        colorDark: '#181b2c',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    });
  </script>
</body>
</html>
