<?php
// master/kondisi.php - Kelola Master Pilihan Kondisi Operasional Umum
$current_page = 'master_kondisi';
$page_title = 'Master Kondisi Operasional';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Handle Form Submissions (Add / Edit / Toggle / Set Default / Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nama  = trim($_POST['nama_kondisi'] ?? '');
        $color = trim($_POST['badge_color'] ?? 'emerald');
        $ket   = trim($_POST['keterangan'] ?? '');
        $isDef = !empty($_POST['is_default']) ? 1 : 0;

        if (!empty($nama)) {
            // Cek duplikasi
            $check = $pdo->prepare("SELECT id FROM master_kondisi WHERE nama_kondisi = :nama");
            $check->execute(['nama' => $nama]);
            if ($check->fetch()) {
                flash_set('error', "Kondisi operasional '{$nama}' sudah ada di database!");
            } else {
                if ($isDef) {
                    $pdo->exec("UPDATE master_kondisi SET is_default = 0");
                }
                $stmt = $pdo->prepare("INSERT INTO master_kondisi (nama_kondisi, badge_color, keterangan, is_default, status) VALUES (:nama, :color, :ket, :def, 'aktif')");
                $stmt->execute(['nama' => $nama, 'color' => $color, 'ket' => $ket, 'def' => $isDef]);
                flash_set('success', "Pilihan kondisi operasional '{$nama}' berhasil ditambahkan.");
            }
        } else {
            flash_set('error', 'Nama kondisi operasional tidak boleh kosong!');
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $nama  = trim($_POST['nama_kondisi'] ?? '');
        $color = trim($_POST['badge_color'] ?? 'emerald');
        $ket   = trim($_POST['keterangan'] ?? '');
        $isDef = !empty($_POST['is_default']) ? 1 : 0;

        if ($id > 0 && !empty($nama)) {
            // Cek duplikasi nama selain id saat ini
            $check = $pdo->prepare("SELECT id FROM master_kondisi WHERE nama_kondisi = :nama AND id != :id");
            $check->execute(['nama' => $nama, 'id' => $id]);
            if ($check->fetch()) {
                flash_set('error', "Kondisi operasional '{$nama}' sudah digunakan oleh data lain!");
            } else {
                if ($isDef) {
                    $pdo->exec("UPDATE master_kondisi SET is_default = 0");
                }
                $stmt = $pdo->prepare("UPDATE master_kondisi SET nama_kondisi = :nama, badge_color = :color, keterangan = :ket, is_default = :def WHERE id = :id");
                $stmt->execute(['nama' => $nama, 'color' => $color, 'ket' => $ket, 'def' => $isDef, 'id' => $id]);
                flash_set('success', "Pilihan kondisi '{$nama}' berhasil diperbarui.");
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE master_kondisi SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', "Status aktif pilihan kondisi operasional berhasil diubah.");
        }
    } elseif ($action === 'set_default') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->exec("UPDATE master_kondisi SET is_default = 0");
            $stmt = $pdo->prepare("UPDATE master_kondisi SET is_default = 1, status = 'aktif' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash_set('success', "Kondisi default formulir berhasil ditetapkan.");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmtK = $pdo->prepare("SELECT nama_kondisi, is_default FROM master_kondisi WHERE id = :id");
            $stmtK->execute(['id' => $id]);
            $kondisi = $stmtK->fetch();

            if ($kondisi) {
                if ($kondisi['is_default']) {
                    flash_set('error', "Pilihan kondisi '{$kondisi['nama_kondisi']}' adalah default saat ini. Tetapkan default lain sebelum menghapus!");
                } else {
                    // Cek apakah dipakai di laporan_serah_terima
                    $checkUsed = $pdo->prepare("SELECT COUNT(*) FROM laporan_serah_terima WHERE kondisi_operasional = :nama");
                    $checkUsed->execute(['nama' => $kondisi['nama_kondisi']]);
                    $usedCount = $checkUsed->fetchColumn();

                    if ($usedCount > 0) {
                        flash_set('warning', "Kondisi '{$kondisi['nama_kondisi']}' telah digunakan pada {$usedCount} laporan serah terima. Disarankan mengubah statusnya menjadi 'Nonaktif' daripada menghapusnya.");
                    } else {
                        $stmtDel = $pdo->prepare("DELETE FROM master_kondisi WHERE id = :id");
                        $stmtDel->execute(['id' => $id]);
                        flash_set('success', "Kondisi operasional '{$kondisi['nama_kondisi']}' berhasil dihapus.");
                    }
                }
            }
        }
    }
    // Invalidate Redis Cache setelah perubahan master kondisi
    cache_delete('master_kondisi:active');
    cache_delete('master_kondisi:all');
    cache_delete('master_kondisi:map');

    header('Location: ' . BASE_URL . 'master/kondisi');
    exit;
}

