<?php
// aset/index.php - Log Peminjaman Kunci, Alat & Aset Shift + QR Barcode Tamu (Publik / Bersama Untuk Semua Petugas)
$current_page = 'aset';
$page_title = 'Kunci, Alat & Aset Shift';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/aset_helper.php';

require_login();
$user = current_user();

ensure_aset_tables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_asset') {
        $kode = trim($_POST['kode_aset'] ?? '');
        $nama = trim($_POST['nama_aset'] ?? '');
        $kat  = $_POST['kategori'] ?? 'Kunci';
        if (!in_array($kat, ['Kunci', 'Alat / Tools', 'Perangkat IT', 'Kartu Akses', 'Lainnya'], true)) {
            $kat = 'Kunci';
        }
        if ($nama !== '') {
            if ($kode === '') $kode = 'KNC-' . date('His');
            $pdo->prepare("INSERT INTO shift_assets (kode_aset, nama_aset, kategori) VALUES (:k, :n, :kat)")
                ->execute(['k' => mb_substr($kode, 0, 40), 'n' => mb_substr($nama, 0, 150), 'kat' => $kat]);
            flash_set('success', "Aset '{$nama}' berhasil ditambahkan.");
        }
    } elseif ($action === 'borrow_asset') {
        $aid = (int)($_POST['asset_id'] ?? 0);
        $cat = trim($_POST['catatan'] ?? '');
        $stmtA = $pdo->prepare("SELECT * FROM shift_assets WHERE id = :id LIMIT 1");
        $stmtA->execute(['id' => $aid]);
        $asset = $stmtA->fetch();
        if ($asset) {
            $pdo->prepare("UPDATE shift_assets SET status = 'Dipinjam', pemegang_id = :uid, pemegang_nama = :unama, waktu_pinjam = NOW(), catatan_terakhir = :cat WHERE id = :id")
                ->execute(['uid' => (int)$user['id'], 'unama' => $user['nama_lengkap'], 'cat' => mb_substr($cat, 0, 255), 'id' => $aid]);
            $pdo->prepare("INSERT INTO shift_asset_logs (asset_id, nama_aset, aksi, user_id, nama_user, catatan) VALUES (:aid, :na, 'PINJAM', :uid, :unama, :cat)")
                ->execute(['aid' => $aid, 'na' => $asset['nama_aset'], 'uid' => (int)$user['id'], 'unama' => $user['nama_lengkap'], 'cat' => mb_substr($cat, 0, 255)]);
            flash_set('success', "'{$asset['nama_aset']}' tercatat dipegang oleh {$user['nama_lengkap']}.");
        }
    } elseif ($action === 'return_asset') {
        $aid = (int)($_POST['asset_id'] ?? 0);
        $cat = trim($_POST['catatan'] ?? '');
        $stmtA = $pdo->prepare("SELECT * FROM shift_assets WHERE id = :id LIMIT 1");
        $stmtA->execute(['id' => $aid]);
        $asset = $stmtA->fetch();
        if ($asset) {
            $pdo->prepare("UPDATE shift_assets SET status = 'Tersedia', pemegang_id = NULL, pemegang_nama = NULL, waktu_pinjam = NULL, catatan_terakhir = :cat WHERE id = :id")
                ->execute(['cat' => mb_substr($cat ?: 'Dikembalikan dalam kondisi baik', 0, 255), 'id' => $aid]);
            $pdo->prepare("INSERT INTO shift_asset_logs (asset_id, nama_aset, aksi, user_id, nama_user, catatan) VALUES (:aid, :na, 'KEMBALI', :uid, :unama, :cat)")
                ->execute(['aid' => $aid, 'na' => $asset['nama_aset'], 'uid' => (int)$user['id'], 'unama' => $user['nama_lengkap'], 'cat' => mb_substr($cat ?: 'Kondisi baik', 0, 255)]);
            flash_set('success', "'{$asset['nama_aset']}' berhasil dikembalikan.");
        }
    } elseif ($action === 'delete_asset' && is_admin()) {
        $aid = (int)($_POST['asset_id'] ?? 0);
        if ($aid > 0) {
            $pdo->prepare("DELETE FROM shift_assets WHERE id = :id")->execute(['id' => $aid]);
            flash_set('success', 'Item aset dihapus.');
        }
    }

    header('Location: ' . BASE_URL . 'aset/index');
    exit;
}

