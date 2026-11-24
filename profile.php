<?php
$current_page = 'profile';
$page_title = 'Pengaturan Profil';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama    = trim($_POST['nama_lengkap'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $noWa    = trim($_POST['no_wa'] ?? '');

        if (empty($nama)) {
            flash_set('error', 'Nama lengkap wajib diisi.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = :nama, jabatan = :jabatan, email = :email, no_wa = :no_wa WHERE id = :id");
            $stmt->execute([
                'nama'    => $nama,
                'jabatan' => $jabatan,
                'email'   => $email,
                'no_wa'   => $noWa,
                'id'      => $userId,
            ]);

            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['jabatan']      = $jabatan;
            $_SESSION['email']        = $email;
            $_SESSION['no_wa']        = $noWa;

            flash_set('success', 'Informasi profil berhasil diperbarui.');
        }
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }

    if ($action === 'upload_photo') {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'Pilih file gambar yang valid untuk foto profil.');
        } else {
            $newPhoto = upload_avatar($_FILES['foto']);
            if ($newPhoto) {
                if (!empty($user['foto']) && file_exists(UPLOAD_DIR . $user['foto'])) {
                    @unlink(UPLOAD_DIR . $user['foto']);
                }

                $stmt = $pdo->prepare("UPDATE users SET foto = :foto WHERE id = :id");
                $stmt->execute(['foto' => $newPhoto, 'id' => $userId]);
                $_SESSION['foto'] = $newPhoto;

                flash_set('success', 'Foto profil berhasil diperbarui.');
            } else {
                flash_set('error', 'Gagal memproses foto. Pastikan format JPG, PNG, atau WebP.');
            }
        }
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }

    if ($action === 'delete_photo') {
        if (!empty($user['foto']) && file_exists(UPLOAD_DIR . $user['foto'])) {
            @unlink(UPLOAD_DIR . $user['foto']);
        }
        $stmt = $pdo->prepare("UPDATE users SET foto = NULL WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $_SESSION['foto'] = null;

        flash_set('success', 'Foto profil berhasil dihapus.');
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }

    if ($action === 'update_signature') {
        $sigType   = $_POST['ttd_type'] ?? 'barcode';
        $sigFile   = $_FILES['ttd_upload'] ?? null;
        $sigCanvas = $_POST['ttd_canvas_data'] ?? null;

        if ($sigType === 'barcode') {
            $stmt = $pdo->prepare("UPDATE users SET ttd_type = 'barcode' WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $_SESSION['ttd_type'] = 'barcode';
            flash_set('success', 'Tanda tangan default berhasil diatur ke Barcode QR Digital.');
        } elseif ($sigType === 'upload') {
            if ($sigFile && isset($sigFile['error']) && $sigFile['error'] === UPLOAD_ERR_OK) {
                $saved = process_signature('upload', $sigFile, null, 'user_' . $userId . '_');
                if ($saved) {
                    if (!empty($user['ttd_file']) && file_exists(UPLOAD_DIR . $user['ttd_file'])) {
                        @unlink(UPLOAD_DIR . $user['ttd_file']);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET ttd_type = 'upload', ttd_file = :file WHERE id = :id");
                    $stmt->execute(['file' => $saved, 'id' => $userId]);
                    $_SESSION['ttd_type'] = 'upload';
                    $_SESSION['ttd_file'] = $saved;
                    flash_set('success', 'Gambar TTD basah berhasil diunggah dan disimpan sebagai default.');
                } else {
                    flash_set('error', 'Gagal memproses gambar TTD. Pastikan format PNG, JPG, atau WebP.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET ttd_type = 'upload' WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $_SESSION['ttd_type'] = 'upload';
                flash_set('success', 'Metode tanda tangan diatur ke Upload TTD Basah.');
            }
        } elseif ($sigType === 'canvas') {
            if (!empty($sigCanvas) && str_starts_with($sigCanvas, 'data:image')) {
                $saved = process_signature('canvas', null, $sigCanvas, 'user_' . $userId . '_');
                if ($saved) {
                    if (!empty($user['ttd_file']) && file_exists(UPLOAD_DIR . $user['ttd_file'])) {
                        @unlink(UPLOAD_DIR . $user['ttd_file']);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET ttd_type = 'canvas', ttd_file = :file WHERE id = :id");
                    $stmt->execute(['file' => $saved, 'id' => $userId]);
                    $_SESSION['ttd_type'] = 'canvas';
                    $_SESSION['ttd_file'] = $saved;
                    flash_set('success', 'Goresan tanda tangan digital berhasil disimpan sebagai default.');
                } else {
                    flash_set('error', 'Gagal menyimpan goresan tanda tangan.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET ttd_type = 'canvas' WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $_SESSION['ttd_type'] = 'canvas';
                flash_set('success', 'Metode tanda tangan diatur ke Gores Canvas.');
            }
        }
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }

    if ($action === 'delete_signature') {
        if (!empty($user['ttd_file']) && file_exists(UPLOAD_DIR . $user['ttd_file'])) {
            @unlink(UPLOAD_DIR . $user['ttd_file']);
        }
        $stmt = $pdo->prepare("UPDATE users SET ttd_file = NULL, ttd_type = 'barcode' WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $_SESSION['ttd_file'] = null;
        $_SESSION['ttd_type'] = 'barcode';
        flash_set('success', 'Tanda tangan berkas berhasil dihapus dan dikembalikan ke Barcode QR.');
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }

    if ($action === 'change_password') {
        $oldPass     = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $currentHash = $stmt->fetchColumn();

        if (!password_verify($oldPass, $currentHash)) {
            flash_set('error', 'Kata sandi saat ini tidak cocok.');
        } elseif (strlen($newPass) < 6) {
            flash_set('error', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($newPass !== $confirmPass) {
            flash_set('error', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmt->execute(['p' => $newHash, 'id' => $userId]);

            flash_set('success', 'Kata sandi Anda berhasil diperbarui.');
        }
        header('Location: ' . BASE_URL . 'profile.php');
        exit;
    }
}

// Data pengguna terkini
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmtUser->execute(['id' => $userId]);
$profile = $stmtUser->fetch(PDO::FETCH_ASSOC);

$avatarUrl = get_user_avatar_url($profile['foto'] ?? null);
$sigUrl    = get_signature_url($profile['ttd_file'] ?? null);
$userSigType = $profile['ttd_type'] ?? 'barcode';

require_once __DIR__ . '/includes/header.php';
?>

<!-- QRCode JS CDN for Live Digital Signature Preview -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="space-y-6 w-full animate-stagger-1 max-w-6xl mx-auto">

  <!-- Header Page Navigation -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
      <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-1.5">
        <i class="ph-bold ph-user-gear"></i>
        <span>Pengaturan Akun</span>
      </div>
      <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
        Profil &amp; Tanda Tangan
      </h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
        Kelola informasi identitas, foto profil, tanda tangan digital (Barcode QR / Basah / Canvas), dan kata sandi.
      </p>
    </div>

    <a href="<?= BASE_URL ?>dashboard.php" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-[#181b2c] border border-slate-200 dark:border-slate-800 hover:border-[#6558f5] hover:text-[#6558f5] transition self-start sm:self-auto shadow-sm">
      <i class="ph-bold ph-arrow-left"></i>
      <span>Kembali ke Dashboard</span>
    </a>
  </div>

  <!-- Main Grid: Left (Avatar & Quick Info), Right (Personal Details, TTD, Password) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Left Column: Avatar & Summary (4 Cols) -->
    <div class="lg:col-span-4 space-y-6">

      <!-- Avatar & Identity Card -->
      <div class="bg-white dark:bg-[#181b2c] rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-card flex flex-col items-center text-center relative overflow-hidden">
        
        <!-- Top decorative banner accent -->
        <div class="absolute top-0 inset-x-0 h-20 bg-gradient-to-r from-[#6558f5]/20 via-indigo-500/10 to-[#68cfa3]/20"></div>

        <!-- Avatar Wrapper with ring and interactive file upload -->
        <div class="relative mt-4 mb-4 group">
          <div class="w-28 h-28 rounded-full overflow-hidden ring-4 ring-white dark:ring-[#181b2c] shadow-pop bg-[#efeffb] dark:bg-slate-800 flex items-center justify-center">
            <?php if ($avatarUrl): ?>
              <img id="avatar-preview-img" src="<?= $avatarUrl ?>" alt="Foto Profil" class="w-full h-full object-cover">
            <?php else: ?>
              <div id="avatar-placeholder" class="w-full h-full bg-gradient-to-tr from-[#6558f5] to-indigo-400 text-white flex items-center justify-center text-3xl font-black">
                <?= strtoupper(substr($profile['nama_lengkap'] ?? 'U', 0, 2)) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Camera button to trigger file picker -->
          <button type="button" 
                  onclick="document.getElementById('avatar-file-input').click()"
                  class="absolute bottom-0 right-0 w-9 h-9 rounded-full bg-[#6558f5] text-white flex items-center justify-center shadow-md hover:bg-[#5446e8] hover:scale-105 active:scale-95 transition"
                  title="Ganti Foto">
            <i class="ph-bold ph-camera text-base"></i>
          </button>
        </div>

        <!-- Hidden form auto-submitted on image select -->
        <form id="avatar-form" action="<?= BASE_URL ?>profile.php" method="POST" enctype="multipart/form-data" class="hidden">
          <input type="hidden" name="action" value="upload_photo">
          <input type="file" id="avatar-file-input" name="foto" accept="image/jpeg,image/png,image/webp" onchange="handleAvatarSelected(this)">
        </form>

        <h2 class="text-base font-extrabold text-slate-900 dark:text-white leading-tight">
          <?= sanitize($profile['nama_lengkap']) ?>
        </h2>
        <p class="text-xs font-mono text-slate-400 mt-0.5">@<?= sanitize($profile['username']) ?></p>

        <div class="mt-2.5 flex items-center justify-center gap-1.5 flex-wrap">
          <?= badge_role($profile['role']) ?>
          <?php if (!empty($profile['jabatan'])): ?>
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 rounded-full">
              <?= sanitize($profile['jabatan']) ?>
            </span>
          <?php endif; ?>
        </div>

        <!-- Action buttons for Photo -->
        <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800/80 w-full flex flex-col gap-2">
          <button type="button" 
                  onclick="document.getElementById('avatar-file-input').click()"
                  class="w-full py-2 px-3 rounded-xl bg-[#efeffb] hover:bg-[#e4e3f8] dark:bg-slate-800 dark:hover:bg-slate-700 text-[#6558f5] dark:text-indigo-300 font-extrabold text-xs transition flex items-center justify-center gap-2">
            <i class="ph-bold ph-upload-simple"></i>
            <span>Unggah Foto Profil Baru</span>
          </button>

          <?php if ($avatarUrl): ?>
            <form action="<?= BASE_URL ?>profile.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus foto profil?')">
              <input type="hidden" name="action" value="delete_photo">
              <button type="submit" 
                      class="w-full py-1.5 px-3 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-[11px] font-bold transition flex items-center justify-center gap-1.5">
                <i class="ph-bold ph-trash"></i>
                <span>Hapus Foto</span>
              </button>
            </form>
          <?php endif; ?>

          <span class="text-[10px] text-slate-400">
            Mendukung file JPG, PNG, atau WebP. Gambar dikompresi otomatis.
          </span>
        </div>

      </div>

      <!-- Account Info Metadata Card -->
      <div class="bg-white dark:bg-[#181b2c] rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-card text-xs space-y-3">
        <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
          Informasi Akun &amp; TTD Aktif
        </div>

        <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
          <span class="text-slate-500 dark:text-slate-400">Status Akun</span>
          <span class="font-extrabold text-emerald-600 dark:text-emerald-400 uppercase text-[11px]">
            <i class="ph-fill ph-check-circle mr-0.5"></i> <?= sanitize($profile['status'] ?? 'aktif') ?>
          </span>
        </div>

        <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
          <span class="text-slate-500 dark:text-slate-400">Metode TTD Default</span>
          <span class="font-bold text-[#6558f5] uppercase text-[10.5px]">
            <?php
            if ($userSigType === 'barcode') echo 'Barcode Digital QR';
            elseif ($userSigType === 'upload') echo 'Upload TTD Basah';
            else echo 'Gores Canvas';
            ?>
          </span>
        </div>

        <?php if ($sigUrl): ?>
          <div class="py-2 border-b border-slate-100 dark:border-slate-800">
            <span class="text-slate-500 dark:text-slate-400 block mb-1">Preview Berkas TTD:</span>
            <div class="p-2 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-center h-16">
              <img src="<?= $sigUrl ?>" alt="TTD Default" class="max-h-12 object-contain">
            </div>
          </div>
        <?php endif; ?>

        <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800">
          <span class="text-slate-500 dark:text-slate-400">ID Pengguna</span>
          <span class="font-mono font-bold text-slate-700 dark:text-slate-200">#<?= str_pad($profile['id'], 4, '0', STR_PAD_LEFT) ?></span>
        </div>

        <div class="flex items-center justify-between py-1">
          <span class="text-slate-500 dark:text-slate-400">Terdaftar Sejak</span>
          <span class="font-bold text-slate-700 dark:text-slate-200"><?= format_tanggal($profile['created_at']) ?></span>
        </div>
      </div>

    </div>

    <!-- Right Column: Forms (8 Cols) -->
    <div class="lg:col-span-8 space-y-6">

      <!-- Form 1: Profil & Data Kontak -->
      <div class="bg-white dark:bg-[#181b2c] rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center text-xl shrink-0">
            <i class="ph-bold ph-identification-card"></i>
          </div>
          <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Informasi Pribadi &amp; Kontak Operasional</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Identitas yang tercetak pada laporan serah terima dan alamat notifikasi shift.</p>
          </div>
        </div>

        <form action="<?= BASE_URL ?>profile.php" method="POST" class="space-y-4">
          <input type="hidden" name="action" value="update_profile">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="nama_lengkap" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Nama Lengkap <span class="text-rose-500">*</span>
              </label>
              <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?= sanitize($profile['nama_lengkap']) ?>"
                     class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
            </div>

            <div>
              <label for="username" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Username (ID Masuk)
              </label>
              <input type="text" id="username" readonly value="<?= sanitize($profile['username']) ?>"
                     class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-xs font-mono font-bold text-slate-500 dark:text-slate-400 cursor-not-allowed">
              <span class="text-[10px] text-slate-400 mt-1 block">Username akun bersifat permanen untuk pencatatan log.</span>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="jabatan" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Jabatan / Bagian Unit
              </label>
              <input type="text" id="jabatan" name="jabatan" value="<?= sanitize($profile['jabatan'] ?? '') ?>" placeholder="Misal: Control Room Senior Operator"
                     class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
            </div>

            <div>
              <label for="role_badge" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Peran Hak Akses
              </label>
              <div class="h-[42px] flex items-center px-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <?= badge_role($profile['role']) ?>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
            <div>
              <label for="email" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Alamat Email (Notifikasi SMTP)
              </label>
              <div class="relative">
                <i class="ph-bold ph-envelope-simple absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                <input type="email" id="email" name="email" value="<?= sanitize($profile['email'] ?? '') ?>" placeholder="user@perusahaan.com"
                       class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
              </div>
              <span class="text-[10px] text-slate-400 mt-1 block">Menerima email saat laporan dibuat atau butuh persetujuan.</span>
            </div>

            <div>
              <label for="no_wa" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Nomor WhatsApp (Notifikasi WA)
              </label>
              <div class="relative">
                <i class="ph-bold ph-whatsapp-logo absolute left-3.5 top-3 text-emerald-500 text-sm"></i>
                <input type="text" id="no_wa" name="no_wa" value="<?= sanitize($profile['no_wa'] ?? '') ?>" placeholder="Opsional (misal: 08123456789 atau 628123456789)"
                       class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
              </div>
              <span class="text-[10px] text-slate-400 mt-1 block">Bisa dikosongkan jika ingin memilih nomor, kontak, atau grup WhatsApp secara bebas saat mengirim laporan.</span>
            </div>
          </div>

          <div class="pt-4 flex justify-end">
            <button type="submit" 
                    class="px-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 transition flex items-center gap-2">
              <i class="ph-bold ph-floppy-disk"></i>
              <span>Simpan Informasi Profil</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Form 2: Pengaturan Tanda Tangan (Barcode QR, Upload TTD Basah, Gores Canvas) -->
      <div class="bg-white dark:bg-[#181b2c] rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-card">
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 flex items-center justify-center text-xl shrink-0">
              <i class="ph-bold ph-signature"></i>
            </div>
            <div>
              <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Pengaturan Tanda Tangan (Digital &amp; Basah)</h2>
              <p class="text-xs text-slate-500 dark:text-slate-400">Atur tanda tangan default akun Anda: Barcode QR Digital, Upload TTD Basah, atau Gores Canvas.</p>
            </div>
          </div>
          <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-[#e6f7f0] text-[#1c7a52] dark:bg-emerald-950/50 dark:text-emerald-300">
            <i class="ph-bold ph-check-circle"></i>
            <span>TTD Default Tersedia</span>
          </span>
        </div>

        <form id="form-signature" action="<?= BASE_URL ?>profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="action" value="update_signature">
          <input type="hidden" name="ttd_type" id="profile_ttd_type" value="<?= sanitize($userSigType) ?>">
          <input type="hidden" name="ttd_canvas_data" id="profile_ttd_canvas_data">

          <!-- Identical Card Box as in Serah Terima -->
          <div class="p-5 rounded-2xl bg-indigo-50/40 dark:bg-slate-900/50 border border-indigo-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                  Tanda Tangan Akun:
                </span>
                <span class="text-xs font-black text-[#6558f5]"><?= sanitize($profile['nama_lengkap']) ?></span>
              </div>

              <!-- Tab Pilihan Mode Tanda Tangan -->
              <div class="flex items-center gap-1.5 p-1 bg-slate-200/80 dark:bg-slate-800 rounded-xl mb-3">
                <button type="button" onclick="setProfileSignMode('barcode')" id="tabProfBarcode"
                        class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold transition flex items-center justify-center gap-1">
                  <i class="ph-bold ph-qr-code text-sm"></i>
                  <span>Barcode QR</span>
                </button>
                <button type="button" onclick="setProfileSignMode('upload')" id="tabProfUpload"
                        class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold transition flex items-center justify-center gap-1">
                  <i class="ph-bold ph-upload-simple text-sm"></i>
                  <span>Upload TTD Basah</span>
                </button>
                <button type="button" onclick="setProfileSignMode('canvas')" id="tabProfCanvas"
                        class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold transition flex items-center justify-center gap-1">
                  <i class="ph-bold ph-pencil-simple text-sm"></i>
                  <span>Gores Canvas</span>
                </button>
              </div>

              <!-- MODE 1: BARCODE QR DIGITAL TTD BADGE -->
              <div id="profBoxBarcode" class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-indigo-200 dark:border-slate-700 flex items-center gap-3">
                <div id="profQrContainer" class="p-1 bg-white rounded-lg border border-slate-200 shrink-0"></div>
                <div class="text-[11px] text-slate-600 dark:text-slate-300 space-y-0.5">
                  <div class="font-black text-[#6558f5] text-[10px] uppercase tracking-wide flex items-center gap-1">
                    <i class="ph-fill ph-seal-check text-emerald-500"></i>
                    <span>DIGITAL SIGNATURE OMIT</span>
                  </div>
                  <div class="font-bold text-slate-800 dark:text-white"><?= sanitize($profile['nama_lengkap']) ?></div>
                  <div class="text-[10px] text-slate-400"><?= date('d/m/Y H:i') ?> WIB</div>
                  <div class="text-[9px] text-emerald-600 font-semibold">Tersertifikasi Sah Sistem</div>
                </div>
              </div>

              <!-- MODE 2: UPLOAD GAMBAR TTD BASAH -->
              <div id="profBoxUpload" class="hidden p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 text-center">
                <?php if ($sigUrl && $userSigType === 'upload'): ?>
                  <div class="mb-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <div class="w-16 h-12 bg-white rounded-lg p-1 border border-slate-200 flex items-center justify-center overflow-hidden">
                        <img src="<?= $sigUrl ?>" alt="TTD Basah Tersimpan" class="max-h-full max-w-full object-contain">
                      </div>
                      <div class="text-left">
                        <span class="text-xs font-bold text-slate-800 dark:text-white block">Berkas TTD Basah Tersimpan</span>
                        <span class="text-[10px] text-emerald-600 font-semibold">✓ Siap digunakan otomatis saat serah terima</span>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="w-full h-32 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 flex flex-col items-center justify-center overflow-hidden cursor-pointer bg-slate-50/50 dark:bg-slate-800/50 hover:bg-slate-100/50 transition"
                     onclick="document.getElementById('ttd_upload_profile').click()">
                  <img id="preview_prof_ttd" class="hidden max-h-28 object-contain" />
                  <div id="placeholder_prof_ttd">
                    <i class="ph ph-file-arrow-up text-3xl text-indigo-500 mb-1"></i>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block">
                      <?= ($sigUrl && $userSigType === 'upload') ? 'Ganti Berkas TTD Basah' : 'Pilih Gambar TTD Basah' ?>
                    </span>
                    <span class="text-[10px] text-slate-400 block">Foto tanda tangan di atas kertas (PNG / JPG / WebP maks. 3MB)</span>
                  </div>
                </div>
                <input type="file" name="ttd_upload" id="ttd_upload_profile" accept="image/*" class="hidden" 
                       onchange="previewProfSignature(this)" />
              </div>

              <!-- MODE 3: GORES DIGITAL CANVAS -->
              <div id="profBoxCanvas" class="hidden space-y-2">
                <?php if ($sigUrl && $userSigType === 'canvas'): ?>
                  <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <div class="w-20 h-10 bg-slate-50 dark:bg-slate-800 rounded-lg p-1 border border-slate-200 dark:border-slate-700 flex items-center justify-center overflow-hidden">
                        <img src="<?= $sigUrl ?>" alt="Goresan Tersimpan" class="max-h-full max-w-full object-contain">
                      </div>
                      <div class="text-left">
                        <span class="text-xs font-bold text-slate-800 dark:text-white block">Goresan Canvas Tersimpan</span>
                        <span class="text-[10px] text-emerald-600 font-semibold">✓ Siap digunakan otomatis saat serah terima</span>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="relative bg-white rounded-xl border border-slate-300 overflow-hidden h-36">
                  <canvas id="profileCanvasPad" class="w-full h-full cursor-crosshair"></canvas>
                  <button type="button" onclick="clearProfileCanvas()"
                          class="absolute top-2 right-2 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold rounded-lg transition shadow-sm">
                    <i class="ph-bold ph-eraser mr-0.5"></i> Hapus Goresan
                  </button>
                  <div class="absolute bottom-1.5 left-3 pointer-events-none text-[9.5px] text-slate-400">
                    Gores tanda tangan Anda di area putih ini menggunakan mouse atau jari/stylus.
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-indigo-100 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3">
              <span class="text-[10px] text-slate-400 text-center sm:text-left">
                Tanda tangan yang Anda simpan di sini akan menjadi pilihan default saat membuat formulir serah terima atau approval.
              </span>

              <div class="flex items-center gap-2 w-full sm:w-auto">
                <?php if ($sigUrl): ?>
                  <button type="button" onclick="document.getElementById('form-del-signature').submit()"
                          class="px-3 py-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition flex items-center gap-1.5">
                    <i class="ph-bold ph-trash"></i>
                    <span>Hapus Berkas</span>
                  </button>
                <?php endif; ?>

                <button type="submit" 
                        class="w-full sm:w-auto px-5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 transition flex items-center justify-center gap-2">
                  <i class="ph-bold ph-check"></i>
                  <span>Simpan Sebagai TTD Default</span>
                </button>
              </div>
            </div>
          </div>
        </form>

        <!-- Hidden delete signature form -->
        <form id="form-del-signature" action="<?= BASE_URL ?>profile.php" method="POST" class="hidden" onsubmit="return confirm('Hapus berkas tanda tangan tersimpan dan kembalikan ke Barcode QR?')">
          <input type="hidden" name="action" value="delete_signature">
        </form>
      </div>

      <!-- Form 3: Keamanan & Kata Sandi -->
      <div class="bg-white dark:bg-[#181b2c] rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-500 flex items-center justify-center text-xl shrink-0">
            <i class="ph-bold ph-key"></i>
          </div>
          <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Keamanan &amp; Kata Sandi</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Gunakan kombinasi kata sandi yang aman untuk melindungi akun operasional Anda.</p>
          </div>
        </div>

        <form action="<?= BASE_URL ?>profile.php" method="POST" class="space-y-4">
          <input type="hidden" name="action" value="change_password">

          <div>
            <label for="current_password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Kata Sandi Saat Ini <span class="text-rose-500">*</span>
            </label>
            <div class="relative max-w-md">
              <input type="password" id="current_password" name="current_password" required placeholder="Masukkan kata sandi lama Anda"
                     class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-medium text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
              <button type="button" onclick="togglePasswordVisibility('current_password', this)" class="absolute right-3.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="ph-bold ph-eye text-base"></i>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="new_password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Kata Sandi Baru <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-medium text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
                <button type="button" onclick="togglePasswordVisibility('new_password', this)" class="absolute right-3.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                  <i class="ph-bold ph-eye text-base"></i>
                </button>
              </div>
            </div>

            <div>
              <label for="confirm_password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Konfirmasi Kata Sandi Baru <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Ulangi kata sandi baru"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-xs font-medium text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#6558f5]/40 transition">
                <button type="button" onclick="togglePasswordVisibility('confirm_password', this)" class="absolute right-3.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                  <i class="ph-bold ph-eye text-base"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="pt-4 flex justify-end">
            <button type="submit" 
                    class="px-5 py-2.5 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-[#6558f5] dark:hover:bg-[#6558f5] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2">
              <i class="ph-bold ph-lock-key"></i>
              <span>Perbarui Kata Sandi</span>
            </button>
          </div>
        </form>
      </div>

    </div>

  </div>

</div>

<!-- Profile Scripts -->
<script>
function handleAvatarSelected(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran file maksimal 5 MB.');
      input.value = '';
      return;
    }
    document.getElementById('avatar-form').submit();
  }
}

function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('ph-eye');
    icon.classList.add('ph-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('ph-eye-slash');
    icon.classList.add('ph-eye');
  }
}

// -------------------------------------------------------------
// Interactive Signature Tabs & Canvas Logic
// -------------------------------------------------------------
let profileSigMode = '<?= $userSigType ?>';
let profCanvas, profCtx;
let profDrawing = false;
let profCanvasHasDrawn = false;

function setProfileSignMode(mode) {
  profileSigMode = mode;
  document.getElementById('profile_ttd_type').value = mode;

  const btnB = document.getElementById('tabProfBarcode');
  const btnU = document.getElementById('tabProfUpload');
  const btnC = document.getElementById('tabProfCanvas');

  const boxB = document.getElementById('profBoxBarcode');
  const boxU = document.getElementById('profBoxUpload');
  const boxC = document.getElementById('profBoxCanvas');

  const activeClass = 'bg-white dark:bg-[#131b2e] text-[#6558f5] shadow-sm';
  const inactiveClass = 'text-slate-600 dark:text-slate-400 hover:text-slate-900';

  [btnB, btnU, btnC].forEach(b => {
    b.className = b.className.replace(activeClass, '').trim();
    if (!b.className.includes(inactiveClass)) {
      b.className += ' ' + inactiveClass;
    }
  });

  boxB.classList.add('hidden');
  boxU.classList.add('hidden');
  boxC.classList.add('hidden');

  if (mode === 'barcode') {
    btnB.className = btnB.className.replace(inactiveClass, '').trim() + ' ' + activeClass;
    boxB.classList.remove('hidden');
  } else if (mode === 'upload') {
    btnU.className = btnU.className.replace(inactiveClass, '').trim() + ' ' + activeClass;
    boxU.classList.remove('hidden');
  } else if (mode === 'canvas') {
    btnC.className = btnC.className.replace(inactiveClass, '').trim() + ' ' + activeClass;
    boxC.classList.remove('hidden');
    setTimeout(initProfileCanvas, 50);
  }
}

function previewProfSignature(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('preview_prof_ttd');
      const placeholder = document.getElementById('placeholder_prof_ttd');
      img.src = e.target.result;
      img.classList.remove('hidden');
      if (placeholder) placeholder.classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function initProfileCanvas() {
  profCanvas = document.getElementById('profileCanvasPad');
  if (!profCanvas) return;

  const rect = profCanvas.parentElement.getBoundingClientRect();
  profCanvas.width = rect.width;
  profCanvas.height = rect.height;

  profCtx = profCanvas.getContext('2d');
  profCtx.strokeStyle = '#181b2c';
  profCtx.lineWidth = 2.5;
  profCtx.lineCap = 'round';
  profCtx.lineJoin = 'round';

  function getPos(e) {
    const r = profCanvas.getBoundingClientRect();
    if (e.touches && e.touches.length > 0) {
      return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
    }
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  }

  function startDraw(e) {
    profDrawing = true;
    profCanvasHasDrawn = true;
    const p = getPos(e);
    profCtx.beginPath();
    profCtx.moveTo(p.x, p.y);
    e.preventDefault();
  }

  function drawMove(e) {
    if (!profDrawing) return;
    const p = getPos(e);
    profCtx.lineTo(p.x, p.y);
    profCtx.stroke();
    e.preventDefault();
  }

  function stopDraw() {
    if (!profDrawing) return;
    profDrawing = false;
    document.getElementById('profile_ttd_canvas_data').value = profCanvas.toDataURL('image/png');
  }

  profCanvas.onmousedown = startDraw;
  profCanvas.onmousemove = drawMove;
  window.addEventListener('mouseup', stopDraw);

  profCanvas.ontouchstart = startDraw;
  profCanvas.ontouchmove = drawMove;
  window.addEventListener('touchend', stopDraw);
}

function clearProfileCanvas() {
  if (profCtx && profCanvas) {
    profCtx.clearRect(0, 0, profCanvas.width, profCanvas.height);
    document.getElementById('profile_ttd_canvas_data').value = '';
    profCanvasHasDrawn = false;
  }
}

// Generate Barcode QR Digital on Page Load
document.addEventListener('DOMContentLoaded', function() {
  const qrTarget = document.getElementById('profQrContainer');
  if (qrTarget && typeof QRCode !== 'undefined') {
    qrTarget.innerHTML = '';
    const certString = "OMIT-SIG-DEFAULT|USER:<?= $userId ?>|<?= sanitize($profile['nama_lengkap']) ?>|VERIFIED";
    new QRCode(qrTarget, {
      text: certString,
      width: 72,
      height: 72,
      colorDark: "#181b2c",
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  setProfileSignMode(profileSigMode);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
