<?php
// master/shift.php - Kelola Master Shift Operasional
$current_page = 'master_shift';
$page_title = 'Master Shift Operasional';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Handle Add / Edit / Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama_shift'] ?? '');
    $mulai    = $_POST['jam_mulai'] ?? '07:00';
    $selesai  = $_POST['jam_selesai'] ?? '15:00';
    $handover = $_POST['jam_serah_terima'] ?? $selesai;
    $ket      = trim($_POST['keterangan'] ?? '');

    if ($action === 'add' && !empty($nama)) {
        $stmt = $pdo->prepare("INSERT INTO master_shift (nama_shift, jam_mulai, jam_selesai, jam_serah_terima, keterangan) VALUES (:nama, :mulai, :selesai, :handover, :ket)");
        $stmt->execute(['nama' => $nama, 'mulai' => $mulai, 'selesai' => $selesai, 'handover' => $handover, 'ket' => $ket]);
        flash_set('success', "Shift '{$nama}' berhasil ditambahkan.");
    } elseif ($action === 'edit' && $id > 0 && !empty($nama)) {
        $stmt = $pdo->prepare("UPDATE master_shift SET nama_shift = :nama, jam_mulai = :mulai, jam_selesai = :selesai, jam_serah_terima = :handover, keterangan = :ket WHERE id = :id");
        $stmt->execute(['nama' => $nama, 'mulai' => $mulai, 'selesai' => $selesai, 'handover' => $handover, 'ket' => $ket, 'id' => $id]);
        flash_set('success', "Shift '{$nama}' berhasil diperbarui.");
    } elseif ($action === 'toggle_status' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE master_shift SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = :id");
        $stmt->execute(['id' => $id]);
        flash_set('success', "Status shift berhasil diubah.");
    }
    // Invalidate Cache
    cache_delete('master_shift:active');
    cache_delete('master_shift:all');

    header('Location: ' . BASE_URL . 'master/shift.php');
    exit;
}

