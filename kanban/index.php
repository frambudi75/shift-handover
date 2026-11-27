<?php
// kanban/index.php - Papan Kanban Tugas & Catatan Pribadi (100% Privat Per Akun)
$current_page = 'kanban';
$page_title = 'Kanban & Catatan Pribadi';
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
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_task') {
        $judul = trim($_POST['judul'] ?? '');
        if ($judul !== '') {
            $prioVal = $_POST['prioritas'] ?? 'Sedang';
            $statVal = $_POST['status'] ?? 'Belum Mulai';
            $pdo->prepare("INSERT INTO personal_kanban_tasks (user_id, judul, deskripsi, prioritas, status, target_tanggal) VALUES (:uid, :j, :d, :p, :s, :t)")
                ->execute([
                    'uid' => $userId,
                    'j'   => mb_substr($judul, 0, 200),
                    'd'   => trim($_POST['deskripsi'] ?? ''),
                    'p'   => in_array($prioVal, ['Tinggi', 'Sedang', 'Rendah'], true) ? $prioVal : 'Sedang',
                    's'   => in_array($statVal, ['Belum Mulai', 'Berjalan', 'Selesai'], true) ? $statVal : 'Belum Mulai',
                    't'   => !empty($_POST['target_tanggal']) ? $_POST['target_tanggal'] : null,
                ]);
            flash_set('success', 'Tugas pribadi berhasil ditambahkan.');
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

    header('Location: ' . BASE_URL . 'kanban/index');
    exit;
}

// Strictly fetch ONLY current user's tasks & notes
$stmtT = $pdo->prepare("SELECT * FROM personal_kanban_tasks WHERE user_id = :uid ORDER BY FIELD(prioritas,'Tinggi','Sedang','Rendah'), id DESC");
$stmtT->execute(['uid' => $userId]);
$allTasks = $stmtT->fetchAll();

$columns = ['Belum Mulai' => [], 'Berjalan' => [], 'Selesai' => []];
foreach ($allTasks as $tk) {
    $columns[$tk['status']][] = $tk;
}

$stmtN = $pdo->prepare("SELECT * FROM personal_notes WHERE user_id = :uid ORDER BY updated_at DESC, id DESC");
$stmtN->execute(['uid' => $userId]);
$notes = $stmtN->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 w-full animate-stagger-1">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-lock-key"></i>
        <span>Ruang Kerja Privat (Hanya Anda yang Bisa Melihat)</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-kanban text-[#6558f5]"></i>
        <span>Kanban Tugas &amp; Catatan Pribadi</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Kelola daftar tugas pribadi dan catatan tempel harian khusus untuk akun <strong><?= sanitize($user['nama_lengkap']) ?></strong>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <button type="button" onclick="document.getElementById('addTaskModal').classList.remove('hidden')"
              class="px-4 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-plus"></i>
        <span>Tugas Baru</span>
      </button>
      <button type="button" onclick="openNoteModal()"
              class="px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-note-pencil"></i>
        <span>Catatan Baru</span>
      </button>
    </div>
  </div>

  <!-- 3-Column Personal Kanban Board -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
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
                  <form method="POST" action="" onsubmit="return confirm('Hapus tugas ini?')">
                    <input type="hidden" name="action" value="delete_task" />
                    <input type="hidden" name="task_id" value="<?= $item['id'] ?>" />
                    <button type="submit" class="text-slate-400 hover:text-rose-500 p-0.5 cursor-pointer" title="Hapus">
                      <i class="ph-bold ph-trash text-xs"></i>
                    </button>
                  </form>
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

  <!-- Personal Sticky Notes -->
  <div class="bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-5 sm:p-7 space-y-4">
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
        Belum ada catatan pribadi. Klik <strong>Catatan Baru</strong> di kanan atas untuk membuat.
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

<!-- Modal Tambah Tugas Pribadi -->
<div id="addTaskModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('addTaskModal').classList.add('hidden')"></div>
  <div class="relative z-10 w-full max-w-md bg-white dark:bg-[#1b1e30] rounded-[26px] p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Tambah Tugas Kanban Pribadi</h3>
    <form method="POST" action="" class="space-y-3 text-xs">
      <input type="hidden" name="action" value="add_task" />
      <div>
        <label class="block font-bold mb-1">Judul Tugas</label>
        <input type="text" name="judul" required placeholder="Contoh: Cek ulang patch server NVR" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
      </div>
      <div>
        <label class="block font-bold mb-1">Deskripsi (Opsional)</label>
        <textarea name="deskripsi" rows="2" class="w-full px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold mb-1">Prioritas</label>
          <select name="prioritas" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-bold">
            <option value="Tinggi">Tinggi</option>
            <option value="Sedang" selected>Sedang</option>
            <option value="Rendah">Rendah</option>
          </select>
        </div>
        <div>
          <label class="block font-bold mb-1">Target Tanggal</label>
          <input type="date" name="target_tanggal" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('addTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold">Batal</button>
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
        <button type="button" onclick="document.getElementById('noteModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-extrabold cursor-pointer">Simpan Catatan</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openNoteModal(nt) {
    document.getElementById('note_id').value = nt ? nt.id : 0;
    document.getElementById('note_judul').value = nt ? nt.judul : '';
    document.getElementById('note_isi').value = nt ? nt.isi : '';
    document.getElementById('note_warna').value = nt ? nt.warna : 'indigo';
    document.getElementById('noteModalTitle').textContent = nt ? 'Edit Catatan Pribadi' : 'Tambah Catatan Pribadi';
    document.getElementById('noteModal').classList.remove('hidden');
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
