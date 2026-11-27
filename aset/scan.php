<?php
// aset/scan.php - Portal Scan QR / Barcode Peminjaman & Pengembalian Kunci untuk Tamu & Petugas (Tanpa Login, Token HMAC)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/aset_helper.php';

ensure_aset_tables($pdo);

$assetId  = max(0, (int)($_GET['id'] ?? $_POST['qr_id'] ?? 0));
$folderId = $assetId > 0 ? 0 : max(0, (int)($_GET['folder'] ?? $_POST['qr_folder'] ?? 0));
$token    = trim($_GET['t'] ?? $_POST['qr_t'] ?? '');

$isValidToken = verify_aset_qr_token($assetId, $token, $folderId);
$targetAsset  = null;
$targetFolder = null;
$allAssets    = [];

$fetchScanAssets = function (PDO $pdo, int $fid): array {
    if ($fid > 0) {
        $s = $pdo->prepare("SELECT * FROM shift_assets WHERE folder_id = :fid ORDER BY status ASC, nama_aset ASC");
        $s->execute(['fid' => $fid]);
        return $s->fetchAll();
    }
    return $pdo->query("SELECT * FROM shift_assets ORDER BY kategori ASC, status ASC, nama_aset ASC")->fetchAll();
};

if ($isValidToken) {
    if ($assetId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM shift_assets WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $assetId]);
        $targetAsset = $stmt->fetch() ?: null;
        if (!$targetAsset) {
            $isValidToken = false;
        }
    } elseif ($folderId > 0) {
        $stmtF = $pdo->prepare("SELECT * FROM shift_asset_folders WHERE id = :fid LIMIT 1");
        $stmtF->execute(['fid' => $folderId]);
        $targetFolder = $stmtF->fetch() ?: null;
        if (!$targetFolder) {
            $isValidToken = false;
        }
    }
    if ($isValidToken) {
        $allAssets = $fetchScanAssets($pdo, $folderId);
    }
}

$successMsg = '';
$errorMsg   = '';

