<?php
// aset/index.php - Log Peminjaman Kunci, Alat & Aset Shift (Publik / Bersama Untuk Semua Petugas)
$current_page = 'aset';
$page_title = 'Kunci, Alat & Aset Shift';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();

// Auto-migrating shared asset tables
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `shift_assets` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `kode_aset` VARCHAR(40) NOT NULL,
          `nama_aset` VARCHAR(150) NOT NULL,
          `kategori` ENUM('Kunci','Alat / Tools','Perangkat IT','Kartu Akses','Lainnya') NOT NULL DEFAULT 'Kunci',
          `status` ENUM('Tersedia','Dipinjam') NOT NULL DEFAULT 'Tersedia',
          `pemegang_id` INT(11) DEFAULT NULL,
          `pemegang_nama` VARCHAR(100) DEFAULT NULL,
          `waktu_pinjam` DATETIME DEFAULT NULL,
          `catatan_terakhir` VARCHAR(255) DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_asset_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `shift_asset_logs` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `asset_id` INT(11) NOT NULL,
          `nama_aset` VARCHAR(150) NOT NULL,
          `aksi` ENUM('PINJAM','KEMBALI') NOT NULL,
          `user_id` INT(11) NOT NULL,
          `nama_user` VARCHAR(100) NOT NULL,
          `catatan` VARCHAR(255) DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_asset_log_time` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_asset') {
        $kode = trim($_POST['kode_aset'] ?? '');
        $nama = trim($_POST['nama_aset'] ?? '');
        $kat  = $_POST['kategori'] ?? 'Kunci';
        if ($nama !== '') {
            if ($kode === '') $kode = 'AST-' . date('His');
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
$logs   = $pdo->query("SELECT * FROM shift_asset_logs ORDER BY id DESC LIMIT 15")->fetchAll();

$cntTersedia = 0;
$cntDipinjam = 0;
foreach ($assets as $a) {
    if ($a['status'] === 'Dipinjam') $cntDipinjam++;
    else $cntTersedia++;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-users-three"></i>
        <span>Publik Bersama (Semua Petugas Shift)</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-key text-[#6558f5]"></i>
        <span>Log Kunci, Alat &amp; Aset Shift</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Pantau siapa pemegang terakhir kunci ruang server, HT, kartu akses, dan peralatan piket secara transparan
      </p>
    </div>

    <button type="button" onclick="document.getElementById('addAssetModal').classList.remove('hidden')"
            class="px-5 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 transition flex items-center gap-2 self-start sm:self-auto cursor-pointer">
      <i class="ph-bold ph-plus-circle text-base"></i>
      <span>Tambah Kunci / Aset</span>
    </button>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4">
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase text-slate-400">Total Item</p>
      <p class="text-2xl font-black text-[#181b2c] dark:text-white mt-1"><?= count($assets) ?></p>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase text-emerald-600">Tersedia</p>
      <p class="text-2xl font-black text-emerald-600 mt-1"><?= $cntTersedia ?></p>
    </div>
    <div class="p-4 rounded-[22px] bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800/80 shadow-card">
      <p class="text-[10.5px] font-extrabold uppercase text-amber-600">Sedang Dipinjam</p>
      <p class="text-2xl font-black text-amber-600 mt-1"><?= $cntDipinjam ?></p>
    </div>
  </div>

  <!-- Asset Cards Grid -->
  <?php if (empty($assets)): ?>
    <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800 p-10 text-center text-xs text-slate-400">
      <i class="ph ph-key text-4xl block mx-auto mb-2 opacity-40"></i>
      Belum ada daftar kunci atau aset operasional. Klik <strong>Tambah Kunci / Aset</strong> untuk mendaftarkan.
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($assets as $a): ?>
        <?php $isBorrowed = ($a['status'] === 'Dipinjam'); ?>
        <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border <?= $isBorrowed ? 'border-amber-300/80 dark:border-amber-700/70' : 'border-slate-100 dark:border-slate-800/80' ?> shadow-card p-5 flex flex-col justify-between gap-4">
          <div>
            <div class="flex items-start justify-between gap-2 mb-2">
              <span class="px-2.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[10px] font-extrabold">
                <?= sanitize($a['kategori']) ?> • <?= sanitize($a['kode_aset']) ?>
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $isBorrowed ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white' ?>">
                <?= $isBorrowed ? 'DIPINJAM' : 'TERSEDIA' ?>
              </span>
            </div>

            <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white"><?= sanitize($a['nama_aset']) ?></h3>

            <?php if ($isBorrowed): ?>
              <div class="mt-2.5 p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 text-xs space-y-0.5">
                <p class="font-bold text-amber-900 dark:text-amber-200">Pemegang: <?= sanitize($a['pemegang_nama']) ?></p>
                <p class="text-[11px] text-amber-700 dark:text-amber-300">Sejak <?= date('d/m H:i', strtotime($a['waktu_pinjam'])) ?> WIB</p>
                <?php if (!empty($a['catatan_terakhir'])): ?>
                  <p class="text-[11px] text-slate-600 dark:text-slate-300 italic">"<?= sanitize($a['catatan_terakhir']) ?>"</p>
                <?php endif; ?>
              </div>
            <?php elseif (!empty($a['catatan_terakhir'])): ?>
              <p class="text-[11px] text-slate-400 mt-1.5">Catatan terakhir: <?= sanitize($a['catatan_terakhir']) ?></p>
            <?php endif; ?>
          </div>

          <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
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
        <button type="button" onclick="document.getElementById('addAssetModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] text-white font-extrabold cursor-pointer">Simpan Item</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
