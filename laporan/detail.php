<?php
// laporan/detail.php - Detail Formulir & Workflow Approval Serah Terima Shift
$current_page = 'laporan_list';
$page_title = 'Detail Laporan Serah Terima';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'laporan/index');
    exit;
}

// Auto-migrating table for follow-up comments
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `laporan_comments` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `laporan_id` INT(11) NOT NULL,
          `user_id` INT(11) NOT NULL,
          `nama_user` VARCHAR(100) NOT NULL,
          `role_user` VARCHAR(30) NOT NULL DEFAULT 'user',
          `komentar` TEXT NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_comment_laporan` (`laporan_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    $komentar = trim($_POST['komentar'] ?? '');
    if ($komentar !== '') {
        $pdo->prepare("INSERT INTO laporan_comments (laporan_id, user_id, nama_user, role_user, komentar) VALUES (:lid, :uid, :nama, :role, :kom)")
            ->execute([
                'lid'  => $id,
                'uid'  => (int)$user['id'],
                'nama' => $user['nama_lengkap'],
                'role' => $user['role'] ?? 'user',
                'kom'  => $komentar,
            ]);
        require_once __DIR__ . '/../includes/audit_helper.php';
        log_activity($pdo, 'HANDOVER_COMMENT', "Menambahkan catatan tindak lanjut pada laporan #{$id}.");
        flash_set('success', 'Catatan tindak lanjut berhasil ditambahkan.');
    }
    header('Location: ' . BASE_URL . 'laporan/detail?id=' . $id . '#comments');
    exit;
}