if ($isValidToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedId = $assetId > 0 ? $assetId : (int)($_POST['asset_id'] ?? 0);
    $aksi       = ($_POST['aksi'] ?? 'PINJAM') === 'KEMBALI' ? 'KEMBALI' : 'PINJAM';
    $namaTamu   = trim($_POST['nama_peminjam'] ?? '');
    $kontak     = trim($_POST['kontak_instansi'] ?? '');
    $catatan    = trim($_POST['catatan'] ?? '');

    if ($selectedId <= 0) {
        $errorMsg = 'Silakan pilih kunci atau aset yang ingin dicatat.';
    } elseif ($namaTamu === '') {
        $errorMsg = 'Nama peminjam / tamu wajib diisi.';
    } else {
        $stmtA = $pdo->prepare("SELECT * FROM shift_assets WHERE id = :id LIMIT 1");
        $stmtA->execute(['id' => $selectedId]);
        $chosen = $stmtA->fetch();

        if (!$chosen || ($folderId > 0 && (int)$chosen['folder_id'] !== $folderId)) {
            $errorMsg = 'Data kunci / aset tidak ditemukan pada folder ini.';
        } else {
            $displayBorrower = $kontak !== ''
                ? mb_substr($namaTamu . ' (' . $kontak . ')', 0, 100)
                : mb_substr($namaTamu . ' (Tamu/QR)', 0, 100);
            $noteText = $catatan !== ''
                ? mb_substr('[Scan QR] ' . $catatan, 0, 255)
                : ($aksi === 'PINJAM' ? '[Scan QR] Dipinjam via barcode' : '[Scan QR] Dikembalikan via barcode');

            if ($aksi === 'PINJAM' && $chosen['status'] === 'Dipinjam') {
                $errorMsg = "Gagal: '{$chosen['nama_aset']}' sedang dipinjam oleh {$chosen['pemegang_nama']}. Harap kembalikan terlebih dahulu sebelum dipinjam lagi.";
            } elseif ($aksi === 'PINJAM') {
                $pdo->prepare("UPDATE shift_assets SET status = 'Dipinjam', pemegang_id = 0, pemegang_nama = :unama, waktu_pinjam = NOW(), catatan_terakhir = :cat WHERE id = :id")
                    ->execute(['unama' => $displayBorrower, 'cat' => $noteText, 'id' => $selectedId]);
                $pdo->prepare("INSERT INTO shift_asset_logs (asset_id, nama_aset, aksi, user_id, nama_user, catatan) VALUES (:aid, :na, 'PINJAM', 0, :unama, :cat)")
                    ->execute(['aid' => $selectedId, 'na' => $chosen['nama_aset'], 'unama' => $displayBorrower, 'cat' => $noteText]);
                notify_aset_activity(
                    $pdo,
                    'Peminjaman ' . $chosen['kategori'] . ' via QR Tamu',
                    "**{$chosen['nama_aset']}** ({$chosen['kode_aset']}) dipinjam oleh **{$displayBorrower}**." . ($catatan !== '' ? "\nCatatan: {$catatan}" : ''),
                    0xf5a623
                );
                $successMsg = "Berhasil! '{$chosen['nama_aset']}' tercatat dipinjam oleh {$displayBorrower}.";
            } else {
                $pdo->prepare("UPDATE shift_assets SET status = 'Tersedia', pemegang_id = NULL, pemegang_nama = NULL, waktu_pinjam = NULL, catatan_terakhir = :cat WHERE id = :id")
                    ->execute(['cat' => $noteText, 'id' => $selectedId]);
                $pdo->prepare("INSERT INTO shift_asset_logs (asset_id, nama_aset, aksi, user_id, nama_user, catatan) VALUES (:aid, :na, 'KEMBALI', 0, :unama, :cat)")
                    ->execute(['aid' => $selectedId, 'na' => $chosen['nama_aset'], 'unama' => $displayBorrower, 'cat' => $noteText]);
                notify_aset_activity(
                    $pdo,
                    'Pengembalian ' . $chosen['kategori'] . ' via QR',
                    "**{$chosen['nama_aset']}** ({$chosen['kode_aset']}) telah dikembalikan oleh **{$displayBorrower}**." . ($catatan !== '' ? "\nCatatan: {$catatan}" : ''),
                    0x10b981
                );
                $successMsg = "Terima kasih! '{$chosen['nama_aset']}' telah dicatat kembali oleh {$displayBorrower}.";
            }

            // Refresh state after update
            $allAssets = $fetchScanAssets($pdo, $folderId);
            if ($assetId > 0) {
                $stmtA->execute(['id' => $assetId]);
                $targetAsset = $stmtA->fetch() ?: null;
            }
        }
    }
}

$appName     = get_app_name();
$appFullName = get_app_full_name();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Scan QR Kunci &amp; Aset — <?= sanitize($appName) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: '280' ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/regular/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/bold/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/fill/style.css" />
  <style>
    body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    .aksi-radio:checked + .aksi-box-pinjam {
      border-color: #6558f5;
      background-color: rgba(239, 239, 251, 0.75);
      box-shadow: 0 0 0 2px rgba(101, 88, 245, 0.15);
    }
    .aksi-radio:checked + .aksi-box-kembali {
      border-color: #059669;
      background-color: #ecfdf5;
      box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.15);
    }
  </style>
