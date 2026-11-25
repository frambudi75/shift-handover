<?php
// notulen/create.php - Catat & Edit Notulen Meeting / Briefing (Semua User Dapat Akses & Update)
$current_page = 'notulen';
$page_title = 'Catat Notulen Meeting / Briefing';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notulen_helper.php';

require_login();
$user = current_user();
ensure_notulen_tables($pdo);

$masterUsers  = $pdo->query("SELECT id, nama_lengkap, jabatan, role FROM users WHERE status = 'aktif' ORDER BY nama_lengkap ASC")->fetchAll();
$masterLokasi = get_master_lokasi(true);

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
$editItems = [];

if ($editId > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM notulen_meeting WHERE id = :id LIMIT 1");
    $stmtE->execute(['id' => $editId]);
    $editRow = $stmtE->fetch();
    if (!$editRow) {
        flash_set('error', 'Notulen tidak ditemukan.');
        header('Location: ' . BASE_URL . 'notulen/index');
        exit;
    }
    $stmtI = $pdo->prepare("SELECT * FROM notulen_action_items WHERE notulen_id = :id ORDER BY urutan ASC, id ASC");
    $stmtI->execute(['id' => $editId]);
    $editItems = $stmtI->fetchAll();
}

$nomorOtomatis = $editRow ? $editRow['nomor_notulen'] : generate_nomor_notulen($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $postEditId   = (int)($_POST['edit_id'] ?? 0);
        $submitAction = ($_POST['submit_action'] ?? 'final') === 'draft' ? 'draft' : 'final';
        $status       = $submitAction === 'draft' ? 'DRAFT' : 'FINAL';

        $existing = null;
        if ($postEditId > 0) {
            $stmtEx = $pdo->prepare("SELECT * FROM notulen_meeting WHERE id = :id LIMIT 1");
            $stmtEx->execute(['id' => $postEditId]);
            $existing = $stmtEx->fetch();
            if (!$existing) {
                throw new Exception('Data notulen tidak ditemukan.');
            }
        }

        $nomor_notulen     = $existing ? $existing['nomor_notulen'] : trim($_POST['nomor_notulen'] ?? $nomorOtomatis);
        $judul             = trim($_POST['judul'] ?? '');
        $jenis             = $_POST['jenis'] ?? 'Briefing Shift';
        $tanggal           = $_POST['tanggal'] ?? date('Y-m-d');
        $jam_mulai         = $_POST['jam_mulai'] ?? date('H:i');
        $jam_selesai       = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
        $lokasi            = trim($_POST['lokasi'] ?? 'Control Room');
        $pimpinan_rapat    = trim($_POST['pimpinan_rapat'] ?? $user['nama_lengkap']);
        $pesertaIds        = isset($_POST['peserta_ids']) && is_array($_POST['peserta_ids'])
                             ? array_values(array_unique(array_map('intval', $_POST['peserta_ids'])))
                             : [];
        $peserta_eksternal = trim($_POST['peserta_eksternal'] ?? '');
        $agenda            = trim($_POST['agenda'] ?? '');
        $pembahasan        = trim($_POST['pembahasan'] ?? '');
        $keputusan         = trim($_POST['keputusan'] ?? '');

        if ($judul === '') {
            $judul = $status === 'DRAFT' ? 'Draft Notulen (' . $tanggal . ')' : throw new Exception('Judul / Topik rapat wajib diisi.');
        }

        $lampiranNew  = isset($_FILES['lampiran_foto']) ? upload_foto($_FILES['lampiran_foto'], 'notulen_') : null;
        $lampiranFoto = $lampiranNew ?: ($existing['lampiran_foto'] ?? null);

        $shouldNotify = ($status === 'FINAL') && (!$existing || empty($existing['notified_at']));
        $notifiedAt   = $shouldNotify ? date('Y-m-d H:i:s') : ($existing['notified_at'] ?? null);

        if ($existing) {
            $stmtUp = $pdo->prepare("
                UPDATE notulen_meeting SET
                  judul = :judul, jenis = :jenis, tanggal = :tanggal, jam_mulai = :jam_mulai, jam_selesai = :jam_selesai,
                  lokasi = :lokasi, pimpinan_rapat = :pimpinan, peserta_ids = :peserta_ids, peserta_eksternal = :peserta_eks,
                  agenda = :agenda, pembahasan = :pembahasan, keputusan = :keputusan, lampiran_foto = :lampiran,
                  status = :status, notified_at = :notified_at, updated_by = :updated_by
                WHERE id = :id
            ");
            $stmtUp->execute([
                'judul'       => mb_substr($judul, 0, 200),
                'jenis'       => $jenis,
                'tanggal'     => $tanggal,
                'jam_mulai'   => $jam_mulai,
                'jam_selesai' => $jam_selesai,
                'lokasi'      => mb_substr($lokasi, 0, 120),
                'pimpinan'    => mb_substr($pimpinan_rapat, 0, 100),
                'peserta_ids' => json_encode($pesertaIds),
                'peserta_eks' => mb_substr($peserta_eksternal, 0, 255),
                'agenda'      => $agenda,
                'pembahasan'  => $pembahasan,
                'keputusan'   => $keputusan,
                'lampiran'    => $lampiranFoto,
                'status'      => $status,
                'notified_at' => $notifiedAt,
                'updated_by'  => (int)$user['id'],
                'id'          => $postEditId,
            ]);
            $notulenId = $postEditId;
            $pdo->prepare("DELETE FROM notulen_action_items WHERE notulen_id = :id")->execute(['id' => $notulenId]);
        } else {
            $stmtIn = $pdo->prepare("
                INSERT INTO notulen_meeting (
                  nomor_notulen, judul, jenis, tanggal, jam_mulai, jam_selesai, lokasi, pimpinan_rapat,
                  peserta_ids, peserta_eksternal, agenda, pembahasan, keputusan, lampiran_foto,
                  status, notified_at, created_by, updated_by
                ) VALUES (
                  :nomor, :judul, :jenis, :tanggal, :jam_mulai, :jam_selesai, :lokasi, :pimpinan,
                  :peserta_ids, :peserta_eks, :agenda, :pembahasan, :keputusan, :lampiran,
                  :status, :notified_at, :created_by, :updated_by
                )
            ");
            $stmtIn->execute([
                'nomor'       => $nomor_notulen,
                'judul'       => mb_substr($judul, 0, 200),
                'jenis'       => $jenis,
                'tanggal'     => $tanggal,
                'jam_mulai'   => $jam_mulai,
                'jam_selesai' => $jam_selesai,
                'lokasi'      => mb_substr($lokasi, 0, 120),
                'pimpinan'    => mb_substr($pimpinan_rapat, 0, 100),
                'peserta_ids' => json_encode($pesertaIds),
                'peserta_eks' => mb_substr($peserta_eksternal, 0, 255),
                'agenda'      => $agenda,
                'pembahasan'  => $pembahasan,
                'keputusan'   => $keputusan,
                'lampiran'    => $lampiranFoto,
                'status'      => $status,
                'notified_at' => $notifiedAt,
                'created_by'  => (int)$user['id'],
                'updated_by'  => (int)$user['id'],
            ]);
            $notulenId = (int)$pdo->lastInsertId();
        }

        // Save Action Items
        if (isset($_POST['uraian_tugas']) && is_array($_POST['uraian_tugas'])) {
            $stmtItem = $pdo->prepare("
                INSERT INTO notulen_action_items (notulen_id, uraian_tugas, pic, target_tanggal, status, urutan)
                VALUES (:nid, :uraian, :pic, :target, :status, :urutan)
            ");
            $cnt = count($_POST['uraian_tugas']);
            for ($i = 0; $i < $cnt; $i++) {
                $uraian = trim($_POST['uraian_tugas'][$i] ?? '');
                if ($uraian === '') continue;
                $target = trim($_POST['target_tanggal'][$i] ?? '');
                $stmtItem->execute([
                    'nid'    => $notulenId,
                    'uraian' => $uraian,
                    'pic'    => mb_substr(trim($_POST['pic'][$i] ?? ''), 0, 100),
                    'target' => $target !== '' ? $target : null,
                    'status' => $_POST['status_item'][$i] ?? 'Belum Mulai',
                    'urutan' => $i + 1,
                ]);
            }
        }

        $pdo->commit();

        if ($shouldNotify) {
            notify_notulen_participants($pdo, $notulenId, $pesertaIds, $nomor_notulen, $judul, (int)$user['id']);
        }

        require_once __DIR__ . '/../includes/audit_helper.php';
        $actCode = $status === 'FINAL' ? ($existing ? 'NOTULEN_UPDATE' : 'NOTULEN_FINAL') : 'NOTULEN_DRAFT';
        log_activity($pdo, $actCode, "Menyimpan notulen {$nomor_notulen} ({$status}): {$judul}");

        flash_set('success', $status === 'DRAFT'
            ? "Draft notulen {$nomor_notulen} berhasil disimpan!"
            : "Notulen {$nomor_notulen} berhasil disimpan & difinalisasi!");
        header('Location: ' . BASE_URL . 'notulen/detail?id=' . $notulenId);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMsg = "Gagal menyimpan notulen: " . $e->getMessage();
    }
}

$isEdit          = !empty($editRow);
$valJudul        = $isEdit ? $editRow['judul'] : '';
$valJenis        = $isEdit ? $editRow['jenis'] : 'Briefing Shift';
$valTanggal      = $isEdit ? $editRow['tanggal'] : date('Y-m-d');
$valJamMulai     = $isEdit ? substr($editRow['jam_mulai'], 0, 5) : date('H:i');
$valJamSelesai   = ($isEdit && !empty($editRow['jam_selesai'])) ? substr($editRow['jam_selesai'], 0, 5) : '';
$valLokasi       = $isEdit ? $editRow['lokasi'] : ($masterLokasi[0]['nama_lokasi'] ?? 'Control Room');
$valPimpinan     = $isEdit ? $editRow['pimpinan_rapat'] : $user['nama_lengkap'];
$valPesertaIds   = $isEdit ? (json_decode($editRow['peserta_ids'] ?? '[]', true) ?: []) : [(int)$user['id']];
$valPesertaEks   = $isEdit ? ($editRow['peserta_eksternal'] ?? '') : '';
$valAgenda       = $isEdit ? ($editRow['agenda'] ?? '') : '';
$valPembahasan   = $isEdit ? ($editRow['pembahasan'] ?? '') : '';
$valKeputusan    = $isEdit ? ($editRow['keputusan'] ?? '') : '';
$valFotoUrl      = ($isEdit && !empty($editRow['lampiran_foto'])) ? (UPLOAD_URL . $editRow['lampiran_foto']) : '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="w-full pb-12 animate-stagger-1">

  <div class="mb-6">
    <a href="<?= BASE_URL ?>notulen/index" class="text-xs font-bold text-[#6558f5] hover:underline flex items-center gap-1 mb-2">
      <i class="ph-bold ph-arrow-left"></i>
      <span>Kembali ke Daftar Notulen</span>
    </a>
    <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
      <i class="ph-fill ph-notepad text-[#6558f5]"></i>
      <span><?= $isEdit ? 'Edit / Update Notulen' : 'Catat Notulen Meeting &amp; Briefing' ?></span>
    </h2>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
      Semua petugas dapat melihat, memperbarui isi pembahasan, maupun memantau status tindak lanjut.
    </p>
  </div>

  <?php if (!empty($errorMsg)): ?>
    <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm flex items-center gap-3">
      <i class="ph-bold ph-warning-circle text-xl shrink-0"></i>
      <span><?= sanitize($errorMsg) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="edit_id" value="<?= $isEdit ? (int)$editRow['id'] : 0 ?>" />

    <!-- Bagian 1: Informasi Rapat -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8 space-y-5">
      <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200 pb-3 border-b border-slate-100 dark:border-slate-800">
        1. Informasi Pertemuan / Briefing
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Nomor Notulen</label>
          <input type="text" name="nomor_notulen" value="<?= sanitize($nomorOtomatis) ?>" readonly
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-xs font-bold text-slate-500" />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Jenis Pertemuan <span class="text-rose-500">*</span></label>
          <select name="jenis" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
            <?php foreach (['Briefing Shift', 'Meeting Operasional', 'Evaluasi', 'Koordinasi'] as $jOpt): ?>
              <option value="<?= $jOpt ?>" <?= $valJenis === $jOpt ? 'selected' : '' ?>><?= $jOpt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Tanggal <span class="text-rose-500">*</span></label>
          <input type="date" name="tanggal" value="<?= htmlspecialchars($valTanggal) ?>" required
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Judul / Topik Rapat <span class="text-rose-500">*</span></label>
          <input type="text" name="judul" value="<?= sanitize($valJudul) ?>" required placeholder="Contoh: Briefing Operasional Shift Pagi &amp; Evaluasi Chiller"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Jam Mulai</label>
            <input type="time" name="jam_mulai" value="<?= htmlspecialchars($valJamMulai) ?>" required
                   class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Jam Selesai</label>
            <input type="time" name="jam_selesai" value="<?= htmlspecialchars($valJamSelesai) ?>"
                   class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Lokasi / Ruang Rapat</label>
          <input type="text" name="lokasi" list="lokasiOptions" value="<?= sanitize($valLokasi) ?>" required placeholder="Contoh: Ruang Meeting / Control Room"
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
          <datalist id="lokasiOptions">
            <?php foreach ($masterLokasi as $ml): ?>
              <option value="<?= sanitize($ml['nama_lokasi']) ?>"></option>
            <?php endforeach; ?>
            <option value="Ruang Meeting Utama"></option>
            <option value="Online / Zoom / Teams"></option>
          </datalist>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Pimpinan Rapat / Notulis</label>
          <input type="text" name="pimpinan_rapat" value="<?= sanitize($valPimpinan) ?>" required
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
        </div>
      </div>

      <!-- Peserta -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Peserta Internal (Mendapat Notifikasi Lonceng Saat Final)</label>
          <div class="flex gap-2 text-[11px]">
            <button type="button" onclick="toggleAllParticipants(true)" class="text-[#6558f5] font-bold hover:underline cursor-pointer">Pilih Semua</button>
            <span class="text-slate-300">•</span>
            <button type="button" onclick="toggleAllParticipants(false)" class="text-slate-500 font-semibold hover:underline cursor-pointer">Kosongkan</button>
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 max-h-44 overflow-y-auto p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
          <?php foreach ($masterUsers as $mu): ?>
            <?php $checked = in_array((int)$mu['id'], $valPesertaIds, true); ?>
            <label class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-700 cursor-pointer text-xs">
              <input type="checkbox" name="peserta_ids[]" value="<?= $mu['id'] ?>" class="peserta-cb rounded text-[#6558f5]" <?= $checked ? 'checked' : '' ?> />
              <span class="truncate font-semibold text-slate-700 dark:text-slate-200"><?= sanitize($mu['nama_lengkap']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <input type="text" name="peserta_eksternal" value="<?= sanitize($valPesertaEks) ?>" placeholder="Peserta tambahan / tamu eksternal (opsional, pisahkan dengan koma)..."
               class="mt-2.5 w-full px-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
      </div>
    </div>

    <!-- Bagian 2: Isi Pembahasan & Keputusan -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8 space-y-5">
      <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200 pb-3 border-b border-slate-100 dark:border-slate-800">
        2. Agenda, Pembahasan &amp; Keputusan
      </h3>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Agenda Pertemuan (Opsional)</label>
        <textarea name="agenda" rows="2" placeholder="1. Review operasional shift&#10;2. Pembagian tugas harian..."
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs"><?= sanitize($valAgenda) ?></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Ringkasan Pembahasan / Catatan Rapat</label>
        <textarea name="pembahasan" rows="5" placeholder="Catat poin-poin penting yang dibahas selama meeting atau briefing..."
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs leading-relaxed"><?= sanitize($valPembahasan) ?></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Keputusan / Kesimpulan Rapat</label>
          <textarea name="keputusan" rows="3" placeholder="Tuliskan keputusan final yang disepakati..."
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs"><?= sanitize($valKeputusan) ?></textarea>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Foto Dokumentasi / Papan Tulis (Opsional)</label>
          <div onclick="document.getElementById('lampiran_foto').click()"
               class="h-24 rounded-xl bg-slate-100 dark:bg-slate-900 border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center overflow-hidden cursor-pointer hover:border-[#6558f5] transition">
            <img id="preview_lampiran" src="<?= htmlspecialchars($valFotoUrl) ?>" class="<?= $valFotoUrl ? '' : 'hidden' ?> w-full h-full object-cover" />
            <div id="placeholder_lampiran" class="<?= $valFotoUrl ? 'hidden' : '' ?> text-center text-slate-400 text-xs">
              <i class="ph ph-camera text-2xl block"></i>
              <span>Upload Foto</span>
            </div>
          </div>
          <input type="file" name="lampiran_foto" id="lampiran_foto" accept="image/*" class="hidden" onchange="previewNotulenImg(this)" />
        </div>
      </div>
    </div>

    <!-- Bagian 3: Action Items -->
    <div class="bg-white dark:bg-[#1b1e30] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-800">
        <div>
          <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200">3. Action Items / Tindak Lanjut</h3>
          <p class="text-xs text-slate-400 mt-0.5">Daftar tugas hasil rapat beserta PIC dan target penyelesaiannya</p>
        </div>
        <button type="button" onclick="addActionRow()"
                class="px-4 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white text-xs font-bold flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
          <i class="ph-bold ph-plus"></i>
          <span>Tambah Tugas</span>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs min-w-[700px]">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase text-[10px] tracking-wider">
            <tr>
              <th class="py-2.5 px-3 w-10 text-center">No</th>
              <th class="py-2.5 px-3">Uraian Tugas / Tindak Lanjut</th>
              <th class="py-2.5 px-3 w-40">PIC</th>
              <th class="py-2.5 px-3 w-36">Target Tanggal</th>
              <th class="py-2.5 px-3 w-36">Status</th>
              <th class="py-2.5 px-3 w-12 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="actionRows" class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php
              $rowsToRender = !empty($editItems) ? $editItems : [['uraian_tugas' => '', 'pic' => $user['nama_lengkap'], 'target_tanggal' => '', 'status' => 'Belum Mulai']];
              foreach ($rowsToRender as $idx => $it):
            ?>
              <tr class="action-row">
                <td class="py-2.5 px-3 text-center font-bold text-slate-400 row-num"><?= $idx + 1 ?></td>
                <td class="py-2.5 px-3">
                  <input type="text" name="uraian_tugas[]" value="<?= sanitize($it['uraian_tugas']) ?>" placeholder="Tugas yang harus ditindaklanjuti..."
                         class="w-full px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-2.5 px-3">
                  <input type="text" name="pic[]" value="<?= sanitize($it['pic']) ?>" placeholder="Nama PIC"
                         class="w-full px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-2.5 px-3">
                  <input type="date" name="target_tanggal[]" value="<?= htmlspecialchars($it['target_tanggal'] ?? '') ?>"
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-2.5 px-3">
                  <select name="status_item[]" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                    <?php foreach (['Belum Mulai', 'Berjalan', 'Selesai'] as $st): ?>
                      <option value="<?= $st ?>" <?= ($it['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="py-2.5 px-3 text-center">
                  <button type="button" onclick="removeActionRow(this)" class="p-1 text-slate-400 hover:text-rose-500 cursor-pointer">
                    <i class="ph-bold ph-trash text-sm"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Submit Buttons -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
      <a href="<?= BASE_URL ?>notulen/index"
         class="px-6 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-sm text-center transition">
        Batal
      </a>
      <button type="submit" name="submit_action" value="draft" formnovalidate
              class="px-6 py-3 rounded-2xl bg-amber-50 dark:bg-amber-950/50 hover:bg-amber-100 text-amber-700 dark:text-amber-300 border border-amber-300/80 dark:border-amber-700/70 font-extrabold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-note-pencil text-lg"></i>
        <span>Simpan Draft</span>
      </button>
      <button type="submit" name="submit_action" value="final"
              class="px-8 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-sm shadow-lg shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-check-circle text-lg"></i>
        <span>Simpan &amp; Finalisasi</span>
      </button>
    </div>
  </form>

</div>

<script>
  function toggleAllParticipants(checked) {
    document.querySelectorAll('.peserta-cb').forEach(cb => cb.checked = checked);
  }

  function previewNotulenImg(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.getElementById('preview_lampiran');
        const ph  = document.getElementById('placeholder_lampiran');
        img.src = e.target.result;
        img.classList.remove('hidden');
        ph.classList.add('hidden');
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function addActionRow() {
    const tbody = document.getElementById('actionRows');
    const num = tbody.querySelectorAll('.action-row').length + 1;
    const tr = document.createElement('tr');
    tr.className = 'action-row';
    tr.innerHTML = `
      <td class="py-2.5 px-3 text-center font-bold text-slate-400 row-num">${num}</td>
      <td class="py-2.5 px-3"><input type="text" name="uraian_tugas[]" placeholder="Tugas yang harus ditindaklanjuti..." class="w-full px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" /></td>
      <td class="py-2.5 px-3"><input type="text" name="pic[]" placeholder="Nama PIC" class="w-full px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" /></td>
      <td class="py-2.5 px-3"><input type="date" name="target_tanggal[]" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" /></td>
      <td class="py-2.5 px-3">
        <select name="status_item[]" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
          <option value="Belum Mulai" selected>Belum Mulai</option>
          <option value="Berjalan">Berjalan</option>
          <option value="Selesai">Selesai</option>
        </select>
      </td>
      <td class="py-2.5 px-3 text-center"><button type="button" onclick="removeActionRow(this)" class="p-1 text-slate-400 hover:text-rose-500 cursor-pointer"><i class="ph-bold ph-trash text-sm"></i></button></td>
    `;
    tbody.appendChild(tr);
  }

  function removeActionRow(btn) {
    const tbody = document.getElementById('actionRows');
    if (tbody.querySelectorAll('.action-row').length <= 1) return;
    btn.closest('tr').remove();
    tbody.querySelectorAll('.action-row .row-num').forEach((el, idx) => el.textContent = idx + 1);
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
