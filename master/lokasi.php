<?php
// master/lokasi.php - Kelola Master Lokasi / Unit
$current_page = 'master_lokasi';
$page_title = 'Master Lokasi & Unit';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Handle Add / Edit / Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nama = trim($_POST['nama_lokasi'] ?? '');
        $ket  = trim($_POST['keterangan'] ?? '');
        if (!empty($nama)) {
            $stmt = $pdo->prepare("INSERT INTO master_lokasi (nama_lokasi, keterangan) VALUES (:nama, :ket)");
            $stmt->execute(['nama' => $nama, 'ket' => $ket]);
            flash_set('success', "Lokasi '{$nama}' berhasil ditambahkan.");
        }
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_lokasi'] ?? '');
        $ket  = trim($_POST['keterangan'] ?? '');
        if ($id > 0 && !empty($nama)) {
            $stmt = $pdo->prepare("UPDATE master_lokasi SET nama_lokasi = :nama, keterangan = :ket WHERE id = :id");
            $stmt->execute(['nama' => $nama, 'ket' => $ket, 'id' => $id]);
            flash_set('success', "Lokasi '{$nama}' berhasil diperbarui.");
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE master_lokasi SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', "Status lokasi berhasil diubah.");
        }
    }
    // Invalidate Cache
    cache_delete('master_lokasi:active');
    cache_delete('master_lokasi:all');

    header('Location: ' . BASE_URL . 'master/lokasi');
    exit;
}