</head>
<body class="min-h-screen bg-[#f5f5fa] text-[#181b2c] py-6 px-4 flex items-center justify-center">

  <div class="w-full max-w-lg space-y-5">

    <!-- Brand Header -->
    <div class="text-center space-y-1.5">
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#efeffb] text-[#6558f5] text-xs font-extrabold">
        <i class="ph-fill ph-qr-code text-base"></i>
        <span>Portal Scan QR<?= $targetFolder ? ' · ' . sanitize($targetFolder['nama_folder']) : ($targetAsset ? ' · ' . sanitize($targetAsset['kategori']) : '') ?> · <?= sanitize($appName) ?></span>
      </div>
      <h1 class="text-2xl font-extrabold text-[#181b2c] tracking-tight">
        <?= $targetAsset ? sanitize($targetAsset['nama_aset']) : ($targetFolder ? 'Catat ' . sanitize($targetFolder['nama_folder']) : 'Catat Peminjaman Item') ?>
      </h1>
      <p class="text-xs text-slate-500">
        <?= $targetAsset ? 'Kode: ' . sanitize($targetAsset['kode_aset']) . ' · ' . sanitize($targetAsset['kategori']) : ($targetFolder ? 'Pilih item dari folder ' . sanitize($targetFolder['nama_folder']) . ' lalu isi identitas Anda.' : 'Pilih kunci, alat, atau aset yang dipinjam/dikembalikan lalu isi identitas Anda.') ?>
      </p>
    </div>

    <?php if (!$isValidToken): ?>
      <div class="bg-white rounded-[26px] border border-rose-200 p-8 text-center shadow-lg space-y-3">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center">
          <i class="ph-fill ph-warning-circle text-2xl"></i>
        </div>
        <h2 class="text-base font-extrabold text-slate-900">QR Code Tidak Valid atau Kadaluarsa</h2>
        <p class="text-xs text-slate-500">Pastikan Anda memindai QR Code resmi yang tertera pada rak kunci atau meja operasional.</p>
      </div>
    <?php else: ?>

      <?php if ($successMsg !== ''): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-start gap-2.5 shadow-sm">
          <i class="ph-fill ph-check-circle text-lg text-emerald-600 shrink-0 mt-0.5"></i>
          <div>
            <div><?= sanitize($successMsg) ?></div>
            <div class="text-[11px] font-medium text-emerald-700 mt-0.5">Catatan Anda sudah masuk ke log petugas piket secara real-time.</div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg !== ''): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
          <i class="ph-fill ph-warning-circle text-lg shrink-0"></i>
          <span><?= sanitize($errorMsg) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($targetAsset): ?>
        <?php $tBorrowed = ($targetAsset['status'] === 'Dipinjam'); ?>
        <div class="p-4 rounded-2xl bg-white border <?= $tBorrowed ? 'border-amber-300' : 'border-emerald-200' ?> flex items-center justify-between gap-3 shadow-sm">
          <div>
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Status Saat Ini</div>
            <div class="text-sm font-extrabold text-slate-900 mt-0.5">
              <?= $tBorrowed ? 'Sedang Dipinjam oleh ' . sanitize($targetAsset['pemegang_nama']) : 'Tersedia di Rak / Pos' ?>
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-[11px] font-extrabold <?= $tBorrowed ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white' ?>">
            <?= $tBorrowed ? 'DIPINJAM' : 'TERSEDIA' ?>
          </span>
        </div>
      <?php endif; ?>

      <!-- Scan Form Card -->
      <div class="bg-white rounded-[28px] border border-slate-100 shadow-xl p-5 sm:p-7">
        <form method="POST" action="" class="space-y-4 text-xs">
          <input type="hidden" name="qr_id" value="<?= $assetId ?>" />
          <input type="hidden" name="qr_folder" value="<?= $folderId ?>" />
          <input type="hidden" name="qr_t" value="<?= sanitize($token) ?>" />

          <!-- Action Toggle -->
          <div>
            <label class="block font-extrabold text-slate-700 mb-2">Jenis Aktivitas</label>
            <div class="grid grid-cols-2 gap-2.5">
              <?php
                $isTargetBorrowed = ($targetAsset && $targetAsset['status'] === 'Dipinjam');
                $defaultAksi = $isTargetBorrowed ? 'KEMBALI' : 'PINJAM';
              ?>
              <label class="<?= $isTargetBorrowed ? 'opacity-45 cursor-not-allowed' : 'cursor-pointer' ?>" id="lblPinjam">
                <input type="radio" name="aksi" id="radioPinjam" value="PINJAM" class="aksi-radio sr-only" <?= $defaultAksi === 'PINJAM' ? 'checked' : '' ?> <?= $isTargetBorrowed ? 'disabled' : '' ?> />
                <div class="aksi-box-pinjam p-3 rounded-2xl border border-slate-200 text-center transition">
                  <i class="ph-bold ph-hand-grabbing text-base text-[#6558f5] block mb-0.5"></i>
                  <span class="font-extrabold text-slate-800">Pinjam</span>
                </div>
              </label>
              <label class="cursor-pointer">
                <input type="radio" name="aksi" id="radioKembali" value="KEMBALI" class="aksi-radio sr-only" <?= $defaultAksi === 'KEMBALI' ? 'checked' : '' ?> />
                <div class="aksi-box-kembali p-3 rounded-2xl border border-slate-200 text-center transition">
                  <i class="ph-bold ph-arrow-u-down-left text-base text-emerald-600 block mb-0.5"></i>
                  <span class="font-extrabold text-slate-800">Kembalikan</span>
                </div>
              </label>
            </div>
            <p id="borrowLockHint" class="text-[11px] text-amber-700 font-bold mt-1.5 <?= $isTargetBorrowed ? '' : 'hidden' ?>">
              <i class="ph-bold ph-lock"></i> Item ini sedang dipinjam — hanya bisa dicatat pengembaliannya terlebih dahulu.
            </p>
          </div>

          <?php if (!$targetAsset): ?>
            <div>
              <label class="block font-extrabold text-slate-700 mb-1.5">Pilih <?= $targetFolder ? sanitize($targetFolder['nama_folder']) : 'Kunci / Alat / Aset' ?></label>
              <select name="asset_id" id="scanAssetSelect" onchange="syncScanAssetStatus()" required class="w-full px-3.5 py-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-800">
                <option value="">-- Pilih <?= $targetFolder ? sanitize($targetFolder['nama_folder']) : 'Item' ?> --</option>
                <?php foreach ($allAssets as $al): ?>
                  <option value="<?= $al['id'] ?>" data-status="<?= sanitize($al['status']) ?>">
                    [<?= sanitize($al['kategori']) ?>] <?= sanitize($al['kode_aset']) ?> - <?= sanitize($al['nama_aset']) ?> (<?= $al['status'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="asset_id" value="<?= $targetAsset['id'] ?>" />
          <?php endif; ?>

          <div>
            <label class="block font-extrabold text-slate-700 mb-1.5">Nama Lengkap Peminjam / Tamu <span class="text-rose-500">*</span></label>
            <input type="text" name="nama_peminjam" required placeholder="Contoh: Budi Santoso"
                   class="w-full px-3.5 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-semibold" />
          </div>

          <div>
            <label class="block font-extrabold text-slate-700 mb-1.5">No. HP / Instansi / Unit (Opsional)</label>
            <input type="text" name="kontak_instansi" placeholder="Contoh: 0812-xxxx / Vendor AC / Tamu"
                   class="w-full px-3.5 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900" />
          </div>

          <div>
            <label class="block font-extrabold text-slate-700 mb-1.5">Keperluan / Keterangan (Opsional)</label>
            <textarea name="catatan" rows="2" placeholder="Contoh: Cek panel listrik lantai 2"
                      class="w-full px-3.5 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900"></textarea>
          </div>

          <button type="submit"
                  class="w-full py-3.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-sm shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
            <i class="ph-bold ph-check-circle text-base"></i>
            <span>Catat Sekarang</span>
          </button>
        </form>
      </div>
    <?php endif; ?>

    <p class="text-center text-[11px] text-slate-400">
      <?= sanitize($appFullName) ?> · Tercatat otomatis ke Log Kunci &amp; Aset Shift
    </p>
  </div>
  <script>
    function syncScanAssetStatus() {
      var sel = document.getElementById('scanAssetSelect');
      if (!sel) return;
      var opt = sel.options[sel.selectedIndex];
      var st = opt ? opt.getAttribute('data-status') : '';
      var rPinjam  = document.getElementById('radioPinjam');
      var rKembali = document.getElementById('radioKembali');
      var lblPinjam = document.getElementById('lblPinjam');
      var hint = document.getElementById('borrowLockHint');
      if (st === 'Dipinjam') {
        rPinjam.disabled = true;
        rKembali.checked = true;
        lblPinjam.classList.add('opacity-45', 'cursor-not-allowed');
        lblPinjam.classList.remove('cursor-pointer');
        hint.classList.remove('hidden');
      } else {
        rPinjam.disabled = false;
        lblPinjam.classList.remove('opacity-45', 'cursor-not-allowed');
        lblPinjam.classList.add('cursor-pointer');
        hint.classList.add('hidden');
      }
    }
  </script>
</body>
</html>