$shifts = $pdo->query("SELECT * FROM master_shift ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- Sub-Nav Tab Switcher: Shift, Lokasi, & Kondisi Operasional -->
  <div class="flex items-center gap-2 p-1.5 bg-[#ebebf7] dark:bg-[#1b1e30] rounded-2xl w-fit border border-slate-200/60 dark:border-slate-800 flex-wrap">
    <a href="<?= BASE_URL ?>master/shift.php" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 bg-white dark:bg-[#131522] text-[#6558f5] shadow-sm">
      <i class="ph-fill ph-clock text-sm"></i>
      <span>Jadwal Shift &amp; Jam Kerja</span>
    </a>
    <a href="<?= BASE_URL ?>master/lokasi.php" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-map-pin text-sm text-slate-400"></i>
      <span>Master Lokasi / Unit</span>
    </a>
    <a href="<?= BASE_URL ?>master/kondisi.php" 
       class="px-4 py-2 rounded-xl text-xs font-extrabold flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
      <i class="ph-fill ph-activity text-sm text-slate-400"></i>
      <span>Kondisi Operasional</span>
    </a>
  </div>

  <!-- Header & Stats Chips -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-clock"></i>
        <span>Manajemen Waktu Operasional</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-clock text-[#6558f5]"></i>
        <span>Master Jadwal Shift &amp; Waktu Serah Terima</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Pengaturan jadwal rotasi shift, rentang jam kerja, dan jam serah terima resmi operasional
      </p>
    </div>

    <!-- Stats Pills -->
    <div class="flex items-center gap-2.5 flex-wrap">
      <div class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5]"></span>
        <span class="text-slate-600 dark:text-slate-300">Total Shift:</span>
        <span class="text-[#6558f5] dark:text-indigo-400"><?= count($shifts) ?></span>
      </div>
    </div>
  </div>

  <!-- 2-Column Grid (4 Cols Form, 8 Cols Table) -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
    
    <!-- Form Tambah Shift (4 Cols) -->
    <div class="xl:col-span-4 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 widget-card">
      <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
        <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold shadow-sm">
          <i class="ph-bold ph-plus-circle"></i>
        </div>
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white leading-tight">Tambah Shift Baru</h3>
          <p class="text-[11px] text-slate-400">Atur rentang jam kerja &amp; serah terima</p>
        </div>
      </div>

      <form method="POST" action="" class="space-y-4">
        <input type="hidden" name="action" value="add" />
        
        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Shift *</label>
          <input type="text" name="nama_shift" required placeholder="Contoh: Pagi, Siang, Malam"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Jam Mulai</label>
            <input type="time" name="jam_mulai" id="input_jam_mulai" required value="07:00"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>
          <div>
            <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Jam Selesai</label>
            <input type="time" name="jam_selesai" id="input_jam_selesai" required value="15:00" onchange="syncHandoverDefault(this.value)"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">
            Jam Serah Terima Resmi *
          </label>
          <input type="time" name="jam_serah_terima" id="input_jam_handover" required value="15:00"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          <span class="text-[10px] text-slate-400 mt-1 block">Otomatis mengisi jam serah terima di formulir saat shift ini dipilih.</span>
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Keterangan Singkat</label>
          <input type="text" name="keterangan" placeholder="Misal: Rotasi normal 8 jam operasional"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <button type="submit" 
                class="w-full py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
          <i class="ph-bold ph-floppy-disk text-base"></i>
          <span>Simpan Shift</span>
        </button>
      </form>
    </div>

    <!-- Tabel Daftar Shift (8 Cols) -->
    <div class="xl:col-span-8 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden widget-card">
      <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5] animate-ping"></span>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">
            Daftar Jadwal Shift Terdaftar (<?= count($shifts) ?>)
          </h3>
        </div>
        <span class="text-[11px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full">
          rotasi 24 jam • jam kerja resmi
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="py-3.5 px-4 w-12 text-center">No</th>
              <th class="py-3.5 px-5">Nama Shift</th>
              <th class="py-3.5 px-4">Rentang Kerja</th>
              <th class="py-3.5 px-4">Jam Serah Terima</th>
              <th class="py-3.5 px-4">Keterangan</th>
              <th class="py-3.5 px-3 text-center">Status</th>
              <th class="py-3.5 px-4 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
            <?php foreach ($shifts as $idx => $s): ?>
              <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3.5 px-4 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                <td class="py-3.5 px-5">
                  <div class="font-extrabold text-[#181b2c] dark:text-white text-xs">
                    Shift <?= sanitize($s['nama_shift']) ?>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-mono text-[11px] font-bold bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300">
                    <i class="ph-bold ph-clock text-xs"></i>
                    <span><?= format_jam($s['jam_mulai']) ?> – <?= format_jam($s['jam_selesai']) ?></span>
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-mono text-[11px] font-extrabold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                    <i class="ph-bold ph-handshake text-xs"></i>
                    <span><?= format_jam($s['jam_serah_terima'] ?: $s['jam_selesai']) ?> WIB</span>
                  </span>
                </td>
                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                  <?= sanitize($s['keterangan'] ?: '—') ?>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $s['status'] === 'aktif' ? 'bg-[#e6f7f0] text-[#1c7a52] dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $s['status'] === 'aktif' ? 'bg-[#68cfa3]' : 'bg-slate-400' ?>"></span>
                    <?= strtoupper($s['status']) ?>
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button type="button" 
                            onclick='openEditShiftModal(<?= json_encode($s) ?>)'
                            class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 hover:bg-indigo-100 text-[11px] font-bold transition flex items-center gap-1">
                      <i class="ph-bold ph-pencil-simple"></i>
                      <span>Edit</span>
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Ubah status aktif shift ini?')">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="id" value="<?= $s['id'] ?>">
                      <button type="submit" class="px-2 py-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-[11px] font-bold transition">
                        <?= $s['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
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

<!-- Modal Edit Shift -->
<div id="modalEditShift" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeEditShiftModal()"></div>
  <div class="relative w-full max-w-md bg-white dark:bg-[#181b2c] rounded-3xl p-6 shadow-pop border border-slate-200/80 dark:border-slate-800 z-10 text-slate-800 dark:text-slate-100">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
      <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
        <i class="ph-bold ph-pencil-simple text-[#6558f5]"></i>
        <span>Edit Jadwal Shift</span>
      </h3>
      <button type="button" onclick="closeEditShiftModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">

      <div>
        <label class="block text-xs font-bold mb-1">Nama Shift *</label>
        <input type="text" name="nama_shift" id="edit_nama" required
               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-semibold">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold mb-1">Jam Mulai</label>
          <input type="time" name="jam_mulai" id="edit_mulai" required
                 class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-semibold">
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Jam Selesai</label>
          <input type="time" name="jam_selesai" id="edit_selesai" required
                 class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-semibold">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold mb-1">Jam Serah Terima Resmi *</label>
        <input type="time" name="jam_serah_terima" id="edit_handover" required
               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-semibold">
      </div>

      <div>
        <label class="block text-xs font-bold mb-1">Keterangan</label>
        <input type="text" name="keterangan" id="edit_ket"
               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs font-semibold">
      </div>

      <div class="pt-3 flex justify-end gap-2">
        <button type="button" onclick="closeEditShiftModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold">
          Batal
        </button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-extrabold shadow-md">
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function syncHandoverDefault(val) {
  const handoverInput = document.getElementById('input_jam_handover');
  if (handoverInput && !handoverInput.dataset.touched) {
    handoverInput.value = val;
  }
}
document.getElementById('input_jam_handover').addEventListener('input', function() {
  this.dataset.touched = 'true';
});

function openEditShiftModal(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_nama').value = data.nama_shift;
  document.getElementById('edit_mulai').value = data.jam_mulai.substring(0, 5);
  document.getElementById('edit_selesai').value = data.jam_selesai.substring(0, 5);
  document.getElementById('edit_handover').value = (data.jam_serah_terima || data.jam_selesai).substring(0, 5);
  document.getElementById('edit_ket').value = data.keterangan || '';
  document.getElementById('modalEditShift').classList.remove('hidden');
}

function closeEditShiftModal() {
  document.getElementById('modalEditShift').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