$kondisis = $pdo->query("SELECT * FROM master_kondisi ORDER BY is_default DESC, urutan ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

$totalAktif = 0;
$defaultKondisi = 'Normal';
foreach ($kondisis as $k) {
    if ($k['status'] === 'aktif') $totalAktif++;
    if ($k['is_default']) $defaultKondisi = $k['nama_kondisi'];
}
?>

<div class="space-y-6 w-full animate-stagger-1 pb-12">

  <!-- Sub-Nav Tab Switcher: Shift, Lokasi, & Kondisi Operasional -->
  <div class="flex items-center gap-2 p-1.5 bg-[#ebebf7] dark:bg-[#1b1e30] rounded-2xl w-fit border border-slate-200/60 dark:border-slate-800 flex-wrap">
    <a href="<?= BASE_URL ?>master/shift" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-clock text-sm text-slate-400"></i>
      <span>Jadwal Shift &amp; Jam Kerja</span>
    </a>
    <a href="<?= BASE_URL ?>master/lokasi" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-map-pin text-sm text-slate-400"></i>
      <span>Master Lokasi / Unit</span>
    </a>
    <a href="<?= BASE_URL ?>master/kondisi" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 bg-white dark:bg-[#131522] text-[#6558f5] shadow-sm">
      <i class="ph-fill ph-activity text-sm"></i>
      <span>Kondisi Operasional</span>
    </a>
  </div>

  <!-- HEADER & STATS CHIPS -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-sliders"></i>
        <span>Parameter Status Lapangan</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-activity text-[#6558f5]"></i>
        <span>Pengaturan Kondisi Operasional Umum</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Kelola pilihan status kondisi operasional umum yang muncul pada formulir serah terima shift
      </p>
    </div>

    <!-- Actions & Stats Pills -->
    <div class="flex items-center gap-2.5 flex-wrap">
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5]"></span>
        <span class="text-slate-600 dark:text-slate-300">Total Pilihan:</span>
        <span class="text-[#6558f5] dark:text-indigo-400"><?= count($kondisis) ?></span>
      </div>
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#68cfa3]"></span>
        <span class="text-slate-600 dark:text-slate-300">Aktif:</span>
        <span class="text-emerald-600 dark:text-emerald-400"><?= $totalAktif ?></span>
      </div>
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
        <span class="text-slate-600 dark:text-slate-300">Default:</span>
        <span class="text-amber-600 dark:text-amber-400"><?= sanitize($defaultKondisi) ?></span>
      </div>

      <button type="button" onclick="openAddModal()" 
              class="px-4 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
        <i class="ph-bold ph-plus text-sm"></i>
        <span>Tambah Kondisi Baru</span>
      </button>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 widget-card">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3 px-4 rounded-l-xl w-14 text-center">No</th>
            <th class="py-3 px-4">Tampilan Badge</th>
            <th class="py-3 px-4">Nama Kondisi Operasional</th>
            <th class="py-3 px-4">Warna Tema</th>
            <th class="py-3 px-4">Keterangan / Indikasi</th>
            <th class="py-3 px-4 text-center">Status Formulir</th>
            <th class="py-3 px-4 text-center rounded-r-xl">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
          <?php if (empty($kondisis)): ?>
            <tr>
              <td colspan="7" class="py-8 text-center text-slate-400 font-bold">
                Belum ada pilihan kondisi operasional. Tambahkan pilihan baru di atas.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kondisis as $idx => $k): ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition">
                <td class="py-3.5 px-4 text-center text-slate-400 font-bold">
                  <?= $idx + 1 ?>
                </td>
                <td class="py-3.5 px-4 font-bold">
                  <?= badge_kondisi_operasional($k['nama_kondisi']) ?>
                </td>
                <td class="py-3.5 px-4 font-black text-slate-900 dark:text-white">
                  <div class="flex items-center gap-2">
                    <span><?= sanitize($k['nama_kondisi']) ?></span>
                    <?php if ($k['is_default']): ?>
                      <span class="px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 text-[9.5px] font-black border border-amber-300 dark:border-amber-800 flex items-center gap-1">
                        <i class="ph-fill ph-star"></i>
                        <span>DEFAULT FORM</span>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-slate-600 dark:text-slate-300">
                  <span class="capitalize"><?= sanitize($k['badge_color']) ?></span>
                </td>
                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 max-w-xs">
                  <?= sanitize($k['keterangan'] ?: '-') ?>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <form method="POST" action="" class="inline">
                    <input type="hidden" name="action" value="toggle" />
                    <input type="hidden" name="id" value="<?= $k['id'] ?>" />
                    <button type="submit" 
                            class="px-3 py-1 rounded-full text-[10px] font-black transition cursor-pointer <?= $k['status'] === 'aktif' ? 'bg-[#e6f7f0] text-[#1c7a52] hover:bg-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-400' ?>">
                      <?= strtoupper($k['status']) ?>
                    </button>
                  </form>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <?php if (!$k['is_default']): ?>
                      <form method="POST" action="" class="inline" title="Jadikan Pilihan Default Formulir">
                        <input type="hidden" name="action" value="set_default" />
                        <input type="hidden" name="id" value="<?= $k['id'] ?>" />
                        <button type="submit" class="p-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:hover:bg-amber-900/60 dark:text-amber-300 transition cursor-pointer">
                          <i class="ph-bold ph-star text-sm"></i>
                        </button>
                      </form>
                    <?php endif; ?>

                    <button type="button" 
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($k), ENT_QUOTES, 'UTF-8') ?>)"
                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer"
                            title="Edit Kondisi">
                      <i class="ph-bold ph-pencil text-sm"></i>
                    </button>

                    <?php if (!$k['is_default']): ?>
                      <button type="button" 
                              onclick="openDeleteModal(<?= $k['id'] ?>, '<?= addslashes(sanitize($k['nama_kondisi'])) ?>')"
                              class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 dark:text-rose-300 transition cursor-pointer"
                              title="Hapus Kondisi">
                        <i class="ph-bold ph-trash text-sm"></i>
                      </button>
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