$lokasis = $pdo->query("SELECT * FROM master_lokasi ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$totalAktif = 0;
foreach ($lokasis as $l) {
    if ($l['status'] === 'aktif') $totalAktif++;
}
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Sub-Nav Tab Switcher: Shift, Lokasi, & Kondisi Operasional -->
  <div class="flex items-center gap-2 p-1.5 bg-[#ebebf7] dark:bg-[#1b1e30] rounded-2xl w-fit border border-slate-200/60 dark:border-slate-800 flex-wrap">
    <a href="<?= BASE_URL ?>master/shift" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-clock text-sm text-slate-400"></i>
      <span>Jadwal Shift &amp; Jam Kerja</span>
    </a>
    <a href="<?= BASE_URL ?>master/lokasi" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 bg-white dark:bg-[#131522] text-[#6558f5] shadow-sm">
      <i class="ph-fill ph-map-pin text-sm"></i>
      <span>Master Lokasi / Unit</span>
    </a>
    <a href="<?= BASE_URL ?>master/kondisi" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-activity text-sm text-slate-400"></i>
      <span>Kondisi Operasional</span>
    </a>
  </div>

  <!-- HEADER & STATS CHIPS -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-map-pin"></i>
        <span>Area Kerja Operasional</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-map-pin text-[#6558f5]"></i>
        <span>Master Data Lokasi / Unit</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Daftar stasiun kerja, pos pemantauan, dan unit serah terima shift
      </p>
    </div>

    <!-- Stats Pills -->
    <div class="flex items-center gap-2.5 flex-wrap">
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5]"></span>
        <span class="text-slate-600 dark:text-slate-300">Total Unit:</span>
        <span class="text-[#6558f5] dark:text-indigo-400"><?= count($lokasis) ?></span>
      </div>
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#68cfa3]"></span>
        <span class="text-slate-600 dark:text-slate-300">Aktif:</span>
        <span class="text-emerald-600 dark:text-emerald-400"><?= $totalAktif ?></span>
      </div>
    </div>
  </div>

  <!-- 2-COLUMN GRID (4 COLS FORM, 8 COLS TABLE) -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
    
    <!-- Form Tambah Lokasi (4 Cols) -->
    <div class="xl:col-span-4 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 widget-card">
      <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
        <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold shadow-sm">
          <i class="ph-bold ph-plus-circle"></i>
        </div>
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white leading-tight">Tambah Lokasi Baru</h3>
          <p class="text-[11px] text-slate-400">Daftarkan nama area operasional baru</p>
        </div>
      </div>

      <form method="POST" action="" class="space-y-4">
        <input type="hidden" name="action" value="add" />
        
        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Lokasi / Unit *</label>
          <input type="text" name="nama_lokasi" required placeholder="Contoh: Area Produksi C"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan / Fungsi Area</label>
          <textarea name="keterangan" rows="3" placeholder="Deskripsi area kerja atau lingkup operasional..."
                    class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition"></textarea>
        </div>

        <button type="submit" 
                class="w-full py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
          <i class="ph-bold ph-floppy-disk text-base"></i>
          <span>Simpan Lokasi</span>
        </button>
      </form>
    </div>

    <!-- Tabel Daftar Lokasi (8 Cols) -->
    <div class="xl:col-span-8 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden widget-card">
      <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5] animate-ping"></span>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">
            Daftar Lokasi Terdaftar (<?= count($lokasis) ?>)
          </h3>
        </div>
        <span class="text-[11px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full">
          pos lapangan • ruang kontrol
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="py-3.5 px-4 w-12 text-center">No</th>
              <th class="py-3.5 px-5">Nama Lokasi / Unit</th>
              <th class="py-3.5 px-5">Keterangan</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-5 text-center">Tindakan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
            <?php foreach ($lokasis as $idx => $l): ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3.5 px-4 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                <td class="py-3.5 px-5">
                  <div class="font-extrabold text-[#181b2c] dark:text-white text-xs">
                    <?= sanitize($l['nama_lokasi']) ?>
                  </div>
                </td>
                <td class="py-3.5 px-5 text-slate-600 dark:text-slate-300">
                  <?= sanitize($l['keterangan'] ?: '—') ?>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $l['status'] === 'aktif' ? 'bg-[#e6f7f0] text-[#1c7a52] dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $l['status'] === 'aktif' ? 'bg-[#68cfa3]' : 'bg-slate-400' ?>"></span>
                    <?= strtoupper($l['status']) ?>
                  </span>
                </td>
                <td class="py-3.5 px-5 text-center whitespace-nowrap">
                  <div class="inline-flex items-center justify-center gap-1.5">
                    <button type="button"
                            onclick='openEditLokasiModal(<?= json_encode(["id" => $l["id"], "nama_lokasi" => $l["nama_lokasi"], "keterangan" => $l["keterangan"] ?? ""], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            title="Edit Nama &amp; Keterangan Lokasi"
                            class="px-2.5 py-1.5 rounded-xl text-[10.5px] font-extrabold bg-[#efeffb] hover:bg-[#e0dffc] dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-[#6558f5] dark:text-indigo-300 active:scale-95 transition flex items-center gap-1 cursor-pointer border border-indigo-200/50 dark:border-indigo-800/50">
                      <i class="ph-bold ph-pencil-simple text-xs"></i>
                      <span>Edit</span>
                    </button>

                    <form method="POST" action="" class="inline" onsubmit="return confirm('Ubah status lokasi ini?')">
                      <input type="hidden" name="action" value="toggle" />
                      <input type="hidden" name="id" value="<?= $l['id'] ?>" />
                      <button type="submit" 
                              class="px-2.5 py-1.5 rounded-xl text-[10.5px] font-extrabold <?= $l['status'] === 'aktif' ? 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700' : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800' ?> active:scale-95 transition cursor-pointer">
                        <?= $l['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Modal Edit Lokasi / Unit -->
<div id="editLokasiModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeEditLokasiModal()"></div>
  <div class="relative z-10 bg-white dark:bg-[#1b1e30] rounded-[26px] max-w-md w-full max-h-[90vh] overflow-y-auto p-6 border border-slate-100 dark:border-slate-800 shadow-2xl">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl">
          <i class="ph-bold ph-pencil-simple"></i>
        </div>
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">Edit Lokasi / Unit</h3>
          <p class="text-[11px] text-slate-400">Perbarui nama area atau deskripsi unit</p>
        </div>
      </div>
      <button type="button" onclick="closeEditLokasiModal()" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white transition cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="edit" />
      <input type="hidden" name="id" id="editLokasiId" />

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Lokasi / Unit *</label>
        <input type="text" name="nama_lokasi" id="editLokasiNama" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
      </div>

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan / Fungsi Area</label>
        <textarea name="keterangan" id="editLokasiKet" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition"></textarea>
      </div>

      <div class="flex items-center gap-2.5 pt-2">
        <button type="button" onclick="closeEditLokasiModal()"
                class="flex-1 py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-extrabold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit"
                class="flex-1 py-2.5 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
          <i class="ph-bold ph-check-circle text-base"></i>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  function openEditLokasiModal(data) {
    document.getElementById('editLokasiId').value = data.id;
    document.getElementById('editLokasiNama').value = data.nama_lokasi;
    document.getElementById('editLokasiKet').value = data.keterangan || '';
    document.getElementById('editLokasiModal').classList.remove('hidden');
    document.getElementById('editLokasiNama').focus({ preventScroll: true });
  }
  function closeEditLokasiModal() {
    document.getElementById('editLokasiModal').classList.add('hidden');
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