// Fetch Report
$stmt = $pdo->prepare("
    SELECT l.*, s.nama_shift, s.jam_mulai, s.jam_selesai, m.nama_lokasi,
           u1.nama_lengkap AS nama_menyerahkan, u1.jabatan AS jabatan_menyerahkan, u1.email AS email_menyerahkan, u1.no_wa AS wa_menyerahkan, u1.foto AS foto_menyerahkan,
           u2.nama_lengkap AS nama_menerima, u2.jabatan AS jabatan_menerima, u2.email AS email_menerima, u2.no_wa AS wa_menerima, u2.foto AS foto_menerima
    FROM laporan_serah_terima l
    JOIN master_shift s ON l.shift_id = s.id
    JOIN master_lokasi m ON l.lokasi_id = m.id
    JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
    LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
    WHERE l.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$report = $stmt->fetch();

if (!$report) {
    flash_set('error', 'Laporan serah terima tidak ditemukan.');
    header('Location: ' . BASE_URL . 'laporan/index');
    exit;
}

// Fetch Tasks
$stmtTasks = $pdo->prepare("SELECT * FROM laporan_pekerjaan WHERE laporan_id = :id ORDER BY urutan ASC, id ASC");
$stmtTasks->execute(['id' => $id]);
$tasks = $stmtTasks->fetchAll();

// Fetch Follow-up Comments
$comments = [];
try {
    $stmtCom = $pdo->prepare("SELECT * FROM laporan_comments WHERE laporan_id = :id ORDER BY created_at ASC, id ASC");
    $stmtCom->execute(['id' => $id]);
    $comments = $stmtCom->fetchAll();
} catch (Throwable $e) {
}

$totalAktif = 0;
$totalKritis = 0;
foreach ($tasks as $t) {
    if ($t['status'] !== 'Selesai') $totalAktif++;
    if ($t['prioritas'] === 'Tinggi / Kritis' && $t['status'] !== 'Selesai') $totalKritis++;
}

// Check if user is eligible to approve/validate
$canValidate = ($report['status_validasi'] === 'MENUNGGU VALIDASI') && 
               ($user['id'] == $report['petugas_menerima_id'] || is_admin() || empty($report['petugas_menerima_id']));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Include QRCode JS -->
<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>

<div class="max-w-6xl mx-auto pb-12 space-y-6">

  <!-- Top Action Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <a href="<?= BASE_URL ?>laporan/index" class="text-xs font-bold text-[#6558f5] hover:underline flex items-center gap-1 mb-2">
        <i class="ph-bold ph-arrow-left"></i>
        <span>Kembali ke Daftar Laporan</span>
      </a>
      <div class="flex items-center gap-3">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
          <?= sanitize($report['nomor_laporan']) ?>
        </h2>
        <?= badge_status_validasi($report['status_validasi']) ?>
      </div>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Dibuat pada <?= date('d M Y, H:i', strtotime($report['created_at'])) ?> WIB oleh <strong><?= sanitize($report['nama_menyerahkan']) ?></strong>
      </p>
    </div>

    <div class="flex items-center gap-2.5 flex-wrap">
      <?php if ($report['status_validasi'] === 'DRAFT' && ($report['created_by'] == $user['id'] || is_admin())): ?>
        <a href="<?= BASE_URL ?>laporan/create?edit=<?= $report['id'] ?>"
           class="px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 active:scale-95">
          <i class="ph-bold ph-pencil-simple text-base"></i>
          <span>Lanjutkan Edit Draft</span>
        </a>
      <?php endif; ?>
      <?php
      require_once __DIR__ . '/../includes/notifications.php';
      $targetWaPhone = ($report['status_validasi'] === 'MENUNGGU VALIDASI') ? ($report['wa_menerima'] ?: '') : ($report['wa_menyerahkan'] ?: '');
      if ($targetWaPhone === '081234567890' || $targetWaPhone === '6281234567890') {
          $targetWaPhone = '';
      }
      $targetWaName  = ($report['status_validasi'] === 'MENUNGGU VALIDASI') ? ($report['nama_menerima'] ?: 'Rekan Shift Baru') : ($report['nama_menyerahkan'] ?: 'Petugas Shift');
      
      // Default: tanpa tautan publik di pesan WhatsApp sesuai permintaan, namun tautan publik tetap ada di sistem dan opsional jika ingin disertakan
      $waMsgWithoutLink = build_handover_whatsapp_message($report, $tasks, false);
      $waMsgWithLink    = build_handover_whatsapp_message($report, $tasks, true);
      $waFullMsg        = $waMsgWithoutLink;
      ?>

      <!-- WhatsApp Share Button with Rich Modal -->
      <button type="button" onclick="openWhatsAppModal()"
              title="Kirim Rincian Pekerjaan &amp; Outstanding via WhatsApp"
              class="px-4 py-2.5 rounded-2xl bg-[#25D366] hover:bg-[#20bd5a] text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 active:scale-95">
        <i class="ph-bold ph-whatsapp-logo text-base"></i>
        <span>Kirim ke WhatsApp</span>
      </button>

      <!-- Copy Public Share Link (No Login Required) -->
      <?php $publicShareUrl = get_full_web_url("laporan/share?id={$report['id']}&token=" . get_report_share_token((int)$report['id'], $report['nomor_laporan'])); ?>
      <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($publicShareUrl, ENT_QUOTES) ?>').then(() => alert('Link laporan tanpa login berhasil disalin!'))"
              title="Salin tautan baca-saja khusus laporan ini (tanpa login)"
              class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-link text-base"></i>
        <span>Salin Link Publik</span>
      </button>

      <!-- Print Button (1:1 OMIT Template) -->
      <a href="<?= BASE_URL ?>laporan/print?id=<?= $report['id'] ?>" target="_blank"
         class="px-5 py-2.5 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 transition flex items-center gap-2 active:scale-95">
        <i class="ph-bold ph-printer text-base"></i>
        <span>Cetak PDF Standar OMIT</span>
      </a>
    </div>
  </div>

  <!-- ========================================================
       WORKFLOW APPROVAL BOX (HANYA MUNCUL JIKA MENUNGGU VALIDASI)
       ======================================================== -->
  <?php if ($canValidate): ?>
    <div class="p-6 sm:p-7 rounded-[26px] bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 text-white shadow-xl">
      <div class="flex items-start justify-between gap-4 mb-5">
        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-2xl font-bold flex-shrink-0">
            <i class="ph-bold ph-signature"></i>
          </div>
          <div>
            <h3 class="text-lg font-black tracking-tight">Persetujuan &amp; Tanda Tangan Penerima Shift Baru</h3>
            <p class="text-xs text-amber-100 mt-0.5">
              Laporan ini menunggu persetujuan (approval) resmi dari Anda sebagai petugas shift penerima: <strong><?= sanitize($user['nama_lengkap']) ?></strong>.
            </p>
          </div>
        </div>
      </div>

      <form method="POST" action="<?= BASE_URL ?>laporan/validate" enctype="multipart/form-data" id="formApprovalShift" class="bg-white/10 dark:bg-slate-900/40 p-5 rounded-2xl backdrop-blur-md space-y-5">
        <input type="hidden" name="laporan_id" value="<?= $report['id'] ?>" />
        <input type="hidden" name="jenis_ttd_menerima" id="jenis_ttd_menerima" value="<?= htmlspecialchars($user['ttd_type'] ?? 'barcode') ?>" />
        <input type="hidden" name="use_saved_profile_ttd" value="1" />
        <input type="hidden" name="ttd_base64_menerima" id="ttd_base64_menerima" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
          
          <!-- Signature Automatic Box from Profile -->
          <div>
            <?php 
            $userTtdType = $user['ttd_type'] ?? 'barcode';
            $hasSavedSig = !empty($user['ttd_file']) && file_exists(UPLOAD_DIR . $user['ttd_file']);
            ?>

            <?php if (($userTtdType === 'upload' || $userTtdType === 'canvas') && $hasSavedSig): ?>
              <!-- TTD Otomatis: Profil -->
              <div class="p-3.5 bg-white rounded-xl text-slate-800 shadow-sm">
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                  <div class="flex items-center gap-1.5 text-emerald-600 font-bold text-[11px]">
                    <i class="ph-fill ph-seal-check text-sm"></i>
                    <span>Tanda Tangan Otomatis Profil</span>
                  </div>
                  <span class="text-[9.5px] font-mono px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold uppercase">
                    <?= $userTtdType === 'canvas' ? 'Gores Canvas' : 'TTD Basah' ?>
                  </span>
                </div>
                
                <div class="h-24 flex items-center justify-center p-2 bg-slate-50 rounded-lg">
                  <img src="<?= BASE_URL . 'uploads/' . $user['ttd_file'] ?>" alt="TTD Resmi" class="max-h-full max-w-full object-contain filter drop-shadow-sm">
                </div>

                <div class="mt-2 flex items-center justify-between text-[10.5px]">
                  <span class="text-slate-700 font-bold"><?= sanitize($user['nama_lengkap']) ?> (<?= sanitize($user['jabatan'] ?? 'Staff') ?>)</span>
                  <span class="text-emerald-600 font-semibold flex items-center gap-1">
                    <i class="ph-bold ph-check"></i> Siap Disahkan
                  </span>
                </div>
              </div>

            <?php else: ?>
              <!-- TTD Otomatis: Digital Approval Barcode QR -->
              <div class="p-3.5 bg-white rounded-xl text-slate-800 shadow-sm flex items-center gap-3.5">
                <div id="qrReceiverContainer" class="p-1.5 bg-white rounded-lg border border-slate-200 shrink-0"></div>
                <div class="text-xs space-y-0.5">
                  <div class="font-black text-[#6558f5] text-[10.5px] uppercase flex items-center gap-1">
                    <i class="ph-fill ph-seal-check text-emerald-500"></i>
                    <span>Digital Approval OMIT</span>
                  </div>
                  <div class="font-bold text-slate-900"><?= sanitize($user['nama_lengkap']) ?></div>
                  <div class="text-[10px] text-slate-500"><?= date('d/m/Y H:i') ?> WIB</div>
                  <div class="text-[9.5px] text-emerald-600 font-bold flex items-center gap-1">
                    <i class="ph-bold ph-shield-check"></i>
                    <span>Penerima Shift Terverifikasi</span>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="mt-2 flex items-center justify-between text-[11px] text-white/80">
              <span class="text-[10.5px]">Tanda tangan dibubuhkan otomatis dari profil Anda.</span>
              <a href="<?= BASE_URL ?>profile.php#ttd-section" target="_blank" class="text-white hover:underline font-bold flex items-center gap-1">
                <i class="ph-bold ph-pencil-simple"></i>
                <span>Ubah di Profil</span>
              </a>
            </div>
          </div>

          <!-- Notes & Confirm Action -->
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-white mb-1">Catatan Penerimaan / Serah Terima (Opsional):</label>
              <textarea name="catatan_approval" rows="2" placeholder="Catatan tambahan saat penerimaan shift..."
                        class="w-full px-3 py-2 rounded-xl bg-white/20 border border-white/30 text-white placeholder-white/60 text-xs focus:outline-none focus:ring-2 focus:ring-white"></textarea>
            </div>

            <button type="submit" 
                    class="w-full py-3 px-6 rounded-xl bg-white hover:bg-amber-50 text-amber-900 font-black text-sm shadow-lg transition flex items-center justify-center gap-2 cursor-pointer active:scale-95">
              <i class="ph-bold ph-check-circle text-lg text-emerald-600"></i>
              <span>Setujui &amp; Sahkan Serah Terima (Approve)</span>
            </button>
          </div>

        </div>
      </form>
    </div>
  <?php endif; ?>

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
        <p class="text-xs text-slate-400">Parameter utama waktu, lokasi, dan kondisi shift</p>
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
      <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tanggal</span>
        <span class="text-xs font-bold text-slate-800 dark:text-slate-100 mt-1 block"><?= format_tanggal($report['tanggal']) ?></span>
      </div>

      <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Shift</span>
        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-1 block">Shift <?= sanitize($report['nama_shift']) ?></span>
      </div>

      <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jam Serah Terima</span>
        <span class="text-xs font-bold text-slate-800 dark:text-slate-100 mt-1 block"><?= format_jam($report['jam_serah_terima']) ?> WIB</span>
      </div>

      <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Lokasi / Unit</span>
        <span class="text-xs font-bold text-slate-800 dark:text-slate-100 mt-1 block"><?= sanitize($report['nama_lokasi']) ?></span>
      </div>

      <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kondisi Operasional</span>
        <div class="mt-1"><?= badge_kondisi_operasional($report['kondisi_operasional']) ?></div>
      </div>

      <div class="p-3 rounded-xl bg-emerald-50 dark:bg-slate-900/60 border border-emerald-100 dark:border-slate-800">
        <span class="text-[10px] font-bold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider block">Tugas Aktif / Kritis</span>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300"><?= $totalAktif ?> Aktif</span>
          <span>•</span>
          <span class="text-xs font-bold text-rose-600"><?= $totalKritis ?> Kritis</span>
        </div>
      </div>
    </div>

    <!-- Petugas Menyerahkan & Menerima -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-indigo-50/40 dark:bg-slate-900/40 border border-indigo-100/60 dark:border-slate-800 mb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-tr from-[#6558f5] to-indigo-400 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm ring-2 ring-indigo-200 dark:ring-indigo-900">
          <?php $avSerah = get_user_avatar_url($report['foto_menyerahkan'] ?? null); ?>
          <?php if ($avSerah): ?>
            <img src="<?= $avSerah ?>" alt="Foto" class="w-full h-full object-cover">
          <?php else: ?>
            <?= strtoupper(substr($report['nama_menyerahkan'] ?? 'P', 0, 2)) ?>
          <?php endif; ?>
        </div>
        <div>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Petugas Menyerahkan (Shift Lama)</span>
          <div class="text-xs font-extrabold text-slate-800 dark:text-slate-100 mt-0.5">
            <?= sanitize($report['nama_menyerahkan']) ?>
            <span class="text-[11px] font-normal text-slate-500">(<?= sanitize($report['jabatan_menyerahkan'] ?? '-') ?>)</span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-tr from-[#68cfa3] to-teal-400 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm ring-2 ring-teal-200 dark:ring-teal-900">
          <?php $avTerima = get_user_avatar_url($report['foto_menerima'] ?? null); ?>
          <?php if ($avTerima): ?>
            <img src="<?= $avTerima ?>" alt="Foto" class="w-full h-full object-cover">
          <?php else: ?>
            <?= strtoupper(substr($report['nama_menerima'] ?? 'M', 0, 2)) ?>
          <?php endif; ?>
        </div>
        <div>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Petugas Menerima (Shift Baru)</span>
          <div class="text-xs font-extrabold text-slate-800 dark:text-slate-100 mt-0.5">
            <?= sanitize($report['nama_menerima'] ?? 'Menunggu Approval') ?>
            <?php if (!empty($report['jabatan_menerima'])): ?>
              <span class="text-[11px] font-normal text-slate-500">(<?= sanitize($report['jabatan_menerima']) ?>)</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Catatan Penting -->
    <?php if (!empty($report['catatan_penting'])): ?>
      <div class="p-4 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 text-xs">
        <span class="font-bold text-amber-900 dark:text-amber-300 uppercase text-[10px] block mb-1">Catatan Penting / Kendala Utama Shift Ini:</span>
        <p class="text-slate-700 dark:text-slate-300 leading-relaxed"><?= nl2br(sanitize($report['catatan_penting'])) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ========================================================
         BAGIAN 2: DAFTAR PEKERJAAN & TINDAK LANJUT SHIFT BERIKUTNYA
         ======================================================== -->
  <div class="bg-white dark:bg-[#131b2e] rounded-[24px] border border-slate-200/80 dark:border-slate-800 shadow-card p-6 sm:p-8">
    <div class="flex items-center gap-3 pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
      <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-sm">
        2
      </div>
      <div>
        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">DAFTAR PEKERJAAN &amp; TINDAK LANJUT UNTUK SHIFT BERIKUTNYA</h3>
        <p class="text-xs text-slate-400">Rincian pekerjaan yang dipantau atau ditindaklanjuti</p>
      </div>
    </div>

    <?php if ($totalAktif > 0): ?>
      <div class="mb-5 p-4 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/40 dark:to-orange-950/30 border border-amber-200/90 dark:border-amber-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl font-bold shrink-0 shadow-sm">
            <i class="ph-bold ph-warning"></i>
          </div>
          <div>
            <h4 class="text-xs font-black text-amber-900 dark:text-amber-200 flex items-center gap-2">
              <span>Terdapat <?= $totalAktif ?> Pekerjaan Belum Selesai</span>
              <?php if ($totalKritis > 0): ?>
                <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] font-extrabold animate-pulse"><?= $totalKritis ?> Butuh Eskalasi Kritis</span>
              <?php endif; ?>
            </h4>
            <p class="text-[11px] text-amber-800/80 dark:text-amber-300/80 mt-0.5">
              Pekerjaan ini harus ditindaklanjuti oleh petugas shift baru. Klik tombol di samping untuk langsung membawa tugas ini ke form serah terima shift baru.
            </p>
          </div>
        </div>
        <a href="<?= BASE_URL ?>laporan/create?carry_from=<?= $report['id'] ?>" 
           class="px-4 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 self-start sm:self-auto cursor-pointer whitespace-nowrap">
          <i class="ph-bold ph-arrow-bend-down-right text-sm"></i>
          <span>Operkan ke Shift Baru</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase tracking-wider">
          <tr>
            <th class="py-2.5 px-3 w-10 text-center rounded-l-xl">No</th>
            <th class="py-2.5 px-3 w-28">No / Tiket</th>
            <th class="py-2.5 px-3">Pekerjaan / Masalah</th>
            <th class="py-2.5 px-3 w-32">Prioritas</th>
            <th class="py-2.5 px-3 w-32">Status</th>
            <th class="py-2.5 px-3">Kondisi Terakhir &amp; Tindak Lanjut</th>
            <th class="py-2.5 px-3 w-32">PIC</th>
            <th class="py-2.5 px-3 w-28 rounded-r-xl">Target</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <?php if (empty($tasks)): ?>
            <tr>
              <td colspan="8" class="py-6 text-center text-slate-400">Tidak ada rincian pekerjaan pada shift ini.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($tasks as $idx => $t): ?>
              <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                <td class="py-3 px-3 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                <td class="py-3 px-3 font-mono font-bold text-[#6558f5]"><?= sanitize($t['no_tiket'] ?: '-') ?></td>
                <td class="py-3 px-3 font-semibold text-slate-800 dark:text-slate-100"><?= sanitize($t['pekerjaan_masalah']) ?></td>
                <td class="py-3 px-3"><?= badge_prioritas($t['prioritas']) ?></td>
                <td class="py-3 px-3"><?= badge_status_pekerjaan($t['status']) ?></td>
                <td class="py-3 px-3 text-slate-600 dark:text-slate-300"><?= sanitize($t['kondisi_tindak_lanjut']) ?></td>
                <td class="py-3 px-3 font-medium text-slate-700 dark:text-slate-200"><?= sanitize($t['pic']) ?></td>
                <td class="py-3 px-3 text-slate-500"><?= sanitize($t['target_selesai'] ?: '-') ?></td>
              </tr>
            <?php endforeach; ?>
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
        <p class="text-xs text-slate-400">Bukti visual lapangan, kondisi peralatan, dan penyerahan shift</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Foto 1 -->
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 1: Kondisi Area / Peralatan</span>
        <div class="w-full h-48 rounded-xl bg-slate-100 dark:bg-slate-800 overflow-hidden flex items-center justify-center">
          <?php if (!empty($report['foto_1']) && file_exists(UPLOAD_DIR . $report['foto_1'])): ?>
            <img src="<?= UPLOAD_URL . $report['foto_1'] ?>" alt="Foto 1" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition" onclick="window.open(this.src)" />
          <?php else: ?>
            <div class="text-center p-4 text-slate-400">
              <i class="ph ph-image text-3xl mb-1 block"></i>
              <span class="text-[11px] font-semibold">Tidak ada foto dilampirkan</span>
            </div>
          <?php endif; ?>
        </div>
        <div class="mt-3 text-xs">
          <span class="font-bold text-slate-500 block text-[10px] uppercase">Keterangan Foto 1:</span>
          <p class="text-slate-700 dark:text-slate-300 mt-0.5"><?= sanitize($report['ket_foto_1'] ?: 'Tidak ada keterangan.') ?></p>
        </div>
      </div>

      <!-- Foto 2 -->
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 2: Temuan / Kendala / Indikator</span>
        <div class="w-full h-48 rounded-xl bg-slate-100 dark:bg-slate-800 overflow-hidden flex items-center justify-center">
          <?php if (!empty($report['foto_2']) && file_exists(UPLOAD_DIR . $report['foto_2'])): ?>
            <img src="<?= UPLOAD_URL . $report['foto_2'] ?>" alt="Foto 2" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition" onclick="window.open(this.src)" />
          <?php else: ?>
            <div class="text-center p-4 text-slate-400">
              <i class="ph ph-image text-3xl mb-1 block"></i>
              <span class="text-[11px] font-semibold">Tidak ada foto dilampirkan</span>
            </div>
          <?php endif; ?>
        </div>
        <div class="mt-3 text-xs">
          <span class="font-bold text-slate-500 block text-[10px] uppercase">Keterangan Foto 2:</span>
          <p class="text-slate-700 dark:text-slate-300 mt-0.5"><?= sanitize($report['ket_foto_2'] ?: 'Tidak ada keterangan.') ?></p>
        </div>
      </div>

      <!-- Foto 3 -->
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Foto 3: Bukti Serah Terima / Lainnya</span>
        <div class="w-full h-48 rounded-xl bg-slate-100 dark:bg-slate-800 overflow-hidden flex items-center justify-center">
          <?php if (!empty($report['foto_3']) && file_exists(UPLOAD_DIR . $report['foto_3'])): ?>
            <img src="<?= UPLOAD_URL . $report['foto_3'] ?>" alt="Foto 3" class="w-full h-full object-cover cursor-pointer hover:scale-105 transition" onclick="window.open(this.src)" />
          <?php else: ?>
            <div class="text-center p-4 text-slate-400">
              <i class="ph ph-image text-3xl mb-1 block"></i>
              <span class="text-[11px] font-semibold">Tidak ada foto dilampirkan</span>
            </div>
          <?php endif; ?>
        </div>
        <div class="mt-3 text-xs">
          <span class="font-bold text-slate-500 block text-[10px] uppercase">Keterangan Foto 3:</span>
          <p class="text-slate-700 dark:text-slate-300 mt-0.5"><?= sanitize($report['ket_foto_3'] ?: 'Tidak ada keterangan.') ?></p>
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
        <p class="text-xs text-slate-400">Verifikasi checklist dan tanda tangan kedua belah pihak</p>
      </div>
    </div>

    <!-- 4 Checklist Items -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">1. Pekerjaan &amp; kendala sudah dijelaskan lisan?</span>
        <span class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] font-extrabold text-xs">
          <?= sanitize($report['check_lisan']) ?>
        </span>
      </div>

      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">2. Tiket / catatan sistem sudah diperbarui?</span>
        <span class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] font-extrabold text-xs">
          <?= sanitize($report['check_tiket']) ?>
        </span>
      </div>

      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">3. Risiko &amp; target waktu sudah diinfokan?</span>
        <span class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] font-extrabold text-xs">
          <?= sanitize($report['check_risiko']) ?>
        </span>
      </div>

      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">4. Kunci / alat / akses sudah diserahkan?</span>
        <span class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-[#6558f5] font-extrabold text-xs">
          <?= sanitize($report['check_kunci']) ?>
        </span>
      </div>
    </div>

    <!-- Signatures Preview (Both parties) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- Menyerahkan (Shift Lama) -->
      <div class="p-5 rounded-2xl bg-indigo-50/40 dark:bg-slate-900/50 border border-indigo-100 dark:border-slate-800 flex flex-col items-center">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diserahkan Oleh (Shift Lama):</span>
        
        <div class="w-full h-36 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-center overflow-hidden mb-3 p-2">
          <?php if (!empty($report['ttd_menyerahkan'])): ?>
            <?php if (str_starts_with($report['ttd_menyerahkan'], 'data:image')): ?>
              <img src="<?= $report['ttd_menyerahkan'] ?>" alt="TTD Menyerahkan" class="max-h-full max-w-full object-contain" />
            <?php elseif (file_exists(UPLOAD_DIR . $report['ttd_menyerahkan'])): ?>
              <img src="<?= UPLOAD_URL . $report['ttd_menyerahkan'] ?>" alt="TTD Menyerahkan" class="max-h-full max-w-full object-contain" />
            <?php else: ?>
              <div class="text-center text-xs font-bold text-emerald-600 flex items-center gap-1">
                <i class="ph-bold ph-seal-check text-lg"></i>
                <span>Tanda Tangan Digital Terverifikasi</span>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-xs text-slate-400 italic">Tanda tangan digital tercatat di sistem</span>
          <?php endif; ?>
        </div>

        <div class="text-center">
          <div class="text-xs font-black text-slate-900 dark:text-white"><?= sanitize($report['nama_menyerahkan']) ?></div>
          <div class="text-[10px] text-slate-400"><?= sanitize($report['jabatan_menyerahkan'] ?? 'Petugas') ?></div>
          <div class="text-[9px] text-emerald-600 font-semibold mt-0.5">Metode TTD: <?= strtoupper($report['jenis_ttd_menyerahkan'] ?? 'Digital') ?></div>
        </div>
      </div>

      <!-- Menerima (Shift Baru) -->
      <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 flex flex-col items-center">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diterima Oleh (Shift Baru):</span>
        
        <div class="w-full h-36 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-center overflow-hidden mb-3 p-2">
          <?php if ($report['status_validasi'] === 'TERVALIDASI' && !empty($report['ttd_menerima'])): ?>
            <?php if (str_starts_with($report['ttd_menerima'], 'data:image')): ?>
              <img src="<?= $report['ttd_menerima'] ?>" alt="TTD Menerima" class="max-h-full max-w-full object-contain" />
            <?php elseif (file_exists(UPLOAD_DIR . $report['ttd_menerima'])): ?>
              <img src="<?= UPLOAD_URL . $report['ttd_menerima'] ?>" alt="TTD Menerima" class="max-h-full max-w-full object-contain" />
            <?php else: ?>
              <div class="text-center text-xs font-bold text-emerald-600 flex items-center gap-1">
                <i class="ph-bold ph-seal-check text-lg"></i>
                <span>Tervalidasi Digital</span>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-center p-3 text-amber-600">
              <i class="ph ph-hourglass-medium text-3xl mb-1 block"></i>
              <span class="text-xs font-extrabold block">Menunggu Approval &amp; TTD Shift Baru</span>
              <span class="text-[10px] text-slate-400 block mt-0.5">(Tanda tangan akan muncul setelah disetujui)</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="text-center">
          <div class="text-xs font-black text-slate-900 dark:text-white"><?= sanitize($report['nama_menerima'] ?? 'Menunggu Pengesahan') ?></div>
          <div class="text-[10px] text-slate-400">
            <?= $report['waktu_divalidasi'] ? 'Divalidasi: ' . date('d/m/Y H:i', strtotime($report['waktu_divalidasi'])) . ' WIB' : 'Belum Divalidasi' ?>
          </div>
          <?php if ($report['status_validasi'] === 'TERVALIDASI'): ?>
            <div class="text-[9px] text-emerald-600 font-semibold mt-0.5">Metode TTD: <?= strtoupper($report['jenis_ttd_menerima'] ?? 'Digital') ?></div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Catatan Approval jika ada -->
    <?php if (!empty($report['catatan_approval'])): ?>
      <div class="mt-4 p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/60 text-xs">
        <span class="font-bold text-emerald-800 dark:text-emerald-300 uppercase text-[10px] block mb-0.5">Catatan Persetujuan (Approval):</span>
        <p class="text-slate-700 dark:text-slate-300"><?= nl2br(sanitize($report['catatan_approval'])) ?></p>
      </div>
    <?php endif; ?>

  </div>

  <!-- ========================================================
       CATATAN / DISKUSI TINDAK LANJUT (TANPA UBAH BERITA ACARA)
       ======================================================== -->
  <div id="comments" class="bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200 dark:border-slate-800 shadow-card p-6 sm:p-8 space-y-5">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
      <div>
        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="ph-fill ph-chat-teardrop-text text-[#6558f5] text-lg"></i>
          <span>Catatan Tindak Lanjut &amp; Diskusi (<?= count($comments) ?>)</span>
        </h3>
        <p class="text-[11px] text-slate-400 mt-0.5">Tambahkan update perkembangan tanpa mengubah isi berita acara resmi yang sudah bertanda tangan.</p>
      </div>
    </div>

    <?php if (!empty($comments)): ?>
      <div class="space-y-3">
        <?php foreach ($comments as $cm): ?>
          <div class="p-3.5 rounded-2xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex items-center justify-between gap-2 mb-1">
              <div class="flex items-center gap-2">
                <strong class="text-slate-800 dark:text-white"><?= sanitize($cm['nama_user']) ?></strong>
                <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-[9.5px] font-extrabold uppercase"><?= sanitize($cm['role_user']) ?></span>
              </div>
              <span class="text-[10.5px] font-mono text-slate-400"><?= date('d/m/Y H:i', strtotime($cm['created_at'])) ?> WIB</span>
            </div>
            <p class="text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed"><?= sanitize($cm['komentar']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="#comments" class="flex flex-col sm:flex-row gap-2.5">
      <input type="hidden" name="action" value="add_comment" />
      <input type="text" name="komentar" required placeholder="Tulis catatan perkembangan / tindak lanjut baru..."
             class="flex-1 px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none" />
      <button type="submit"
              class="px-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-extrabold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
        <i class="ph-bold ph-paper-plane-right"></i>
        <span>Kirim Catatan</span>
      </button>
    </form>
  </div>

</div>

<!-- Script for Receiver Signature Modes -->
<?php if ($canValidate): ?>
<script>
  // QR Code for Receiver (if Barcode QR is used)
  const qrRecContainer = document.getElementById('qrReceiverContainer');
  if (qrRecContainer) {
    const docNo = "<?= $report['nomor_laporan'] ?>";
    const verifyUrl = window.location.origin + <?= json_encode(BASE_URL . 'laporan/verify?doc=') ?> + encodeURIComponent(docNo);
    new QRCode(qrRecContainer, {
      text: verifyUrl,
      width: 72,
      height: 72,
      colorDark: "#1e1b4b",
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // Submit Handler: capture QR canvas as base64 if barcode mode
  const formApproval = document.getElementById('formApprovalShift');
  if (formApproval) {
    formApproval.addEventListener('submit', function() {
      const qrCanvas = document.querySelector('#qrReceiverContainer canvas');
      if (qrCanvas) {
        document.getElementById('ttd_base64_menerima').value = qrCanvas.toDataURL('image/png');
      } else {
        const qrImg = document.querySelector('#qrReceiverContainer img');
        if (qrImg) document.getElementById('ttd_base64_menerima').value = qrImg.src;
      }
    });
  }
</script>
<?php endif; ?>

<!-- =====================================================
     WHATSAPP CUSTOM SHARE MODAL (NOTEFLOW DIALOG DESIGN)
     ===================================================== -->
<div id="modalWhatsAppShare" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <!-- Backdrop -->
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeWhatsAppModal()"></div>

  <!-- Modal Box -->
  <div class="relative w-full max-w-xl bg-white dark:bg-[#181b2c] rounded-3xl p-6 sm:p-7 shadow-pop border border-slate-200/80 dark:border-slate-800 z-10 animate-stagger-1 text-slate-800 dark:text-slate-100 max-h-[90vh] flex flex-col">
    
    <!-- Modal Header -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4 shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-[#25D366]/15 text-[#25D366] flex items-center justify-center text-2xl font-black shrink-0">
          <i class="ph-bold ph-whatsapp-logo"></i>
        </div>
        <div>
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white leading-tight">
            Kirim Laporan via WhatsApp
          </h3>
          <p class="text-xs text-slate-400">Rangkuman shift, rincian pekerjaan, dan daftar outstanding siap dikirim.</p>
        </div>
      </div>
      <button type="button" onclick="closeWhatsAppModal()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <!-- Modal Body (Scrollable if needed) -->
    <div class="space-y-4 overflow-y-auto pr-1 flex-1 text-xs">
      
      <!-- Target Phone Input & Fast Chips -->
      <div>
        <label for="wa_target_phone" class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">
          Nomor WhatsApp Tujuan:
        </label>
        <div class="relative">
          <i class="ph-bold ph-phone absolute left-3.5 top-3 text-emerald-500 text-sm"></i>
          <input type="text" id="wa_target_phone" value="<?= sanitize($targetWaPhone) ?>" 
                 placeholder="Kosongkan untuk memilih kontak atau grup bebas di WhatsApp..."
                 class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-semibold focus:outline-none focus:ring-2 focus:ring-[#25D366]/50 transition" />
        </div>
        
        <!-- Helpful tip about empty phone -->
        <p class="text-[10.5px] text-slate-400 mt-1 flex items-center gap-1">
          <i class="ph-fill ph-info text-[#6558f5]"></i>
          <span>Nomor dikosongkan secara default agar Anda bebas memilih tujuan (grup atau kontak langsung) di aplikasi WhatsApp.</span>
        </p>

        <!-- Quick Destination Selector Chips -->
        <div class="flex items-center gap-1.5 flex-wrap mt-2">
          <?php if (!empty($report['wa_menerima']) && $report['wa_menerima'] !== '081234567890'): ?>
            <button type="button" onclick="setModalPhone('<?= sanitize($report['wa_menerima']) ?>')"
                    class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-slate-800 text-[#6558f5] dark:text-indigo-300 hover:bg-indigo-100 text-[10.5px] font-bold transition flex items-center gap-1">
              <i class="ph-bold ph-user"></i>
              <span>Penerima: <?= sanitize($report['nama_menerima'] ?: 'Shift Baru') ?></span>
            </button>
          <?php endif; ?>

          <?php if (!empty($report['wa_menyerahkan']) && $report['wa_menyerahkan'] !== '081234567890'): ?>
            <button type="button" onclick="setModalPhone('<?= sanitize($report['wa_menyerahkan']) ?>')"
                    class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-slate-800 text-[#6558f5] dark:text-indigo-300 hover:bg-indigo-100 text-[10.5px] font-bold transition flex items-center gap-1">
              <i class="ph-bold ph-user-check"></i>
              <span>Penyerah: <?= sanitize($report['nama_menyerahkan'] ?: 'Shift Lama') ?></span>
            </button>
          <?php endif; ?>

          <button type="button" onclick="setModalPhone('')"
                  class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 text-[10.5px] font-bold transition flex items-center gap-1">
            <i class="ph-bold ph-users-three"></i>
            <span>👥 Pilih Bebas di WhatsApp (Kosongkan)</span>
          </button>
        </div>
      </div>

      <!-- Message Textarea (Full breakdown of shift, tasks & outstanding) -->
      <div>
        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
          <label for="wa_message_content" class="font-bold text-slate-700 dark:text-slate-300">
            Pratinjau Isi Pesan (Rincian &amp; Outstanding):
          </label>
          <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-1.5 cursor-pointer text-[11px] font-bold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
              <input type="checkbox" id="toggle_include_link" onchange="toggleWaPublicLink(this.checked)" 
                     class="w-3.5 h-3.5 rounded accent-[#25D366] text-[#25D366] focus:ring-0 cursor-pointer" />
              <span>Sertakan Link Dokumen</span>
            </label>
            <span id="copy-status-badge" class="hidden text-[10px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-full">
              Tersalin!
            </span>
          </div>
        </div>
        <textarea id="wa_message_content" rows="10" 
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 font-mono text-[11px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#25D366]/50 transition"><?= sanitize($waFullMsg) ?></textarea>
        <span class="text-[10px] text-slate-400 mt-1 block">Anda dapat mengedit atau menambahkan catatan tambahan sebelum membuka WhatsApp.</span>
      </div>

    </div>

    <!-- Modal Footer Actions -->
    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-2.5 mt-3 shrink-0">
      <button type="button" onclick="copyWhatsAppText()"
              class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs transition flex items-center justify-center gap-1.5 active:scale-95">
        <i class="ph-bold ph-copy"></i>
        <span>Salin Teks Pesan</span>
      </button>

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <button type="button" onclick="closeWhatsAppModal()"
                class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-200 transition">
          Batal
        </button>
        <button type="button" onclick="executeSendWhatsApp()"
                class="flex-1 sm:flex-initial px-5 py-2.5 rounded-xl bg-[#25D366] hover:bg-[#20bd5a] text-white font-extrabold text-xs shadow-md transition flex items-center justify-center gap-2 active:scale-95">
          <i class="ph-bold ph-paper-plane-tilt"></i>
          <span>Buka WhatsApp &amp; Kirim</span>
        </button>
      </div>
    </div>

  </div>
</div>

<script>
const waTextWithoutLink = <?= json_encode($waMsgWithoutLink) ?>;
const waTextWithLink    = <?= json_encode($waMsgWithLink) ?>;

function toggleWaPublicLink(checked) {
  const textarea = document.getElementById('wa_message_content');
  if (textarea) {
    textarea.value = checked ? waTextWithLink : waTextWithoutLink;
  }
}

function openWhatsAppModal() {
  document.getElementById('modalWhatsAppShare').classList.remove('hidden');
}

function closeWhatsAppModal() {
  document.getElementById('modalWhatsAppShare').classList.add('hidden');
}

function setModalPhone(phone) {
  document.getElementById('wa_target_phone').value = phone;
}

function copyWhatsAppText() {
  const textarea = document.getElementById('wa_message_content');
  textarea.select();
  textarea.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(textarea.value).then(() => {
    const badge = document.getElementById('copy-status-badge');
    badge.classList.remove('hidden');
    setTimeout(() => { badge.classList.add('hidden'); }, 2000);
  });
}

function executeSendWhatsApp() {
  const rawPhone = document.getElementById('wa_target_phone').value.trim();
  const message = document.getElementById('wa_message_content').value;

  let clean = rawPhone.replace(/[^0-9]/g, '');
  let url = '';

  // Jika nomor kosong, dummy, atau kurang dari 8 digit: buka pemilih kontak / grup universal WhatsApp
  if (!clean || clean.length < 8 || clean === '081234567890' || clean === '6281234567890') {
    url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(message);
  } else {
    if (clean.startsWith('0')) {
      clean = '62' + clean.substring(1);
    } else if (clean.startsWith('8')) {
      clean = '62' + clean;
    }
    url = 'https://wa.me/' + clean + '?text=' + encodeURIComponent(message);
  }

  window.open(url, '_blank');
  closeWhatsAppModal();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