<!-- MODAL TAMBAH KONDISI -->
<div id="addModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAddModal()"></div>
  <div class="relative w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[28px] shadow-2xl border border-slate-100 dark:border-slate-800 p-6 z-10 space-y-4 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold">
          <i class="ph-bold ph-plus"></i>
        </div>
        <div>
          <h3 class="font-extrabold text-sm text-[#181b2c] dark:text-white">Tambah Kondisi Operasional Baru</h3>
          <p class="text-[11px] text-slate-400">Pilihan opsi status untuk formulir serah terima</p>
        </div>
      </div>
      <button type="button" onclick="closeAddModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-slate-400 transition">
        <i class="ph-bold ph-x text-base"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="add" />

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Nama Kondisi Operasional *
        </label>
        <input type="text" name="nama_kondisi" required placeholder="Misal: Siaga Operasional, Maintenance, dsb."
               class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
      </div>

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Warna Badge / Tema
        </label>
        <select name="badge_color" class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer">
          <option value="emerald">🟢 Emerald / Hijau (Kondisi Aman / Normal)</option>
          <option value="amber">🟡 Amber / Kuning (Perlu Pengawasan / Waspada)</option>
          <option value="rose">🔴 Rose / Merah (Gangguan / Kritis / Bahaya)</option>
          <option value="indigo">🟣 Indigo / Biru Gelap (Informasi / Siaga)</option>
          <option value="purple">🟣 Purple / Ungu (Khusus / Evaluasi)</option>
          <option value="blue">🔵 Blue / Biru Cerah (Pemeliharaan / Maintenance)</option>
          <option value="slate">⚪ Slate / Abu-Abu (Netral)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Keterangan / Panduan Kondisi
        </label>
        <textarea name="keterangan" rows="2" placeholder="Jelaskan kapan kondisi ini dipilih oleh petugas shift..."
                  class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition"></textarea>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" name="is_default" id="chkAddDefault" value="1" class="rounded border-slate-300 text-[#6558f5] focus:ring-[#6558f5] cursor-pointer" />
        <label for="chkAddDefault" class="text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
          Jadikan kondisi terpilih secara default saat buat laporan baru
        </label>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2">
        <button type="button" onclick="closeAddModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-xs transition">
          Batal
        </button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition">
          Simpan Kondisi
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT KONDISI -->
<div id="editModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeEditModal()"></div>
  <div class="relative w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[28px] shadow-2xl border border-slate-100 dark:border-slate-800 p-6 z-10 space-y-4 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold">
          <i class="ph-bold ph-pencil"></i>
        </div>
        <div>
          <h3 class="font-extrabold text-sm text-[#181b2c] dark:text-white">Edit Kondisi Operasional</h3>
          <p class="text-[11px] text-slate-400">Perbarui nama, warna badge, atau keterangan</p>
        </div>
      </div>
      <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-slate-400 transition">
        <i class="ph-bold ph-x text-base"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="edit" />
      <input type="hidden" name="id" id="editKondisiId" value="" />

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Nama Kondisi Operasional *
        </label>
        <input type="text" name="nama_kondisi" id="editNamaKondisi" required
               class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
      </div>

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Warna Badge / Tema
        </label>
        <select name="badge_color" id="editBadgeColor" class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer">
          <option value="emerald">🟢 Emerald / Hijau (Kondisi Aman / Normal)</option>
          <option value="amber">🟡 Amber / Kuning (Perlu Pengawasan / Waspada)</option>
          <option value="rose">🔴 Rose / Merah (Gangguan / Kritis / Bahaya)</option>
          <option value="indigo">🟣 Indigo / Biru Gelap (Informasi / Siaga)</option>
          <option value="purple">🟣 Purple / Ungu (Khusus / Evaluasi)</option>
          <option value="blue">🔵 Blue / Biru Cerah (Pemeliharaan / Maintenance)</option>
          <option value="slate">⚪ Slate / Abu-Abu (Netral)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1">
          Keterangan / Panduan Kondisi
        </label>
        <textarea name="keterangan" id="editKeterangan" rows="2"
                  class="w-full px-3.5 py-2 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition"></textarea>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" name="is_default" id="editIsDefault" value="1" class="rounded border-slate-300 text-[#6558f5] focus:ring-[#6558f5] cursor-pointer" />
        <label for="editIsDefault" class="text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
          Jadikan kondisi terpilih secara default saat buat laporan baru
        </label>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2">
        <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-xs transition">
          Batal
        </button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition">
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS KONDISI -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
  <div class="relative w-full max-w-sm bg-white dark:bg-[#1b1e30] rounded-[28px] shadow-2xl border border-slate-100 dark:border-slate-800 p-6 z-10 space-y-4 animate-stagger-1 text-center">
    <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 flex items-center justify-center font-bold text-2xl mx-auto shadow-sm">
      <i class="ph-bold ph-trash"></i>
    </div>
    <div>
      <h3 class="font-extrabold text-base text-[#181b2c] dark:text-white">Hapus Pilihan Kondisi?</h3>
      <p class="text-xs text-slate-400 mt-1">
        Pilihan kondisi <strong id="deleteLabel" class="text-slate-700 dark:text-slate-200"></strong> akan dihapus permanen.
      </p>
    </div>

    <form method="POST" action="" class="pt-2 flex items-center justify-center gap-2">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="id" id="deleteId" value="" />
      <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-xs transition">
        Batal
      </button>
      <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md transition">
        Hapus Kondisi
      </button>
    </form>
  </div>
</div>

<script>
  function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
  }
  function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
  }

  function openEditModal(data) {
    document.getElementById('editKondisiId').value = data.id;
    document.getElementById('editNamaKondisi').value = data.nama_kondisi;
    document.getElementById('editBadgeColor').value = data.badge_color || 'emerald';
    document.getElementById('editKeterangan').value = data.keterangan || '';
    document.getElementById('editIsDefault').checked = data.is_default == 1;
    document.getElementById('editModal').classList.remove('hidden');
  }
  function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
  }

  function openDeleteModal(id, label) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteLabel').innerText = `"${label}"`;
    document.getElementById('deleteModal').classList.remove('hidden');
  }
  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