$assets = $pdo->query("SELECT * FROM shift_assets ORDER BY status DESC, kategori ASC, nama_aset ASC")->fetchAll();
$logs   = $pdo->query("SELECT * FROM shift_asset_logs ORDER BY id DESC LIMIT 20")->fetchAll();

$cntTersedia = 0;
$cntDipinjam = 0;
$cntGuest    = 0;
foreach ($assets as $a) {
    if ($a['status'] === 'Dipinjam') {
        $cntDipinjam++;
        if ((int)$a['pemegang_id'] === 0) $cntGuest++;
    } else {
        $cntTersedia++;
    }
}

$masterQrToken = get_aset_qr_token(0);

$catMeta = [
    'Kunci'        => ['icon' => 'ph-key',                 'bg' => 'bg-indigo-50 dark:bg-indigo-950/60',  'text' => 'text-[#6558f5] dark:text-indigo-300'],
    'Alat / Tools' => ['icon' => 'ph-wrench',              'bg' => 'bg-amber-50 dark:bg-amber-950/50',    'text' => 'text-amber-600 dark:text-amber-300'],
    'Perangkat IT' => ['icon' => 'ph-desktop',             'bg' => 'bg-blue-50 dark:bg-blue-950/50',      'text' => 'text-blue-600 dark:text-blue-300'],
    'Kartu Akses'  => ['icon' => 'ph-identification-card', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/50','text' => 'text-emerald-600 dark:text-emerald-300'],
    'Lainnya'      => ['icon' => 'ph-cube',                'bg' => 'bg-purple-50 dark:bg-purple-950/50',  'text' => 'text-purple-600 dark:text-purple-300'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>

<style>
  .aset-card {
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
  }
  .aset-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px -12px rgba(24, 27, 44, 0.12);
  }
  @media print {
    body * { visibility: hidden !important; }
    #qrPrintArea, #qrPrintArea * { visibility: visible !important; }
    #qrPrintArea {
      position: fixed !important;
      left: 50% !important;
      top: 50% !important;
      transform: translate(-50%, -50%) !important;
      width: 320px !important;
      padding: 24px !important;
      border: 2px dashed #6558f5 !important;
      border-radius: 20px !important;
      background: #ffffff !important;
      color: #181b2c !important;
      text-align: center !important;
    }
  }
</style>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-users-three"></i>
        <span>Publik Bersama (Semua Petugas &amp; Scan QR Tamu)</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2">
        <i class="ph-fill ph-key text-[#6558f5]"></i>
        <span>Kunci, Alat &amp; Aset Shift</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Pantau pemegang terakhir kunci, cetak QR Code untuk ditempel di rak kunci, dan izinkan tamu mencatat peminjaman lewat scan HP.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button type="button"
              onclick="openQrModal(0, 'MEJA-RAK', 'Portal Scan QR Tamu (Semua Kunci)', 'Pilih kunci apa saja saat scan', <?= json_encode($masterQrToken) ?>)"
              class="px-4 py-2.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-200 dark:border-slate-700 hover:border-[#6558f5] text-[#181b2c] dark:text-white font-extrabold text-xs shadow-sm transition flex items-center gap-2 cursor-pointer">
        <i class="ph-bold ph-qr-code text-base text-[#6558f5]"></i>
        <span>QR Meja / Tamu</span>
      </button>
      <button type="button" onclick="document.getElementById('addAssetModal').classList.remove('hidden')"
              class="px-5 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 transition flex items-center gap-2 cursor-pointer">
        <i class="ph-bold ph-plus-circle text-base"></i>
        <span>Tambah Kunci / Aset</span>
      </button>
    </div>
  </div>

  <!-- Stats (4 Cards) -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center shrink-0">
        <i class="ph-fill ph-key text-xl"></i>
      </div>
      <div>
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Item</p>
        <p class="text-xl font-black text-[#181b2c] dark:text-white"><?= count($assets) ?></p>
      </div>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center shrink-0">
        <i class="ph-fill ph-check-circle text-xl"></i>
      </div>
      <div>
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600">Tersedia</p>
        <p class="text-xl font-black text-emerald-600"><?= $cntTersedia ?></p>
      </div>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-300 flex items-center justify-center shrink-0">
        <i class="ph-fill ph-hand-grabbing text-xl"></i>
      </div>
      <div>
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600">Dipinjam</p>
        <p class="text-xl font-black text-amber-600"><?= $cntDipinjam ?></p>
      </div>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-300 flex items-center justify-center shrink-0">
        <i class="ph-fill ph-qr-code text-xl"></i>
      </div>
      <div>
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600">Pinjaman Tamu</p>
        <p class="text-xl font-black text-blue-600"><?= $cntGuest ?></p>
      </div>
    </div>
  </div>

  <!-- Search & Filter Toolbar -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[22px] border border-slate-100 dark:border-slate-800/80 p-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3">
    <div class="relative flex-1">
      <i class="ph-bold ph-magnifying-glass text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 text-sm"></i>
      <input type="text" id="asetSearch" oninput="filterAssets()"
             placeholder="Cari nama kunci, kode, atau nama pemegang..."
             class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs" />
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <select id="asetCatFilter" onchange="filterAssets()"
              class="px-3 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-bold cursor-pointer">
        <option value="all">Semua Kategori</option>
        <option value="Kunci">Kunci</option>
        <option value="Kartu Akses">Kartu Akses</option>
        <option value="Alat / Tools">Alat / Tools</option>
        <option value="Perangkat IT">Perangkat IT</option>
        <option value="Lainnya">Lainnya</option>
      </select>
      <select id="asetStatusFilter" onchange="filterAssets()"
              class="px-3 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-bold cursor-pointer">
        <option value="all">Semua Status</option>
        <option value="Tersedia">Tersedia</option>
        <option value="Dipinjam">Dipinjam</option>
      </select>
    </div>
  </div>

  <!-- Asset Cards Grid -->
  <?php if (empty($assets)): ?>
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800 p-10 text-center text-xs text-slate-400">
      <i class="ph ph-key text-4xl block mx-auto mb-2 opacity-40"></i>
      Belum ada daftar kunci atau aset operasional. Klik <strong>Tambah Kunci / Aset</strong> untuk mendaftarkan.
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="asetGrid">
      <?php foreach ($assets as $a): ?>
        <?php
          $isBorrowed = ($a['status'] === 'Dipinjam');
          $isGuest    = ($isBorrowed && (int)$a['pemegang_id'] === 0);
          $cm         = $catMeta[$a['kategori']] ?? $catMeta['Lainnya'];
          $itemToken  = get_aset_qr_token((int)$a['id']);
          $searchBlob = strtolower($a['kode_aset'] . ' ' . $a['nama_aset'] . ' ' . $a['kategori'] . ' ' . ($a['pemegang_nama'] ?? ''));
        ?>
        <div class="aset-card bg-white dark:bg-[#1b1e30] rounded-[24px] border <?= $isBorrowed ? 'border-amber-300/90 dark:border-amber-700/70' : 'border-slate-100 dark:border-slate-800/80' ?> shadow-card p-5 flex flex-col justify-between gap-4"
             data-search="<?= sanitize($searchBlob) ?>"
             data-cat="<?= sanitize($a['kategori']) ?>"
             data-status="<?= sanitize($a['status']) ?>">
          <div>
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl <?= $cm['bg'] ?> <?= $cm['text'] ?> flex items-center justify-center shrink-0">
                  <i class="ph-fill <?= $cm['icon'] ?> text-lg"></i>
                </div>
                <div class="min-w-0">
                  <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 truncate">
                    <?= sanitize($a['kategori']) ?>
                  </span>
                  <span class="block text-[11px] font-mono font-bold text-[#6558f5] dark:text-indigo-300">
                    <?= sanitize($a['kode_aset']) ?>
                  </span>
                </div>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <button type="button"
                        onclick='openQrModal(<?= (int)$a["id"] ?>, <?= json_encode($a["kode_aset"]) ?>, <?= json_encode($a["nama_aset"]) ?>, <?= json_encode($a["kategori"]) ?>, <?= json_encode($itemToken) ?>)'
                        class="w-7 h-7 rounded-lg bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-[#6558f5] hover:text-[#6558f5] flex items-center justify-center transition cursor-pointer"
                        title="Lihat &amp; Cetak QR Barcode Kunci Ini">
                  <i class="ph-bold ph-qr-code text-sm"></i>
                </button>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold <?= $isBorrowed ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white' ?>">
                  <?= $isBorrowed ? 'DIPINJAM' : 'TERSEDIA' ?>
                </span>
              </div>
            </div>

            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white leading-snug"><?= sanitize($a['nama_aset']) ?></h3>

            <?php if ($isBorrowed): ?>
              <div class="mt-3 p-3 rounded-2xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/40 text-xs space-y-1">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-bold text-amber-900 dark:text-amber-200 truncate">Pemegang: <?= sanitize($a['pemegang_nama']) ?></p>
                  <?php if ($isGuest): ?>
                    <span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[9px] font-extrabold shrink-0">TAMU / QR</span>
                  <?php endif; ?>
                </div>
                <p class="text-[11px] text-amber-700 dark:text-amber-300">Sejak <?= date('d/m H:i', strtotime($a['waktu_pinjam'])) ?> WIB</p>
                <?php if (!empty($a['catatan_terakhir'])): ?>
                  <p class="text-[11px] text-slate-600 dark:text-slate-300 italic">"<?= sanitize($a['catatan_terakhir']) ?>"</p>
                <?php endif; ?>
              </div>
            <?php elseif (!empty($a['catatan_terakhir'])): ?>
              <p class="text-[11px] text-slate-400 mt-2">Catatan terakhir: <?= sanitize($a['catatan_terakhir']) ?></p>
            <?php endif; ?>
          </div>

          <div class="flex items-center gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <form method="POST" action="" class="flex-1 flex gap-2">
              <input type="hidden" name="asset_id" value="<?= $a['id'] ?>" />
              <input type="hidden" name="action" value="<?= $isBorrowed ? 'return_asset' : 'borrow_asset' ?>" />
              <input type="text" name="catatan" placeholder="<?= $isBorrowed ? 'Kondisi saat kembali...' : 'Keperluan / lokasi...' ?>"
                     class="flex-1 min-w-0 px-2.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[11px]" />
              <button type="submit"
                      class="px-3.5 py-1.5 rounded-xl <?= $isBorrowed ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#6558f5] hover:bg-[#5244e8]' ?> text-white text-xs font-extrabold transition shrink-0 cursor-pointer">
                <?= $isBorrowed ? 'Kembalikan' : 'Pinjam' ?>
              </button>
            </form>
            <?php if (is_admin()): ?>
              <form method="POST" action="" onsubmit="return confirm('Hapus aset ini?')">
                <input type="hidden" name="action" value="delete_asset" />
                <input type="hidden" name="asset_id" value="<?= $a['id'] ?>" />
                <button type="submit" class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-500 hover:text-white transition cursor-pointer" title="Hapus">
                  <i class="ph-bold ph-trash text-xs"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Recent Asset Logs -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 sm:p-6">
    <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white mb-4 flex items-center gap-2">
      <i class="ph-bold ph-clock-counter-clockwise text-[#6558f5]"></i>
      <span>Riwayat Serah Terima Kunci &amp; Aset Terbaru</span>
    </h3>
    <?php if (empty($logs)): ?>
      <p class="text-xs text-slate-400">Belum ada riwayat peminjaman atau pengembalian.</p>
    <?php else: ?>
      <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
        <?php foreach ($logs as $lg): ?>
          <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold <?= $lg['aksi'] === 'PINJAM' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?>">
                <?= $lg['aksi'] ?>
              </span>
              <?php if ((int)$lg['user_id'] === 0): ?>
                <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[9.5px] font-extrabold">QR TAMU</span>
              <?php endif; ?>
              <strong class="text-slate-800 dark:text-white"><?= sanitize($lg['nama_aset']) ?></strong>
              <span class="text-slate-500">oleh <strong><?= sanitize($lg['nama_user']) ?></strong></span>
              <?php if (!empty($lg['catatan'])): ?>
                <span class="text-slate-400 italic">— <?= sanitize($lg['catatan']) ?></span>
              <?php endif; ?>
            </div>
            <span class="text-[11px] font-mono text-slate-400"><?= date('d/m/Y H:i', strtotime($lg['created_at'])) ?> WIB</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal QR / Barcode Kunci -->
<div id="qrModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('qrModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-sm bg-white dark:bg-[#1b1e30] rounded-[28px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="ph-fill ph-qr-code text-[#6558f5] text-lg"></i>
        <span>Label QR Scan Kunci</span>
      </h3>
      <button type="button" onclick="document.getElementById('qrModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
        <i class="ph-bold ph-x"></i>
      </button>
    </div>

    <div id="qrPrintArea" class="p-5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-dashed border-[#6558f5]/40 text-center space-y-3">
      <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#6558f5]" id="qrModalCat">Kunci</div>
      <h4 class="text-base font-extrabold text-[#181b2c] dark:text-white leading-tight" id="qrModalTitle">Nama Kunci</h4>
      <div class="text-xs font-mono font-bold text-slate-500" id="qrModalCode">KNC-01</div>

      <div class="inline-flex p-3 rounded-2xl bg-white shadow-sm border border-slate-100 mx-auto">
        <div id="qrCodeCanvas"></div>
      </div>

      <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
        Scan QR ini dengan kamera HP untuk mencatat peminjaman atau pengembalian kunci (bisa untuk tamu tanpa login).
      </p>
    </div>

    <div class="grid grid-cols-2 gap-2.5">
      <button type="button" onclick="copyQrLink()" id="copyQrBtn"
              class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-800 dark:text-white font-extrabold text-xs flex items-center justify-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-link"></i>
        <span>Salin Link</span>
      </button>
      <button type="button" onclick="window.print()"
              class="px-4 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs flex items-center justify-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-printer"></i>
        <span>Cetak Label</span>
      </button>
    </div>
  </div>
</div>

<!-- Modal Tambah Aset -->
<div id="addAssetModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('addAssetModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[26px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Daftarkan Kunci / Alat / Aset Baru</h3>
    <form method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="action" value="add_asset" />
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold mb-1">Kode (Opsional)</label>
          <input type="text" name="kode_aset" placeholder="KNC-01" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono" />
        </div>
        <div>
          <label class="block font-bold mb-1">Kategori</label>
          <select name="kategori" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
            <option value="Kunci">Kunci</option>
            <option value="Alat / Tools">Alat / Tools</option>
            <option value="Perangkat IT">Perangkat IT</option>
            <option value="Kartu Akses">Kartu Akses</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block font-bold mb-1">Nama Kunci / Alat / Aset</label>
        <input type="text" name="nama_aset" required placeholder="Contoh: Kunci Ruang Server &amp; Rak 1" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('addAssetModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold cursor-pointer">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] text-white font-extrabold cursor-pointer">Simpan Item</button>
      </div>
    </form>
  </div>
</div>

<script>
  var currentQrUrl = '';

  function openQrModal(id, kode, nama, kategori, token) {
    var baseScan = window.location.origin + <?= json_encode(BASE_URL . 'aset/scan') ?>;
    currentQrUrl = id > 0
      ? baseScan + '?id=' + encodeURIComponent(id) + '&t=' + encodeURIComponent(token)
      : baseScan + '?t=' + encodeURIComponent(token);

    document.getElementById('qrModalCat').textContent = kategori;
    document.getElementById('qrModalTitle').textContent = nama;
    document.getElementById('qrModalCode').textContent = kode;

    var container = document.getElementById('qrCodeCanvas');
    container.innerHTML = '';
    new QRCode(container, {
      text: currentQrUrl,
      width: 184,
      height: 184,
      colorDark: '#181b2c',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });

    document.getElementById('qrModal').classList.remove('hidden');
  }

  function copyQrLink() {
    if (!currentQrUrl) return;
    navigator.clipboard.writeText(currentQrUrl).then(function () {
      var btn = document.getElementById('copyQrBtn');
      btn.innerHTML = '<i class="ph-bold ph-check"></i><span>Tersalin!</span>';
      setTimeout(function () {
        btn.innerHTML = '<i class="ph-bold ph-link"></i><span>Salin Link</span>';
      }, 1800);
    });
  }

  function filterAssets() {
    var q   = (document.getElementById('asetSearch').value || '').toLowerCase().trim();
    var cat = document.getElementById('asetCatFilter').value;
    var st  = document.getElementById('asetStatusFilter').value;

    document.querySelectorAll('#asetGrid .aset-card').forEach(function (card) {
      var matchQ   = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
      var matchCat = cat === 'all' || card.getAttribute('data-cat') === cat;
      var matchSt  = st === 'all' || card.getAttribute('data-status') === st;
      card.classList.toggle('hidden', !(matchQ && matchCat && matchSt));
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
