<?php
// laporan/create.php - Formulir Buat Serah Terima Shift Baru (Multi-mode Signature: Barcode QR, Upload TTD Basah, Canvas)
$current_page = 'laporan_create';
$page_title = 'Buat Laporan Serah Terima';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();

// Fetch Master Options (Cached with Redis/Memory Fallback)
$masterShift = get_master_shift(true);
$masterLokasi = get_master_lokasi(true);
$masterUsers = $pdo->query("SELECT id, nama_lengkap, role, jabatan FROM users WHERE status = 'aktif' ORDER BY nama_lengkap ASC")->fetchAll();
$masterKondisi = get_master_kondisi(true);

$nomorOtomatis = generate_nomor_laporan($pdo);

// Check Edit Draft Mode (?edit=ID)
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editReport = null;
$editTasks  = [];
if ($editId > 0) {
    $stmtEdit = $pdo->prepare("SELECT * FROM laporan_serah_terima WHERE id = :id LIMIT 1");
    $stmtEdit->execute(['id' => $editId]);
    $editReport = $stmtEdit->fetch();
    if (!$editReport || $editReport['status_validasi'] !== 'DRAFT' || ($editReport['created_by'] != $user['id'] && !is_admin())) {
        flash_set('error', 'Draft laporan tidak ditemukan atau Anda tidak memiliki akses untuk mengeditnya.');
        header('Location: ' . BASE_URL . 'laporan/index');
        exit;
    }
    $nomorOtomatis = $editReport['nomor_laporan'];
    $stmtET = $pdo->prepare("SELECT * FROM laporan_pekerjaan WHERE laporan_id = :id ORDER BY urutan ASC, id ASC");
    $stmtET->execute(['id' => $editId]);
    $editTasks = $stmtET->fetchAll();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $draftId = (int)($_POST['draft_id'] ?? 0);
        $submitAction = ($_POST['submit_action'] ?? 'submit') === 'draft' ? 'draft' : 'submit';
        $isDraft = ($submitAction === 'draft');

        $existingDraft = null;
        if ($draftId > 0) {
            $stmtCheck = $pdo->prepare("SELECT * FROM laporan_serah_terima WHERE id = :id LIMIT 1");
            $stmtCheck->execute(['id' => $draftId]);
            $existingDraft = $stmtCheck->fetch();
            if (!$existingDraft || $existingDraft['status_validasi'] !== 'DRAFT' || ($existingDraft['created_by'] != $user['id'] && !is_admin())) {
                throw new Exception('Draft tidak valid atau sudah diajukan.');
            }
        }

        $nomor_laporan = $existingDraft ? $existingDraft['nomor_laporan'] : trim($_POST['nomor_laporan'] ?? $nomorOtomatis);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $shift_id = (int)($_POST['shift_id'] ?? 1);
        $lokasi_id = (int)($_POST['lokasi_id'] ?? 1);
        $jam_serah_terima = $_POST['jam_serah_terima'] ?? date('H:i');
        $kondisi_operasional = $_POST['kondisi_operasional'] ?? 'Normal';
        // Petugas menyerahkan otomatis terkunci ke akun pembuat yang sedang login
        $petugas_menyerahkan_id = (int)$user['id'];
        $petugas_menerima_id = !empty($_POST['petugas_menerima_id']) ? (int)$_POST['petugas_menerima_id'] : null;
        $catatan_penting = trim($_POST['catatan_penting'] ?? '');

        if (!$isDraft && empty($petugas_menerima_id)) {
            throw new Exception('Silakan pilih Petugas Menerima (Shift Baru) sebelum mengajukan laporan.');
        }

        // Upload 3 Photos (pertahankan foto lama jika edit draft dan tidak upload baru)
        $foto_1_new = isset($_FILES['foto_1']) ? upload_foto($_FILES['foto_1'], 'f1_') : null;
        $foto_1 = $foto_1_new ?: ($existingDraft['foto_1'] ?? null);
        $ket_foto_1 = trim($_POST['ket_foto_1'] ?? '');

        $foto_2_new = isset($_FILES['foto_2']) ? upload_foto($_FILES['foto_2'], 'f2_') : null;
        $foto_2 = $foto_2_new ?: ($existingDraft['foto_2'] ?? null);
        $ket_foto_2 = trim($_POST['ket_foto_2'] ?? '');

        $foto_3_new = isset($_FILES['foto_3']) ? upload_foto($_FILES['foto_3'], 'f3_') : null;
        $foto_3 = $foto_3_new ?: ($existingDraft['foto_3'] ?? null);
        $ket_foto_3 = trim($_POST['ket_foto_3'] ?? '');

        // Checklists
        $check_lisan = $_POST['check_lisan'] ?? 'Ya';
        $check_tiket = $_POST['check_tiket'] ?? 'Ya';
        $check_risiko = $_POST['check_risiko'] ?? 'Ya';
        $check_kunci = $_POST['check_kunci'] ?? 'Ya';

        // Signature Submitter (Shift Lama) - Hanya dibuat saat diajukan (bukan saat draft)
        $jenis_ttd = $user['ttd_type'] ?? 'barcode';
        if (!empty($_POST['jenis_ttd_menyerahkan'])) {
            $jenis_ttd = $_POST['jenis_ttd_menyerahkan'];
        }
        $ttd_file = $_FILES['ttd_upload_menyerahkan'] ?? null;
        $ttd_base64 = $_POST['ttd_base64_menyerahkan'] ?? null;

        $ttd_menyerahkan = null;
        if (!$isDraft) {
            if (($jenis_ttd === 'upload' || $jenis_ttd === 'canvas') && !empty($user['ttd_file'])) {
                $srcPath = UPLOAD_DIR . $user['ttd_file'];
                if (file_exists($srcPath)) {
                    $ext = pathinfo($user['ttd_file'], PATHINFO_EXTENSION);
                    $destFilename = 'signatures/ttd_lama_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (copy($srcPath, UPLOAD_DIR . $destFilename)) {
                        $ttd_menyerahkan = $destFilename;
                    }
                }
            }
            if (empty($ttd_menyerahkan)) {
                $ttd_menyerahkan = process_signature($jenis_ttd, $ttd_file, $ttd_base64, 'ttd_lama_');
            }
        }

        $status_validasi = $isDraft ? 'DRAFT' : 'MENUNGGU VALIDASI';

        if ($existingDraft) {
            $stmt = $pdo->prepare("UPDATE laporan_serah_terima SET
                tanggal = :tanggal, shift_id = :shift_id, lokasi_id = :lokasi_id, jam_serah_terima = :jam_serah_terima,
                kondisi_operasional = :kondisi_operasional, petugas_menerima_id = :petugas_menerima_id, catatan_penting = :catatan_penting,
                foto_1 = :foto_1, ket_foto_1 = :ket_foto_1, foto_2 = :foto_2, ket_foto_2 = :ket_foto_2, foto_3 = :foto_3, ket_foto_3 = :ket_foto_3,
                check_lisan = :check_lisan, check_tiket = :check_tiket, check_risiko = :check_risiko, check_kunci = :check_kunci,
                ttd_menyerahkan = :ttd_menyerahkan, jenis_ttd_menyerahkan = :jenis_ttd_menyerahkan, status_validasi = :status_validasi
                WHERE id = :id");
            $stmt->execute([
                'tanggal'               => $tanggal,
                'shift_id'              => $shift_id,
                'lokasi_id'             => $lokasi_id,
                'jam_serah_terima'      => $jam_serah_terima,
                'kondisi_operasional'   => $kondisi_operasional,
                'petugas_menerima_id'   => $petugas_menerima_id,
                'catatan_penting'       => $catatan_penting,
                'foto_1'                => $foto_1,
                'ket_foto_1'            => $ket_foto_1,
                'foto_2'                => $foto_2,
                'ket_foto_2'            => $ket_foto_2,
                'foto_3'                => $foto_3,
                'ket_foto_3'            => $ket_foto_3,
                'check_lisan'           => $check_lisan,
                'check_tiket'           => $check_tiket,
                'check_risiko'          => $check_risiko,
                'check_kunci'           => $check_kunci,
                'ttd_menyerahkan'       => $ttd_menyerahkan,
                'jenis_ttd_menyerahkan' => $jenis_ttd,
                'status_validasi'       => $status_validasi,
                'id'                    => $draftId,
            ]);
            $laporanId = $draftId;
            $pdo->prepare("DELETE FROM laporan_pekerjaan WHERE laporan_id = :id")->execute(['id' => $laporanId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO laporan_serah_terima (
                nomor_laporan, tanggal, shift_id, lokasi_id, jam_serah_terima,
                kondisi_operasional, petugas_menyerahkan_id, petugas_menerima_id, catatan_penting,
                foto_1, ket_foto_1, foto_2, ket_foto_2, foto_3, ket_foto_3,
                check_lisan, check_tiket, check_risiko, check_kunci,
                ttd_menyerahkan, jenis_ttd_menyerahkan, status_validasi, created_by
            ) VALUES (
                :nomor_laporan, :tanggal, :shift_id, :lokasi_id, :jam_serah_terima,
                :kondisi_operasional, :petugas_menyerahkan_id, :petugas_menerima_id, :catatan_penting,
                :foto_1, :ket_foto_1, :foto_2, :ket_foto_2, :foto_3, :ket_foto_3,
                :check_lisan, :check_tiket, :check_risiko, :check_kunci,
                :ttd_menyerahkan, :jenis_ttd_menyerahkan, :status_validasi, :created_by
            )");

            $stmt->execute([
                'nomor_laporan'          => $nomor_laporan,
                'tanggal'                => $tanggal,
                'shift_id'               => $shift_id,
                'lokasi_id'              => $lokasi_id,
                'jam_serah_terima'       => $jam_serah_terima,
                'kondisi_operasional'    => $kondisi_operasional,
                'petugas_menyerahkan_id' => $petugas_menyerahkan_id,
                'petugas_menerima_id'    => $petugas_menerima_id,
                'catatan_penting'        => $catatan_penting,
                'foto_1'                 => $foto_1,
                'ket_foto_1'             => $ket_foto_1,
                'foto_2'                 => $foto_2,
                'ket_foto_2'             => $ket_foto_2,
                'foto_3'                 => $foto_3,
                'ket_foto_3'             => $ket_foto_3,
                'check_lisan'            => $check_lisan,
                'check_tiket'            => $check_tiket,
                'check_risiko'           => $check_risiko,
                'check_kunci'            => $check_kunci,
                'ttd_menyerahkan'        => $ttd_menyerahkan,
                'jenis_ttd_menyerahkan'  => $jenis_ttd,
                'status_validasi'        => $status_validasi,
                'created_by'             => $user['id']
            ]);

            $laporanId = $pdo->lastInsertId();
        }

        // Save Tasks
        if (isset($_POST['pekerjaan_masalah']) && is_array($_POST['pekerjaan_masalah'])) {
            $stmtTask = $pdo->prepare("INSERT INTO laporan_pekerjaan (
                laporan_id, no_tiket, pekerjaan_masalah, prioritas, status, kondisi_tindak_lanjut, pic, target_selesai, urutan
            ) VALUES (
                :laporan_id, :no_tiket, :pekerjaan_masalah, :prioritas, :status, :kondisi_tindak_lanjut, :pic, :target_selesai, :urutan
            )");

            $count = count($_POST['pekerjaan_masalah']);
            for ($i = 0; $i < $count; $i++) {
                $masalah = trim($_POST['pekerjaan_masalah'][$i] ?? '');
                if ($masalah === '') continue;

                $noTiket = trim($_POST['no_tiket'][$i] ?? '');
                $statusTugas = $_POST['status_tugas'][$i] ?? 'Berjalan';

                $stmtTask->execute([
                    'laporan_id'            => $laporanId,
                    'no_tiket'              => $noTiket,
                    'pekerjaan_masalah'     => $masalah,
                    'prioritas'             => $_POST['prioritas'][$i] ?? 'Sedang',
                    'status'                => $statusTugas,
                    'kondisi_tindak_lanjut' => trim($_POST['kondisi_tindak_lanjut'][$i] ?? ''),
                    'pic'                   => trim($_POST['pic'][$i] ?? ''),
                    'target_selesai'        => trim($_POST['target_selesai'][$i] ?? ''),
                    'urutan'                => $i + 1
                ]);

                // Sinkronisasi: jika tiket ini diselesaikan (Selesai), sinkronkan riwayat tiket lama agar tidak terus muncul sebagai eskalasi aktif
                if (!$isDraft && $statusTugas === 'Selesai' && !empty($noTiket)) {
                    $stmtSync = $pdo->prepare("UPDATE laporan_pekerjaan SET status = 'Selesai' WHERE no_tiket = :tiket AND laporan_id != :laporanId");
                    $stmtSync->execute(['tiket' => $noTiket, 'laporanId' => $laporanId]);
                }
            }
        }

        $pdo->commit();

        require_once __DIR__ . '/../includes/audit_helper.php';
        if ($isDraft) {
            log_activity($pdo, 'HANDOVER_DRAFT', "Menyimpan draft laporan serah terima {$nomor_laporan}.");
            flash_set('success', "Draft {$nomor_laporan} berhasil disimpan! Anda dapat mencicil pengisian atau mengirimnya kapan saja.");
            header('Location: ' . BASE_URL . 'laporan/create?edit=' . $laporanId);
            exit;
        }

        // Trigger Email & WhatsApp Notification to Shift Receiver
        require_once __DIR__ . '/../includes/notifications.php';
        notify_handover_created($laporanId, $pdo);

        log_activity($pdo, 'HANDOVER_CREATE', "Membuat laporan serah terima {$nomor_laporan}.");

        flash_set('success', "Laporan serah terima {$nomor_laporan} berhasil disimpan! Notifikasi telah dikirimkan ke petugas penerima.");
        header('Location: ' . BASE_URL . 'laporan/detail?id=' . $laporanId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Gagal menyimpan laporan: " . $e->getMessage();
    }
}

// Fetch carry-over tasks from previous shift / specified report (skip when editing an existing draft)
$carryFromId = isset($_GET['carry_from']) ? (int)$_GET['carry_from'] : 0;
$carryReport = null;
$carryTasks  = [];
$isEditDraft = !empty($editReport);

if ($isEditDraft) {
    $carryTasks = $editTasks;
} elseif ($carryFromId > 0) {
    $stmtCarry = $pdo->prepare("
        SELECT l.*, s.nama_shift, m.nama_lokasi 
        FROM laporan_serah_terima l 
        JOIN master_shift s ON l.shift_id = s.id 
        JOIN master_lokasi m ON l.lokasi_id = m.id 
        WHERE l.id = :id
    ");
    $stmtCarry->execute(['id' => $carryFromId]);
    $carryReport = $stmtCarry->fetch();

    if ($carryReport) {
        $stmtT = $pdo->prepare("
            SELECT * FROM laporan_pekerjaan 
            WHERE laporan_id = :id AND status != 'Selesai' 
            ORDER BY CASE WHEN prioritas = 'Tinggi / Kritis' THEN 1 WHEN prioritas = 'Sedang' THEN 2 ELSE 3 END, id ASC
        ");
        $stmtT->execute(['id' => $carryFromId]);
        $carryTasks = $stmtT->fetchAll();
    }
} else {
    // Auto-detect unfinished tasks from the most recent submitted handover report
    $stmtLatest = $pdo->query("
        SELECT l.*, s.nama_shift, m.nama_lokasi 
        FROM laporan_serah_terima l 
        JOIN master_shift s ON l.shift_id = s.id 
        JOIN master_lokasi m ON l.lokasi_id = m.id 
        WHERE l.status_validasi != 'DRAFT'
        ORDER BY l.tanggal DESC, l.jam_serah_terima DESC, l.id DESC 
        LIMIT 1
    ");
    $latestRep = $stmtLatest->fetch();
    if ($latestRep) {
        $stmtT = $pdo->prepare("
            SELECT * FROM laporan_pekerjaan 
            WHERE laporan_id = :id AND status != 'Selesai' 
            ORDER BY CASE WHEN prioritas = 'Tinggi / Kritis' THEN 1 WHEN prioritas = 'Sedang' THEN 2 ELSE 3 END, id ASC
        ");
        $stmtT->execute(['id' => $latestRep['id']]);
        $tasksFromLatest = $stmtT->fetchAll();
        if (!empty($tasksFromLatest)) {
            $carryReport = $latestRep;
            $carryTasks = $tasksFromLatest;
        }
    }
}

$selectedLokasiId = $isEditDraft ? (int)$editReport['lokasi_id'] : ($carryReport['lokasi_id'] ?? 1);
$valTanggal       = $isEditDraft ? $editReport['tanggal'] : date('Y-m-d');
$valShiftId       = $isEditDraft ? (int)$editReport['shift_id'] : ($masterShift[0]['id'] ?? 1);
$valKondisi       = $isEditDraft ? $editReport['kondisi_operasional'] : '';
$valPenerimaId    = $isEditDraft ? (int)($editReport['petugas_menerima_id'] ?? 0) : 0;
$valCatatan       = $isEditDraft ? ($editReport['catatan_penting'] ?? '') : '';
$valCheckLisan    = $isEditDraft ? ($editReport['check_lisan'] ?? 'Ya') : 'Ya';
$valCheckTiket    = $isEditDraft ? ($editReport['check_tiket'] ?? 'Ya') : 'Ya';
$valCheckRisiko   = $isEditDraft ? ($editReport['check_risiko'] ?? 'Ya') : 'Ya';
$valCheckKunci    = $isEditDraft ? ($editReport['check_kunci'] ?? 'Ya') : 'Ya';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Include QRCode library -->
<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>

<div class="w-full pb-12 animate-stagger-1">

  <!-- Header Banner -->
  <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <a href="<?= BASE_URL ?>laporan/index" class="text-xs font-bold text-[#6558f5] hover:underline flex items-center gap-1 mb-2">
        <i class="ph-bold ph-arrow-left"></i>
        <span>Kembali ke Riwayat</span>
      </a>
      <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
        <i class="ph-fill ph-file-plus text-[#6558f5]"></i>
        <span><?= $isEditDraft ? 'Lanjutkan Draft Serah Terima' : 'Formulir Serah Terima Shift Operasional' ?></span>
        <?php if ($isEditDraft): ?>
          <span class="px-2.5 py-0.5 rounded-full bg-slate-500 text-white text-[10px] font-black uppercase tracking-wider">DRAFT</span>
        <?php endif; ?>
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        <?= $isEditDraft
          ? 'Anda sedang melengkapi draft <strong class="font-mono text-[#6558f5]">' . sanitize($nomorOtomatis) . '</strong>. Simpan draft kapan saja atau langsung ajukan ke shift penerima.'
          : 'Isi data serah terima shift. Anda dapat menyimpan sebagai <strong>Draft</strong> terlebih dahulu untuk mencicil pengisian pekerjaan.' ?>
      </p>
    </div>
  </div>

  <?php if (!empty($errorMsg)): ?>
    <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm flex items-center gap-3">
      <i class="ph-bold ph-warning-circle text-xl flex-shrink-0"></i>
      <span><?= $errorMsg ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="" enctype="multipart/form-data" id="formHandover" class="space-y-8">
    <input type="hidden" name="draft_id" value="<?= $isEditDraft ? (int)$editReport['id'] : 0 ?>" />
    
    <!-- ==========================================
         BAGIAN 1: INFORMASI SHIFT & KONDISI UMUM
         ========================================== -->
    <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
      <div class="flex items-center gap-3 pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-sm">
          1
        </div>
        <div>
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white">INFORMASI SHIFT &amp; KONDISI UMUM</h3>
          <p class="text-xs text-slate-400">Parameter utama waktu, lokasi, dan status operasional shift</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">No. Dokumen Laporan</label>
          <input type="text" name="nomor_laporan" id="docNumberInput" value="<?= $nomorOtomatis ?>" readonly
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-mono text-xs font-bold" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Tanggal <span class="text-rose-500">*</span></label>
          <input type="date" name="tanggal" value="<?= htmlspecialchars($valTanggal) ?>" required
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Pilihan Shift <span class="text-rose-500">*</span></label>
          <select name="shift_id" id="shift_id" required onchange="handleShiftChange(this)"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
            <?php foreach ($masterShift as $s): 
              $handoverTime = !empty($s['jam_serah_terima']) ? substr($s['jam_serah_terima'], 0, 5) : substr($s['jam_selesai'], 0, 5);
            ?>
              <option value="<?= $s['id'] ?>" data-handover="<?= $handoverTime ?>" <?= $s['id'] == $valShiftId ? 'selected' : '' ?>>Shift <?= sanitize($s['nama_shift']) ?> (<?= format_jam($s['jam_mulai']) ?> - <?= format_jam($s['jam_selesai']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Jam Serah Terima <span class="text-rose-500">*</span></label>
            <div class="flex items-center gap-2 text-[10px]">
              <button type="button" onclick="setHandoverToShift()" class="text-[#6558f5] hover:underline font-bold cursor-pointer">Sesuai Shift</button>
              <span class="text-slate-300 dark:text-slate-600">•</span>
              <button type="button" onclick="setHandoverToNow()" class="text-slate-500 hover:text-slate-800 dark:hover:text-white font-medium cursor-pointer">Jam Sekarang</button>
            </div>
          </div>
          <?php 
          $initialHandover = $isEditDraft
            ? substr($editReport['jam_serah_terima'], 0, 5)
            : (!empty($masterShift[0]['jam_serah_terima']) ? substr($masterShift[0]['jam_serah_terima'], 0, 5) : (!empty($masterShift[0]['jam_selesai']) ? substr($masterShift[0]['jam_selesai'], 0, 5) : date('H:i')));
          ?>
          <input type="time" name="jam_serah_terima" id="jam_serah_terima" value="<?= htmlspecialchars($initialHandover) ?>" required
                 class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Lokasi / Unit <span class="text-rose-500">*</span></label>
          <select name="lokasi_id" required
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
            <?php foreach ($masterLokasi as $l): ?>
              <option value="<?= $l['id'] ?>" <?= $l['id'] == $selectedLokasiId ? 'selected' : '' ?>><?= sanitize($l['nama_lokasi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center justify-between">
            <span>Kondisi Operasional Umum <span class="text-rose-500">*</span></span>
            <?php if (is_admin()): ?>
              <a href="<?= BASE_URL ?>master/kondisi" target="_blank" class="text-[10px] lowercase font-normal text-[#6558f5] dark:text-indigo-400 hover:underline flex items-center gap-1">
                <i class="ph-bold ph-gear"></i> kelola pilihan
              </a>
            <?php endif; ?>
          </label>
          <select name="kondisi_operasional" id="kondisi_operasional" required
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
            <?php if (empty($masterKondisi)): ?>
              <option value="Normal" selected>Normal</option>
              <option value="Perlu Pantauan">Perlu Pantauan</option>
              <option value="Ada Gangguan">Ada Gangguan</option>
            <?php else: ?>
              <?php foreach ($masterKondisi as $mk): ?>
                <?php $isCondSelected = $valKondisi !== '' ? ($mk['nama_kondisi'] === $valKondisi) : !empty($mk['is_default']); ?>
                <option value="<?= sanitize($mk['nama_kondisi']) ?>" <?= $isCondSelected ? 'selected' : '' ?>>
                  <?= sanitize($mk['nama_kondisi']) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center justify-between">
            <span>Petugas Menyerahkan (Shift Lama) <span class="text-rose-500">*</span></span>
            <span class="text-[10px] font-bold text-[#6558f5] dark:text-indigo-400 normal-case flex items-center gap-1">
              <i class="ph-bold ph-lock-key"></i> Terkunci (Akun Anda)
            </span>
          </label>
          <input type="hidden" name="petugas_menyerahkan_id" id="petugas_menyerahkan_id" value="<?= $user['id'] ?>" />
          <div class="w-full px-4 py-2.5 rounded-xl bg-slate-100/90 dark:bg-slate-900/90 border border-slate-200/90 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-between cursor-not-allowed select-none shadow-inner">
            <div class="flex items-center gap-2.5 overflow-hidden">
              <div class="w-6 h-6 rounded-lg bg-[#efeffb] dark:bg-indigo-950/70 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center font-extrabold text-[11px] shrink-0">
                <i class="ph-bold ph-user-circle"></i>
              </div>
              <span class="truncate">
                <?= sanitize($user['nama_lengkap']) ?> (<?= sanitize($user['jabatan'] ?? $user['role']) ?>)
              </span>
            </div>
            <span class="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[10px] font-extrabold shrink-0 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1">
              <i class="ph-bold ph-shield-check"></i>
              <span>Otomatis</span>
            </span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Petugas Menerima (Shift Baru) <span class="text-rose-500">*</span></label>
          <select name="petugas_menerima_id" id="petugas_menerima_id" required onchange="updateReceiverName()"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5] focus:outline-none">
            <option value="">-- Pilih Petugas Penerima --</option>
            <?php foreach ($masterUsers as $u): ?>
              <?php if ($u['id'] != $user['id']): ?>
                <option value="<?= $u['id'] ?>" data-name="<?= sanitize($u['nama_lengkap']) ?>" <?= $u['id'] == $valPenerimaId ? 'selected' : '' ?>>
                  <?= sanitize($u['nama_lengkap']) ?> (<?= sanitize($u['jabatan'] ?? $u['role']) ?>)
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Metric Badges -->
        <div class="grid grid-cols-2 gap-3 bg-emerald-50/70 dark:bg-slate-900/60 p-3 rounded-2xl border border-emerald-200/80 dark:border-slate-700">
          <div class="text-center">
            <span class="text-[10px] uppercase font-bold text-emerald-800 dark:text-emerald-400 block">Total Pekerjaan Aktif</span>
            <span id="badgeTotalAktif" class="text-2xl font-black text-emerald-700 dark:text-emerald-300">1</span>
            <span class="text-[10px] text-emerald-600 block">(Belum Selesai)</span>
          </div>
          <div class="text-center border-l border-emerald-200/60 dark:border-slate-700">
            <span class="text-[10px] uppercase font-bold text-rose-800 dark:text-rose-400 block">Prioritas Kritis</span>
            <span id="badgePrioritasKritis" class="text-2xl font-black text-rose-600 dark:text-rose-400">0</span>
            <span class="text-[10px] text-rose-500 block">(Tinggi / Kritis)</span>
          </div>
        </div>
      </div>

      <div class="mt-5">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Catatan Penting / Kendala Utama Shift Ini (Opsional)</label>
        <textarea name="catatan_penting" rows="2"
                  placeholder="Contoh: Suhu chiller 2 sedikit meningkat pada pukul 14:00, perlu dipantau berkala..."
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs font-normal focus:ring-2 focus:ring-[#6558f5] focus:outline-none"><?= sanitize($valCatatan) ?></textarea>
      </div>
    </div>

    <!-- ========================================================
         BAGIAN 2: DAFTAR PEKERJAAN & TINDAK LANJUT SHIFT BERIKUTNYA
         ======================================================== -->
    <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-6 border-b border-slate-100 dark:border-slate-800 gap-3">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-sm">
            2
          </div>
          <div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">DAFTAR PEKERJAAN &amp; TINDAK LANJUT UNTUK SHIFT BERIKUTNYA</h3>
            <p class="text-xs text-slate-400">Pekerjaan belum selesai / butuh eskalasi dari shift lalu dimuat otomatis ke shift baru ini</p>
          </div>
        </div>
        <button type="button" onclick="addTaskRow()" 
                class="px-4 py-2 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-[#6558f5] text-[#6558f5] hover:text-white font-bold text-xs rounded-xl transition flex items-center gap-1.5 self-start sm:self-auto cursor-pointer">
          <i class="ph-bold ph-plus"></i>
          <span>Tambah Pekerjaan</span>
        </button>
      </div>

      <?php if (!$isEditDraft && !empty($carryTasks)): ?>
        <!-- Smart Carry-Over / Escalation Notification Banner -->
        <div id="carryOverBanner" class="mb-5 p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-indigo-50/90 via-purple-50/70 to-rose-50/80 dark:from-indigo-950/60 dark:via-purple-950/40 dark:to-rose-950/40 border border-indigo-200/90 dark:border-indigo-800/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-start sm:items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-[#6558f5] text-white flex items-center justify-center font-bold text-xl shrink-0 shadow-md">
              <i class="ph-bold ph-arrows-clockwise"></i>
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-black text-slate-900 dark:text-white">
                  Pekerjaan Operan Shift Sebelumnya Dimuat Otomatis (<?= count($carryTasks) ?> Tugas)
                </span>
                <?php
                $cntKritisCarry = 0;
                foreach ($carryTasks as $ct) {
                    if ($ct['prioritas'] === 'Tinggi / Kritis') $cntKritisCarry++;
                }
                ?>
                <?php if ($cntKritisCarry > 0): ?>
                  <span class="px-2.5 py-0.5 rounded-full bg-rose-500 text-white text-[10px] font-black animate-pulse flex items-center gap-1">
                    <i class="ph-bold ph-warning"></i>
                    <span><?= $cntKritisCarry ?> Butuh Eskalasi Kritis</span>
                  </span>
                <?php endif; ?>
              </div>
              <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                Diambil otomatis dari laporan <strong class="text-[#6558f5] dark:text-indigo-400 font-mono"><?= sanitize($carryReport['nomor_laporan']) ?></strong> (Shift <?= sanitize($carryReport['nama_shift']) ?> • <?= sanitize($carryReport['nama_lokasi']) ?>) agar shift Anda dapat langsung melanjutkan penanganan tanpa kehilangan jejak.
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
            <button type="button" onclick="clearCarryTasks()" 
                    class="px-3 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-white dark:bg-rose-950/40 hover:bg-rose-50 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-300 text-xs font-extrabold transition flex items-center gap-1 cursor-pointer"
                    title="Kosongkan tugas operan ini dan mulai dari nol">
              <i class="ph-bold ph-trash"></i>
              <span>Kosongkan</span>
            </button>
          </div>
        </div>
      <?php endif; ?>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs min-w-[850px]" id="tasksTable">
          <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase tracking-wider">
            <tr>
              <th class="py-2.5 px-3 w-12 text-center rounded-l-xl">No</th>
              <th class="py-2.5 px-3 w-28">No / Tiket</th>
              <th class="py-2.5 px-3">Pekerjaan / Masalah <span class="text-rose-500">*</span></th>
              <th class="py-2.5 px-3 w-32">Prioritas</th>
              <th class="py-2.5 px-3 w-32">Status</th>
              <th class="py-2.5 px-3">Kondisi &amp; Tindak Lanjut</th>
              <th class="py-2.5 px-3 w-32">PIC</th>
              <th class="py-2.5 px-3 w-28">Target</th>
              <th class="py-2.5 px-3 w-12 text-center rounded-r-xl">Aksi</th>
            </tr>
          </thead>
          <tbody id="taskRows" class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php if (!empty($carryTasks)): ?>
              <?php foreach ($carryTasks as $idx => $ct): ?>
                <tr class="task-row bg-indigo-50/20 dark:bg-indigo-950/20">
                  <td class="py-3 px-3 text-center font-bold text-[#6558f5] row-number"><?= $idx + 1 ?></td>
                  <td class="py-3 px-3">
                    <input type="text" name="no_tiket[]" value="<?= sanitize($ct['no_tiket']) ?>" placeholder="TK-01" 
                           class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
                  </td>
                  <td class="py-3 px-3">
                    <input type="text" name="pekerjaan_masalah[]" value="<?= sanitize($ct['pekerjaan_masalah']) ?>" placeholder="Uraian pekerjaan/masalah..." required
                           class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold" />
                  </td>
                  <td class="py-3 px-3">
                    <select name="prioritas[]" onchange="recalculateCounters()"
                            class="task-prioritas w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                      <option value="Tinggi / Kritis" <?= $ct['prioritas'] === 'Tinggi / Kritis' ? 'selected' : '' ?>>Tinggi / Kritis</option>
                      <option value="Sedang" <?= $ct['prioritas'] === 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                      <option value="Rendah" <?= $ct['prioritas'] === 'Rendah' ? 'selected' : '' ?>>Rendah</option>
                    </select>
                  </td>
                  <td class="py-3 px-3">
                    <select name="status_tugas[]" onchange="recalculateCounters()"
                            class="task-status w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                      <option value="Berjalan" <?= $ct['status'] === 'Berjalan' ? 'selected' : '' ?>>Berjalan</option>
                      <option value="Belum Mulai" <?= $ct['status'] === 'Belum Mulai' ? 'selected' : '' ?>>Belum Mulai</option>
                      <option value="Tertunda" <?= $ct['status'] === 'Tertunda' ? 'selected' : '' ?>>Tertunda</option>
                      <option value="Selesai" <?= $ct['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                  </td>
                  <td class="py-3 px-3">
                    <input type="text" name="kondisi_tindak_lanjut[]" value="<?= sanitize($ct['kondisi_tindak_lanjut']) ?>" placeholder="Instruksi untuk shift baru..."
                           class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                  </td>
                  <td class="py-3 px-3">
                    <input type="text" name="pic[]" value="<?= sanitize($ct['pic'] ?: $user['nama_lengkap']) ?>" placeholder="Nama PIC"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                  </td>
                  <td class="py-3 px-3">
                    <input type="text" name="target_selesai[]" value="<?= sanitize($ct['target_selesai']) ?>" placeholder="e.g. 17:00"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                  </td>
                  <td class="py-3 px-3 text-center">
                    <button type="button" onclick="removeTaskRow(this)" title="Hapus Baris"
                            class="p-1 text-slate-400 hover:text-rose-500 transition cursor-pointer">
                      <i class="ph-bold ph-trash text-sm"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr class="task-row">
                <td class="py-3 px-3 text-center font-bold text-slate-400 row-number">1</td>
                <td class="py-3 px-3">
                  <input type="text" name="no_tiket[]" placeholder="TK-01" 
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
                </td>
                <td class="py-3 px-3">
                  <input type="text" name="pekerjaan_masalah[]" placeholder="Uraian pekerjaan/masalah..." required
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-3 px-3">
                  <select name="prioritas[]" onchange="recalculateCounters()"
                          class="task-prioritas w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                    <option value="Tinggi / Kritis">Tinggi / Kritis</option>
                    <option value="Sedang" selected>Sedang</option>
                    <option value="Rendah">Rendah</option>
                  </select>
                </td>
                <td class="py-3 px-3">
                  <select name="status_tugas[]" onchange="recalculateCounters()"
                          class="task-status w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                    <option value="Berjalan" selected>Berjalan</option>
                    <option value="Belum Mulai">Belum Mulai</option>
                    <option value="Tertunda">Tertunda</option>
                    <option value="Selesai">Selesai</option>
                  </select>
                </td>
                <td class="py-3 px-3">
                  <input type="text" name="kondisi_tindak_lanjut[]" placeholder="Instruksi untuk shift baru..."
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-3 px-3">
                  <input type="text" name="pic[]" placeholder="Nama PIC" value="<?= sanitize($user['nama_lengkap']) ?>"
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-3 px-3">
                  <input type="text" name="target_selesai[]" placeholder="e.g. 17:00"
                         class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
                </td>
                <td class="py-3 px-3 text-center">
                  <button type="button" onclick="removeTaskRow(this)" title="Hapus Baris"
                          class="p-1 text-slate-400 hover:text-rose-500 transition cursor-pointer">
                    <i class="ph-bold ph-trash text-sm"></i>
                  </button>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ========================================================
         BAGIAN 3: DOKUMENTASI FOTO / BUKTI VISUAL SHIFT
         ======================================================== -->
    <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
      <div class="flex items-center gap-3 pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-sm">
          3
        </div>
        <div>
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white">DOKUMENTASI FOTO / BUKTI VISUAL SHIFT</h3>
          <p class="text-xs text-slate-400">Sisipkan 3 foto bukti lapangan (kondisi area, kendala, dan bukti serah terima)</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- FOTO 1 -->
        <?php $f1Url = ($isEditDraft && !empty($editReport['foto_1'])) ? (UPLOAD_URL . $editReport['foto_1']) : ''; ?>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 1: Kondisi Area / Peralatan</span>
          <div class="relative w-full h-44 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600 flex flex-col items-center justify-center overflow-hidden group cursor-pointer"
               onclick="document.getElementById('foto_1').click()">
            <img id="preview_foto_1" src="<?= htmlspecialchars($f1Url) ?>" class="<?= $f1Url ? '' : 'hidden' ?> w-full h-full object-cover" />
            <div id="placeholder_foto_1" class="<?= $f1Url ? 'hidden' : '' ?> text-center p-4">
              <i class="ph ph-camera text-3xl text-slate-400 group-hover:text-[#6558f5] transition"></i>
              <p class="text-[11px] font-semibold text-slate-500 mt-1">Upload Foto 1</p>
            </div>
          </div>
          <input type="file" name="foto_1" id="foto_1" accept="image/*" class="hidden" onchange="previewImg(this, 'preview_foto_1', 'placeholder_foto_1')" />
          <div class="mt-3">
            <input type="text" name="ket_foto_1" value="<?= sanitize($isEditDraft ? ($editReport['ket_foto_1'] ?? '') : '') ?>" placeholder="Keterangan Foto 1..."
                   class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </div>
        </div>

        <!-- FOTO 2 -->
        <?php $f2Url = ($isEditDraft && !empty($editReport['foto_2'])) ? (UPLOAD_URL . $editReport['foto_2']) : ''; ?>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 2: Temuan / Kendala / Indikator</span>
          <div class="relative w-full h-44 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600 flex flex-col items-center justify-center overflow-hidden group cursor-pointer"
               onclick="document.getElementById('foto_2').click()">
            <img id="preview_foto_2" src="<?= htmlspecialchars($f2Url) ?>" class="<?= $f2Url ? '' : 'hidden' ?> w-full h-full object-cover" />
            <div id="placeholder_foto_2" class="<?= $f2Url ? 'hidden' : '' ?> text-center p-4">
              <i class="ph ph-warning text-3xl text-slate-400 group-hover:text-[#6558f5] transition"></i>
              <p class="text-[11px] font-semibold text-slate-500 mt-1">Upload Foto 2</p>
            </div>
          </div>
          <input type="file" name="foto_2" id="foto_2" accept="image/*" class="hidden" onchange="previewImg(this, 'preview_foto_2', 'placeholder_foto_2')" />
          <div class="mt-3">
            <input type="text" name="ket_foto_2" value="<?= sanitize($isEditDraft ? ($editReport['ket_foto_2'] ?? '') : '') ?>" placeholder="Keterangan Foto 2..."
                   class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </div>
        </div>

        <!-- FOTO 3 -->
        <?php $f3Url = ($isEditDraft && !empty($editReport['foto_3'])) ? (UPLOAD_URL . $editReport['foto_3']) : ''; ?>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 3: Bukti Serah Terima / Lainnya</span>
          <div class="relative w-full h-44 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600 flex flex-col items-center justify-center overflow-hidden group cursor-pointer"
               onclick="document.getElementById('foto_3').click()">
            <img id="preview_foto_3" src="<?= htmlspecialchars($f3Url) ?>" class="<?= $f3Url ? '' : 'hidden' ?> w-full h-full object-cover" />
            <div id="placeholder_foto_3" class="<?= $f3Url ? 'hidden' : '' ?> text-center p-4">
              <i class="ph ph-handshake text-3xl text-slate-400 group-hover:text-[#6558f5] transition"></i>
              <p class="text-[11px] font-semibold text-slate-500 mt-1">Upload Foto 3</p>
            </div>
          </div>
          <input type="file" name="foto_3" id="foto_3" accept="image/*" class="hidden" onchange="previewImg(this, 'preview_foto_3', 'placeholder_foto_3')" />
          <div class="mt-3">
            <input type="text" name="ket_foto_3" value="<?= sanitize($isEditDraft ? ($editReport['ket_foto_3'] ?? '') : '') ?>" placeholder="Keterangan Foto 3..."
                   class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================
         BAGIAN 4: KONFIRMASI & PENGESAHAN SERAH TERIMA
         ======================================================== -->
    <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
      <div class="flex items-center gap-3 pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-sm">
          4
        </div>
        <div>
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white">KONFIRMASI &amp; PENGESAHAN SERAH TERIMA</h3>
          <p class="text-xs text-slate-400">Pemeriksaan checklist kesiapan operasional dan pembubuhan tanda tangan otomatis dari profil akun</p>
        </div>
      </div>

      <!-- Checklist 4 Pertanyaan -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">1. Pekerjaan &amp; kendala sudah dijelaskan lisan?</span>
          <select name="check_lisan" class="px-3 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-xs font-bold text-indigo-600">
            <option value="Ya" <?= $valCheckLisan === 'Ya' ? 'selected' : '' ?>>Ya</option>
            <option value="Belum" <?= $valCheckLisan === 'Belum' ? 'selected' : '' ?>>Belum</option>
            <option value="N/A" <?= $valCheckLisan === 'N/A' ? 'selected' : '' ?>>N/A</option>
          </select>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">2. Tiket / catatan sistem sudah diperbarui?</span>
          <select name="check_tiket" class="px-3 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-xs font-bold text-indigo-600">
            <option value="Ya" <?= $valCheckTiket === 'Ya' ? 'selected' : '' ?>>Ya</option>
            <option value="Belum" <?= $valCheckTiket === 'Belum' ? 'selected' : '' ?>>Belum</option>
            <option value="N/A" <?= $valCheckTiket === 'N/A' ? 'selected' : '' ?>>N/A</option>
          </select>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">3. Risiko &amp; target waktu sudah diinfokan?</span>
          <select name="check_risiko" class="px-3 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-xs font-bold text-indigo-600">
            <option value="Ya" <?= $valCheckRisiko === 'Ya' ? 'selected' : '' ?>>Ya</option>
            <option value="Belum" <?= $valCheckRisiko === 'Belum' ? 'selected' : '' ?>>Belum</option>
            <option value="N/A" <?= $valCheckRisiko === 'N/A' ? 'selected' : '' ?>>N/A</option>
          </select>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">4. Kunci / alat / akses sudah diserahkan?</span>
          <select name="check_kunci" class="px-3 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-xs font-bold text-indigo-600">
            <option value="Ya" <?= $valCheckKunci === 'Ya' ? 'selected' : '' ?>>Ya</option>
            <option value="Belum" <?= $valCheckKunci === 'Belum' ? 'selected' : '' ?>>Belum</option>
            <option value="N/A" <?= $valCheckKunci === 'N/A' ? 'selected' : '' ?>>N/A</option>
          </select>
        </div>
      </div>

      <!-- Blok Tanda Tangan 2 Pihak -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Pihak 1: Petugas Menyerahkan (Shift Lama) -->
        <div class="p-5 rounded-2xl bg-indigo-50/40 dark:bg-slate-900/50 border border-indigo-100 dark:border-slate-800 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase">Diserahkan Oleh (Shift Lama):</span>
              <span class="text-xs font-black text-[#6558f5]" id="signerMenyerahkanName"><?= sanitize($user['nama_lengkap']) ?></span>
            </div>

            <!-- Tanda Tangan Otomatis Dari Profil -->
            <input type="hidden" name="jenis_ttd_menyerahkan" id="jenis_ttd_menyerahkan" value="<?= htmlspecialchars($user['ttd_type'] ?? 'barcode') ?>" />
            <input type="hidden" name="use_saved_profile_ttd" value="1" />
            <input type="hidden" name="ttd_base64_menyerahkan" id="ttd_base64_menyerahkan" />

            <?php 
            $userTtdType = $user['ttd_type'] ?? 'barcode';
            $hasSavedSig = !empty($user['ttd_file']) && file_exists(UPLOAD_DIR . $user['ttd_file']);
            ?>

            <?php if (($userTtdType === 'upload' || $userTtdType === 'canvas') && $hasSavedSig): ?>
              <!-- TTD Otomatis: Gambar Basah / Gores Canvas Profil -->
              <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-indigo-200 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100 dark:border-slate-800">
                  <div class="flex items-center gap-1.5 text-emerald-600 font-bold text-[11px]">
                    <i class="ph-fill ph-seal-check text-sm"></i>
                    <span>Tanda Tangan Terdaftar di Profil</span>
                  </div>
                  <span class="text-[9.5px] font-mono px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] font-bold uppercase">
                    <?= $userTtdType === 'canvas' ? 'Gores Canvas' : 'TTD Basah' ?>
                  </span>
                </div>
                
                <div class="h-28 flex items-center justify-center p-2 bg-slate-50 dark:bg-slate-800/40 rounded-xl">
                  <img src="<?= BASE_URL . 'uploads/' . $user['ttd_file'] ?>" alt="TTD Resmi" class="max-h-full max-w-full object-contain filter drop-shadow-sm">
                </div>

                <div class="mt-2.5 flex items-center justify-between text-[10.5px]">
                  <span class="text-slate-600 dark:text-slate-300 font-bold"><?= sanitize($user['nama_lengkap']) ?> (<?= sanitize($user['jabatan'] ?? 'Staff') ?>)</span>
                  <span class="text-emerald-600 font-semibold flex items-center gap-1">
                    <i class="ph-bold ph-check"></i> Otomatis Disahkan
                  </span>
                </div>
              </div>

            <?php else: ?>
              <!-- TTD Otomatis: Barcode Digital QR OMIT -->
              <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-indigo-200 dark:border-slate-700 shadow-sm flex items-center gap-3.5">
                <div id="qrCodeContainer" class="p-1.5 bg-white rounded-xl border border-slate-200 shrink-0 shadow-sm"></div>
                <div class="text-[11px] text-slate-600 dark:text-slate-300 space-y-0.5">
                  <div class="font-black text-[#6558f5] text-[10.5px] uppercase tracking-wide flex items-center gap-1">
                    <i class="ph-fill ph-seal-check text-emerald-500"></i>
                    <span>Digital Signature OMIT</span>
                  </div>
                  <div class="font-bold text-slate-800 dark:text-white" id="qrSignerName"><?= sanitize($user['nama_lengkap']) ?></div>
                  <div class="text-[10px] text-slate-400"><?= date('d/m/Y H:i') ?> WIB</div>
                  <div class="text-[9.5px] text-emerald-600 font-semibold flex items-center gap-1">
                    <i class="ph-bold ph-shield-check"></i>
                    <span>Tersertifikasi Sah Sistem</span>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="mt-2.5 flex items-center justify-between text-[10.5px]">
              <span class="text-slate-400">Tanda tangan dibubuhkan otomatis dari akun Anda.</span>
              <a href="<?= BASE_URL ?>profile.php#ttd-section" target="_blank" class="font-bold text-[#6558f5] hover:underline flex items-center gap-1">
                <i class="ph-bold ph-pencil-simple"></i>
                <span>Ubah di Profil</span>
              </a>
            </div>
          </div>

          <div class="mt-3 pt-2 border-t border-slate-200/60 dark:border-slate-800 text-[10px] text-slate-400">
            Penandatangan: Petugas shift lama yang menyerahkan tanggung jawab.
          </div>
        </div>

        <!-- Pihak 2: Petugas Menerima (Shift Baru) -->
        <div class="p-5 rounded-2xl bg-amber-50/40 dark:bg-slate-900/50 border border-amber-200/80 dark:border-slate-800 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase">Diterima Oleh (Shift Baru):</span>
              <span class="text-xs font-black text-amber-600" id="signerMenerimaName">Menunggu Dipilih</span>
            </div>

            <!-- Notice: Receiver signature requires approval -->
            <div class="p-6 rounded-xl border-2 border-dashed border-amber-300 dark:border-amber-700/60 bg-white/70 dark:bg-slate-800/40 text-center flex flex-col items-center justify-center min-h-[140px]">
              <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xl mb-2">
                <i class="ph-bold ph-hourglass-high"></i>
              </div>
              <h4 class="text-xs font-extrabold text-amber-900 dark:text-amber-300">Menunggu Approval Shift Penerima</h4>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-xs mt-1">
                Tanda tangan shift baru <strong>belum ada</strong> dan akan dibubuhkan saat shift baru login dan melakukan konfirmasi approval serah terima.
              </p>
            </div>
          </div>

          <div class="mt-3 flex items-center justify-between text-xs">
            <span class="text-slate-500">Status Validasi Awal:</span>
            <span class="font-black text-amber-600 bg-amber-100 dark:bg-amber-950/60 px-2.5 py-0.5 rounded-full border border-amber-300">
              MENUNGGU VALIDASI
            </span>
          </div>
        </div>

      </div>
    </div>

    <!-- Submit Button -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-2">
      <a href="<?= BASE_URL ?>laporan/index" 
         class="px-6 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-sm transition text-center">
        Batal
      </a>
      <button type="submit" name="submit_action" value="draft" formnovalidate id="btnSaveDraft"
              class="px-6 py-3 rounded-2xl bg-amber-50 dark:bg-amber-950/50 hover:bg-amber-100 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-300/80 dark:border-amber-700/70 font-extrabold text-sm transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-note-pencil text-lg"></i>
        <span>Simpan Draft</span>
      </button>
      <button type="submit" name="submit_action" value="submit" id="btnSubmitForm"
              class="px-8 py-3 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-sm shadow-lg shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer">
        <i class="ph-bold ph-paper-plane-tilt text-lg"></i>
        <span>Simpan &amp; Ajukan ke Shift Penerima</span>
      </button>
    </div>

  </form>
</div>

<!-- JavaScript Logic -->
<script>
  // 1. Dynamic Task Rows
  function addTaskRow() {
    const tbody = document.getElementById('taskRows');
    const rowCount = tbody.querySelectorAll('.task-row').length + 1;
    const tr = document.createElement('tr');
    tr.className = 'task-row';
    tr.innerHTML = `
      <td class="py-3 px-3 text-center font-bold text-slate-400 row-number">${rowCount}</td>
      <td class="py-3 px-3">
        <input type="text" name="no_tiket[]" placeholder="TK-0${rowCount}" 
               class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
      </td>
      <td class="py-3 px-3">
        <input type="text" name="pekerjaan_masalah[]" placeholder="Uraian pekerjaan/masalah..." required
               class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
      </td>
      <td class="py-3 px-3">
        <select name="prioritas[]" onchange="recalculateCounters()"
                class="task-prioritas w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
          <option value="Tinggi / Kritis">Tinggi / Kritis</option>
          <option value="Sedang" selected>Sedang</option>
          <option value="Rendah">Rendah</option>
        </select>
      </td>
      <td class="py-3 px-3">
        <select name="status_tugas[]" onchange="recalculateCounters()"
                class="task-status w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
          <option value="Berjalan" selected>Berjalan</option>
          <option value="Belum Mulai">Belum Mulai</option>
          <option value="Tertunda">Tertunda</option>
          <option value="Selesai">Selesai</option>
        </select>
      </td>
      <td class="py-3 px-3">
        <input type="text" name="kondisi_tindak_lanjut[]" placeholder="Instruksi untuk shift baru..."
               class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
      </td>
      <td class="py-3 px-3">
        <input type="text" name="pic[]" placeholder="Nama PIC"
               class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
      </td>
      <td class="py-3 px-3">
        <input type="text" name="target_selesai[]" placeholder="e.g. 17:00"
               class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
      </td>
      <td class="py-3 px-3 text-center">
        <button type="button" onclick="removeTaskRow(this)" title="Hapus Baris"
                class="p-1 text-slate-400 hover:text-rose-500 transition">
          <i class="ph-bold ph-trash text-sm"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
    renumberTaskRows();
    recalculateCounters();
  }

  function removeTaskRow(btn) {
    const tbody = document.getElementById('taskRows');
    if (tbody.querySelectorAll('.task-row').length > 1) {
      btn.closest('.task-row').remove();
      renumberTaskRows();
      recalculateCounters();
    } else {
      alert('Minimal harus ada 1 baris pekerjaan.');
    }
  }

  function renumberTaskRows() {
    document.querySelectorAll('.task-row').forEach((row, idx) => {
      row.querySelector('.row-number').innerText = idx + 1;
    });
  }

  function clearCarryTasks() {
    if (confirm('Kosongkan semua pekerjaan operan dan mulai dengan 1 baris kosong?')) {
      const tbody = document.getElementById('taskRows');
      tbody.innerHTML = `
        <tr class="task-row">
          <td class="py-3 px-3 text-center font-bold text-slate-400 row-number">1</td>
          <td class="py-3 px-3">
            <input type="text" name="no_tiket[]" placeholder="TK-01" 
                   class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono" />
          </td>
          <td class="py-3 px-3">
            <input type="text" name="pekerjaan_masalah[]" placeholder="Uraian pekerjaan/masalah..." required
                   class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </td>
          <td class="py-3 px-3">
            <select name="prioritas[]" onchange="recalculateCounters()"
                    class="task-prioritas w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
              <option value="Tinggi / Kritis">Tinggi / Kritis</option>
              <option value="Sedang" selected>Sedang</option>
              <option value="Rendah">Rendah</option>
            </select>
          </td>
          <td class="py-3 px-3">
            <select name="status_tugas[]" onchange="recalculateCounters()"
                    class="task-status w-full px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
              <option value="Berjalan" selected>Berjalan</option>
              <option value="Belum Mulai">Belum Mulai</option>
              <option value="Tertunda">Tertunda</option>
              <option value="Selesai">Selesai</option>
            </select>
          </td>
          <td class="py-3 px-3">
            <input type="text" name="kondisi_tindak_lanjut[]" placeholder="Instruksi untuk shift baru..."
                   class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </td>
          <td class="py-3 px-3">
            <input type="text" name="pic[]" placeholder="Nama PIC"
                   class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </td>
          <td class="py-3 px-3">
            <input type="text" name="target_selesai[]" placeholder="e.g. 17:00"
                   class="w-full px-2.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs" />
          </td>
          <td class="py-3 px-3 text-center">
            <button type="button" onclick="removeTaskRow(this)" title="Hapus Baris"
                    class="p-1 text-slate-400 hover:text-rose-500 transition cursor-pointer">
              <i class="ph-bold ph-trash text-sm"></i>
            </button>
          </td>
        </tr>
      `;
      const banner = document.getElementById('carryOverBanner');
      if (banner) banner.remove();
      recalculateCounters();
    }
  }

  // 2. Real-time Counter Calculation
  function recalculateCounters() {
    let totalAktif = 0;
    let prioritasKritis = 0;

    document.querySelectorAll('.task-row').forEach(row => {
      const status = row.querySelector('.task-status').value;
      const prioritas = row.querySelector('.task-prioritas').value;

      if (status !== 'Selesai') totalAktif++;
      if (prioritas === 'Tinggi / Kritis' && status !== 'Selesai') prioritasKritis++;
    });

    document.getElementById('badgeTotalAktif').innerText = totalAktif;
    document.getElementById('badgePrioritasKritis').innerText = prioritasKritis;
  }

  // 3. Image Preview
  function previewImg(input, previewId, placeholderId) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
      }
      reader.readAsDataURL(input.files[0]);
    }
  }

  // 4. Update Receiver Name
  function updateReceiverName() {
    const sel = document.getElementById('petugas_menerima_id');
    const nameSpan = document.getElementById('signerMenerimaName');
    if (sel && sel.value) {
      const opt = sel.options[sel.selectedIndex];
      nameSpan.innerText = opt.getAttribute('data-name') || opt.text.split('(')[0].trim();
    } else {
      nameSpan.innerText = 'Menunggu Dipilih';
    }
  }

  // 5. Shift & Handover Time Synchronization
  function handleShiftChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.handover) {
      document.getElementById('jam_serah_terima').value = opt.dataset.handover;
    }
  }

  function setHandoverToShift() {
    const sel = document.getElementById('shift_id');
    if (sel) handleShiftChange(sel);
  }

  function setHandoverToNow() {
    const now = new Date();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('jam_serah_terima').value = `${hh}:${mm}`;
  }

  // 6. QR Code Barcode Digital Generator (Jika Menggunakan Barcode QR)
  let qrcodeObj = null;
  function updateSignerAndQR() {
    const qrContainer = document.getElementById('qrCodeContainer');
    if (!qrContainer) return;

    const signerName = '<?= addslashes(sanitize($user['nama_lengkap'])) ?>';
    const docNo = document.getElementById('docNumberInput').value;

    const signerEl = document.getElementById('signerMenyerahkanName');
    if (signerEl) signerEl.innerText = signerName;
    const qrSignerEl = document.getElementById('qrSignerName');
    if (qrSignerEl) qrSignerEl.innerText = signerName;

    // Generate Verification Payload (gunakan Domain dari Pengaturan jika ada, atau origin + BASE_URL saat ini)
    <?php $customAppUrl = rtrim(get_setting('app_url', get_setting('app_base_url_full', '')), '/'); ?>
    const verifyBase = <?= $customAppUrl !== '' ? json_encode($customAppUrl . '/laporan/verify?doc=') : 'window.location.origin + ' . json_encode(BASE_URL . 'laporan/verify?doc=') ?>;
    const verifyUrl = verifyBase + encodeURIComponent(docNo);
    
    qrContainer.innerHTML = '';
    qrcodeObj = new QRCode(qrContainer, {
      text: verifyUrl,
      width: 74,
      height: 74,
      colorDark: "#1e1b4b",
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // 7. Capture Signature on Form Submit
  document.getElementById('formHandover').addEventListener('submit', function() {
    const qrCanvas = document.querySelector('#qrCodeContainer canvas');
    if (qrCanvas) {
      document.getElementById('ttd_base64_menyerahkan').value = qrCanvas.toDataURL('image/png');
    } else {
      const qrImg = document.querySelector('#qrCodeContainer img');
      if (qrImg) document.getElementById('ttd_base64_menyerahkan').value = qrImg.src;
    }
  });

  // Init on load
  window.addEventListener('DOMContentLoaded', () => {
    updateSignerAndQR();
    updateReceiverName();
    recalculateCounters();
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
