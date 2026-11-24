<?php
// master/users.php - Manajemen Pengguna (Superadmin, Admin, User)
$current_page = 'master_users';
$page_title = 'Kelola Pengguna Sistem';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$user = current_user();

// Handle CSV Template Download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_pengguna.csv');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['nama_lengkap', 'username', 'password', 'jabatan', 'role', 'email', 'no_wa']);
    fputcsv($out, ['Ahmad Fauzi', 'ahmad', 'password123', 'Field Operator Shift', 'user', 'ahmad@operasional.id', '081298765432']);
    fputcsv($out, ['Siti Nurhaliza', 'siti', 'password123', 'Supervisor Operasional', 'admin', 'siti@operasional.id', '081234567891']);
    fclose($out);
    exit;
}

// Handle User Actions (Add, Edit, Edit Role, Toggle Status, Reset Password, Delete, Import CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $no_wa    = trim($_POST['no_wa'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';
        $jabatan  = trim($_POST['jabatan'] ?? '');

        if (!empty($nama) && !empty($username) && !empty($password)) {
            if ($role === 'superadmin' && !is_superadmin()) {
                flash_set('error', 'Akses ditolak: Hanya Superadmin yang dapat membuat akun Superadmin.');
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
                $check->execute(['u' => $username]);
                if ($check->fetchColumn() > 0) {
                    flash_set('error', "Username '{$username}' sudah digunakan!");
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, username, email, no_wa, password, role, jabatan) VALUES (:nama, :u, :email, :no_wa, :p, :r, :j)");
                    $stmt->execute([
                        'nama'   => $nama,
                        'u'      => $username,
                        'email'  => $email,
                        'no_wa'  => $no_wa,
                        'p'      => $hash,
                        'r'      => $role,
                        'j'      => $jabatan
                    ]);
                    flash_set('success', "Pengguna '{$nama}' dengan peran " . strtoupper($role) . " berhasil didaftarkan.");
                }
            }
        }
    } elseif ($action === 'edit_user') {
        $id       = (int)($_POST['id'] ?? 0);
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $no_wa    = trim($_POST['no_wa'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $role     = $_POST['role'] ?? 'user';
        $status   = $_POST['status'] ?? 'aktif';

        if ($id <= 0 || empty($nama) || empty($username)) {
            flash_set('error', 'Nama lengkap dan username tidak boleh kosong.');
        } elseif (!in_array($role, ['user', 'admin', 'superadmin'])) {
            flash_set('error', 'Peran (role) yang dipilih tidak valid.');
        } elseif (!in_array($status, ['aktif', 'nonaktif'])) {
            flash_set('error', 'Status akun tidak valid.');
        } else {
            // Check username uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
            $check->execute(['u' => $username, 'id' => $id]);
            if ($check->fetchColumn()) {
                flash_set('error', "Username '{$username}' sudah digunakan pengguna lain.");
            } else {
                // Prevent disabling self
                if ($id == $user['id'] && $status === 'nonaktif') {
                    flash_set('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
                } else {
                    // Check superadmin privilege & last active superadmin protection
                    $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = :id");
                    $stmtTarget->execute(['id' => $id]);
                    $targetUser = $stmtTarget->fetch();
                    $superadminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND status = 'aktif'")->fetchColumn();
                    if (!is_superadmin() && (($targetUser && $targetUser['role'] === 'superadmin') || $role === 'superadmin')) {
                        flash_set('error', 'Akses ditolak: Role Admin tidak diizinkan mengubah akun atau peran Superadmin.');
                    } elseif ($targetUser && $targetUser['role'] === 'superadmin' && ($role !== 'superadmin' || $status === 'nonaktif') && $superadminCount <= 1) {
                        flash_set('error', 'Tidak dapat mengubah peran/status superadmin terakhir pada sistem.');
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = :nama, username = :u, email = :email, no_wa = :no_wa, jabatan = :j, role = :r, status = :s WHERE id = :id");
                        $stmt->execute([
                            'nama'   => $nama,
                            'u'      => $username,
                            'email'  => $email,
                            'no_wa'  => $no_wa,
                            'j'      => $jabatan,
                            'r'      => $role,
                            's'      => $status,
                            'id'     => $id
                        ]);
                        flash_set('success', "Data pengguna '{$nama}' (@{$username}) berhasil diperbarui.");
                    }
                }
            }
        }
    } elseif ($action === 'change_role') {
        $id      = (int)($_POST['id'] ?? 0);
        $newRole = $_POST['new_role'] ?? 'user';
        
        if ($id > 0 && in_array($newRole, ['superadmin', 'admin', 'user'])) {
            $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmtTarget->execute(['id' => $id]);
            $targetRole = $stmtTarget->fetchColumn();
            if (!is_superadmin() && ($targetRole === 'superadmin' || $newRole === 'superadmin')) {
                flash_set('error', 'Akses ditolak: Role Admin tidak diizinkan mengubah peran Superadmin.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
                $stmt->execute(['r' => $newRole, 'id' => $id]);
                flash_set('success', "Peran pengguna berhasil diubah menjadi " . strtoupper($newRole) . ".");
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id != $user['id']) {
            $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmtTarget->execute(['id' => $id]);
            $targetRole = $stmtTarget->fetchColumn();
            if (!is_superadmin() && $targetRole === 'superadmin') {
                flash_set('error', 'Akses ditolak: Role Admin tidak diizinkan menonaktifkan Superadmin.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = :id");
                $stmt->execute(['id' => $id]);
                flash_set('success', "Status akun pengguna berhasil diperbarui.");
            }
        }
    } elseif ($action === 'reset_pass') {
        $id = (int)($_POST['id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '123456';
        if ($id > 0 && !empty($newPass)) {
            $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmtTarget->execute(['id' => $id]);
            $targetRole = $stmtTarget->fetchColumn();
            if (!is_superadmin() && $targetRole === 'superadmin') {
                flash_set('error', 'Akses ditolak: Role Admin tidak diizinkan mereset password Superadmin.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                $stmt->execute([
                    'p'  => password_hash($newPass, PASSWORD_BCRYPT),
                    'id' => $id
                ]);
                require_once __DIR__ . '/../includes/audit_helper.php';
                log_activity($pdo, 'USER_RESET_PASS', "Mereset password pengguna ID #{$id}.");
                flash_set('success', "Password pengguna berhasil direset.");
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'ID pengguna tidak valid.');
        } elseif ($id == $user['id']) {
            flash_set('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                flash_set('error', 'Pengguna tidak ditemukan.');
            } elseif (!is_superadmin() && $targetUser['role'] === 'superadmin') {
                flash_set('error', 'Akses ditolak: Role Admin tidak diizinkan menghapus akun Superadmin.');
            } else {
                // Check superadmin count
                $superadminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
                if ($targetUser['role'] === 'superadmin' && $superadminCount <= 1) {
                    flash_set('error', 'Tidak dapat menghapus superadmin terakhir pada sistem.');
                } else {
                    // Check reports association (unique placeholders for native PDO prepared statements)
                    $stmtCheckReports = $pdo->prepare("SELECT COUNT(*) FROM laporan_serah_terima WHERE petugas_menyerahkan_id = :id1 OR petugas_menerima_id = :id2 OR created_by = :id3");
                    $stmtCheckReports->execute(['id1' => $id, 'id2' => $id, 'id3' => $id]);
                    $reportCount = (int)$stmtCheckReports->fetchColumn();

                    if ($reportCount > 0) {
                        $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = :id")->execute(['id' => $id]);
                        flash_set('warning', "Pengguna '{$targetUser['nama_lengkap']}' memiliki {$reportCount} riwayat laporan serah terima operasional. Akun dinonaktifkan demi menjaga keaslian arsip dokumen.");
                    } else {
                        if (!empty($targetUser['foto']) && file_exists(UPLOAD_DIR . $targetUser['foto'])) {
                            @unlink(UPLOAD_DIR . $targetUser['foto']);
                        }
                        if (!empty($targetUser['ttd_file']) && file_exists(UPLOAD_DIR . $targetUser['ttd_file'])) {
                            @unlink(UPLOAD_DIR . $targetUser['ttd_file']);
                        }
                        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $id]);
                        require_once __DIR__ . '/../includes/audit_helper.php';
                        log_activity($pdo, 'USER_DELETE', "Menghapus permanen akun '{$targetUser['nama_lengkap']}' (@{$targetUser['username']}).");
                        flash_set('success', "Akun pengguna '{$targetUser['nama_lengkap']}' (@{$targetUser['username']}) berhasil dihapus permanen.");
                    }
                }
            }
        }
    } elseif ($action === 'import_csv') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'Pilih file CSV yang valid untuk diimpor.');
        } else {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');
            if ($handle === false) {
                flash_set('error', 'Gagal membuka file CSV.');
            } else {
                // Detect delimiter
                $firstLine = fgets($handle);
                $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                rewind($handle);

                // Read header row
                $header = fgetcsv($handle, 1000, $delimiter);
                if (!empty($header[0])) {
                    $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                }

                $imported = 0;
                $skipped  = 0;
                $updateExisting = !empty($_POST['update_existing']);

                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :u");
                $stmtInsert = $pdo->prepare("INSERT INTO users (nama_lengkap, username, password, jabatan, role, email, no_wa) VALUES (:nama, :u, :p, :j, :r, :email, :no_wa)");
                $stmtUpdate = $pdo->prepare("UPDATE users SET nama_lengkap = :nama, jabatan = :j, role = :r, email = :email, no_wa = :no_wa WHERE id = :id");

                while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                    if (empty($row) || count($row) < 2) continue;

                    $nama     = trim($row[0] ?? '');
                    $username = strtolower(trim($row[1] ?? ''));
                    $password = trim($row[2] ?? '');
                    $jabatan  = trim($row[3] ?? '');
                    $role     = strtolower(trim($row[4] ?? 'user'));
                    $email    = trim($row[5] ?? '');
                    $no_wa    = trim($row[6] ?? '');

                    if (empty($nama) || empty($username)) {
                        continue;
                    }

                    if (!in_array($role, ['user', 'admin', 'superadmin'])) {
                        $role = 'user';
                    }

                    $stmtCheck->execute(['u' => $username]);
                    $existingId = $stmtCheck->fetchColumn();

                    if ($existingId) {
                        if ($updateExisting) {
                            $stmtUpdate->execute([
                                'nama'   => $nama,
                                'j'      => $jabatan,
                                'r'      => $role,
                                'email'  => $email,
                                'no_wa'  => $no_wa,
                                'id'     => $existingId
                            ]);
                            $imported++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $passHash = password_hash(!empty($password) ? $password : '123456', PASSWORD_BCRYPT);
                        $stmtInsert->execute([
                            'nama'   => $nama,
                            'u'      => $username,
                            'p'      => $passHash,
                            'j'      => $jabatan,
                            'r'      => $role,
                            'email'  => $email,
                            'no_wa'  => $no_wa
                        ]);
                        $imported++;
                    }
                }
                fclose($handle);

                flash_set('success', "Import CSV selesai: {$imported} pengguna berhasil diproses, {$skipped} dilewati.");
            }
        }
    }

    // Preserve query parameters on redirect if any
    $redirectParams = [];
    if (!empty($_GET['page'])) $redirectParams['page'] = (int)$_GET['page'];
    if (!empty($_GET['per_page'])) $redirectParams['per_page'] = (int)$_GET['per_page'];
    if (!empty($_GET['search'])) $redirectParams['search'] = $_GET['search'];
    if (!empty($_GET['role'])) $redirectParams['role'] = $_GET['role'];
    if (!empty($_GET['status'])) $redirectParams['status'] = $_GET['status'];
    $redirectUrl = BASE_URL . 'master/users' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

    header('Location: ' . $redirectUrl);
    exit;
}

// Global User Stats (Always calculated on whole table)
$statsQuery = $pdo->query("SELECT role, COUNT(*) as c FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$cntSuperadmin = (int)($statsQuery['superadmin'] ?? 0);
$cntAdmin      = (int)($statsQuery['admin'] ?? 0);
$cntUser       = (int)($statsQuery['user'] ?? 0);
$totalAllUsers = $cntSuperadmin + $cntAdmin + $cntUser;

// Filtering & Search Parameters
$search       = trim($_GET['search'] ?? '');
$filterRole   = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$rawPerPage   = (int)($_GET['per_page'] ?? 10);
$perPage      = in_array($rawPerPage, [5, 10, 20, 50], true) ? $rawPerPage : 10;
$page         = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE SQL
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(nama_lengkap LIKE :s1 OR username LIKE :s2 OR email LIKE :s3 OR jabatan LIKE :s4 OR no_wa LIKE :s5)";
    $likeVal = "%{$search}%";
    $params['s1'] = $likeVal;
    $params['s2'] = $likeVal;
    $params['s3'] = $likeVal;
    $params['s4'] = $likeVal;
    $params['s5'] = $likeVal;
}
if ($filterRole !== '' && in_array($filterRole, ['superadmin', 'admin', 'user'])) {
    $where[] = "role = :role";
    $params['role'] = $filterRole;
}
if ($filterStatus !== '' && in_array($filterStatus, ['aktif', 'nonaktif'])) {
    $where[] = "status = :status";
    $params['status'] = $filterStatus;
}

$whereSql = implode(' AND ', $where);

// Count Total Filtered Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Calculate Pagination
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = max(0, ($page - 1) * $perPage);

// Fetch Paged Users
$querySql = "SELECT * FROM users WHERE {$whereSql} ORDER BY CASE WHEN role = 'superadmin' THEN 1 WHEN role = 'admin' THEN 2 ELSE 3 END, nama_lengkap ASC LIMIT {$offset}, {$perPage}";
$stmt = $pdo->prepare($querySql);
$stmt->execute($params);
$allUsers = $stmt->fetchAll();

// Pagination URL Builder Helper
function get_user_page_url($targetPage, $s, $r, $st, $pp) {
    $p = ['page' => $targetPage, 'per_page' => $pp];
    if ($s !== '') $p['search'] = $s;
    if ($r !== '') $p['role'] = $r;
    if ($st !== '') $p['status'] = $st;
    return '?' . http_build_query($p);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 w-full animate-stagger-1">

  <!-- HEADER & STATS CHIPS -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] dark:text-indigo-300 text-xs font-bold mb-2">
        <i class="ph-bold ph-shield-check"></i>
        <span>Hak Akses Berjenjang</span>
      </div>
      <h2 class="text-2xl sm:text-3xl font-extrabold text-[#181b2c] dark:text-white tracking-tight flex items-center gap-2.5">
        <i class="ph-fill ph-users-three text-[#6558f5]"></i>
        <span>Kelola Pengguna Sistem</span>
      </h2>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
        Kelola akun tim, hak otorisasi peran berjenjang, import data massal, dan keamanan akun
      </p>
    </div>

    <!-- Action Controls: Import CSV & Unduh Template & Stats -->
    <div class="flex items-center gap-2.5 flex-wrap">
      
      <!-- Import CSV Button -->
      <button type="button" onclick="openImportCsvModal()"
              class="px-4 py-2 rounded-2xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
        <i class="ph-bold ph-file-arrow-up text-base"></i>
        <span>Import CSV</span>
      </button>

      <!-- Unduh Template CSV Link -->
      <a href="?action=download_template"
         class="px-3.5 py-2 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-extrabold text-xs shadow-sm transition flex items-center gap-1.5"
         title="Download Format Template CSV">
        <i class="ph-bold ph-download-simple text-sm text-[#6558f5]"></i>
        <span>Template CSV</span>
      </a>

      <div class="h-6 w-[1px] bg-slate-200 dark:bg-slate-800 hidden sm:block"></div>

      <!-- Quick Stats Pills (NoteFlow Style) -->
      <div class="px-3 py-1.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
        <span class="text-slate-600 dark:text-slate-300">Superadmin:</span>
        <span class="text-rose-600 dark:text-rose-400"><?= $cntSuperadmin ?></span>
      </div>
      <div class="px-3 py-1.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2 h-2 rounded-full bg-[#6558f5]"></span>
        <span class="text-slate-600 dark:text-slate-300">Admin:</span>
        <span class="text-[#6558f5] dark:text-indigo-400"><?= $cntAdmin ?></span>
      </div>
      <div class="px-3 py-1.5 rounded-2xl bg-white dark:bg-[#1b1e30] border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-2 text-xs font-extrabold">
        <span class="w-2 h-2 rounded-full bg-[#68cfa3]"></span>
        <span class="text-slate-600 dark:text-slate-300">User:</span>
        <span class="text-emerald-600 dark:text-emerald-400"><?= $cntUser ?></span>
      </div>
    </div>
  </div>

  <!-- MAIN 2-COLUMN GRID (4 COLS FORM, 8 COLS TABLE) -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
    
    <!-- Left Column: Form Tambah User (4 Cols) -->
    <div class="xl:col-span-4 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature p-6 widget-card">
      <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
        <div class="w-10 h-10 rounded-2xl bg-[#efeffb] dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold shadow-sm">
          <i class="ph-bold ph-user-plus"></i>
        </div>
        <div>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white leading-tight">Tambah Pengguna Baru</h3>
          <p class="text-[11px] text-slate-400">Daftarkan akun operator atau supervisor baru</p>
        </div>
      </div>

      <form method="POST" action="" class="space-y-4">
        <input type="hidden" name="action" value="add" />
        
        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Lengkap *</label>
          <input type="text" name="nama_lengkap" required placeholder="Contoh: Rian Hidayat"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Username Login *</label>
          <input type="text" name="username" required placeholder="rian"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Alamat Email (Untuk Notifikasi)</label>
          <input type="email" name="email" placeholder="contoh: rian@operasional.id"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nomor WhatsApp (Aktif)</label>
          <input type="text" name="no_wa" placeholder="Contoh: 081234567890"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Password Awal *</label>
          <input type="password" name="password" required placeholder="Minimal 6 karakter"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Jabatan / Posisi</label>
          <input type="text" name="jabatan" placeholder="Contoh: Field Operator Shift"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Peran Pengguna (Role) *</label>
          <select name="role" required
                  class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
            <option value="user" selected>User (Operator Shift)</option>
            <option value="admin">Admin (Supervisor Unit)</option>
            <?php if (is_superadmin()): ?>
              <option value="superadmin">Superadmin (Root Access)</option>
            <?php endif; ?>
          </select>
        </div>

        <button type="submit" 
                class="w-full py-3 px-4 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
          <i class="ph-bold ph-plus-circle text-base"></i>
          <span>Daftarkan Akun Pengguna</span>
        </button>
      </form>
    </div>

    <!-- Right Column: Tabel Pengguna Terdaftar (8 Cols) -->
    <div class="xl:col-span-8 bg-white dark:bg-[#1b1e30] rounded-[26px] border border-slate-100 dark:border-slate-800/80 shadow-feature overflow-hidden widget-card">
      
      <!-- Top Title & Stats Banner -->
      <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-[#6558f5] animate-ping"></span>
          <h3 class="text-sm font-extrabold text-[#181b2c] dark:text-white">
            Pengguna Terdaftar (<?= $totalFiltered ?>)
          </h3>
        </div>
        <span class="text-[11px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full">
          Total Akun: <?= $totalAllUsers ?>
        </span>
      </div>

      <!-- Search & Filter Bar (Toolbar) -->
      <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 bg-[#fafaff] dark:bg-[#181b2c]/50">
        <form method="GET" action="" class="flex items-center gap-2 flex-wrap">
          <!-- Search Input -->
          <div class="relative flex-1 min-w-[180px]">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <i class="ph-bold ph-magnifying-glass text-sm"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, username, jabatan..."
                   class="w-full pl-9 pr-3 py-2 rounded-xl bg-white dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
          </div>

          <!-- Filter Role -->
          <select name="role" onchange="this.form.submit()"
                  class="px-3 py-2 rounded-xl bg-white dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-200 cursor-pointer focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none">
            <option value="">Semua Peran</option>
            <option value="superadmin" <?= $filterRole === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="user" <?= $filterRole === 'user' ? 'selected' : '' ?>>User</option>
          </select>

          <!-- Filter Status -->
          <select name="status" onchange="this.form.submit()"
                  class="px-3 py-2 rounded-xl bg-white dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-200 cursor-pointer focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none">
            <option value="">Semua Status</option>
            <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
          </select>

          <!-- Per Page -->
          <select name="per_page" onchange="this.form.submit()"
                  class="px-3 py-2 rounded-xl bg-white dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-200 cursor-pointer focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none">
            <option value="5" <?= $perPage === 5 ? 'selected' : '' ?>>5 / hal</option>
            <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 / hal</option>
            <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 / hal</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 / hal</option>
          </select>

          <!-- Submit Search Button -->
          <button type="submit" class="px-3.5 py-2 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] active:scale-95 text-white font-extrabold text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer">
            <i class="ph-bold ph-funnel text-xs"></i>
            <span>Filter</span>
          </button>

          <!-- Reset Filter Button if active -->
          <?php if ($search !== '' || $filterRole !== '' || $filterStatus !== '' || $perPage !== 10): ?>
            <a href="users" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-extrabold text-xs transition flex items-center gap-1" title="Reset Filter">
              <i class="ph-bold ph-x text-xs"></i>
              <span>Reset</span>
            </a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table Section -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#f7f7fb] dark:bg-[#151828] text-slate-500 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="py-3.5 px-5 whitespace-nowrap">Nama &amp; Username</th>
              <th class="py-3.5 px-4 whitespace-nowrap">Kontak Notifikasi</th>
              <th class="py-3.5 px-4 whitespace-nowrap">Jabatan</th>
              <th class="py-3.5 px-4 whitespace-nowrap">Peran (Role)</th>
              <th class="py-3.5 px-4 text-center whitespace-nowrap">Status</th>
              <th class="py-3.5 px-5 text-center whitespace-nowrap min-w-[210px]">Tindakan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
            <?php if (empty($allUsers)): ?>
              <tr>
                <td colspan="6" class="py-12 text-center text-slate-400">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <i class="ph-thin ph-users text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p class="text-xs font-semibold">Tidak ada pengguna yang sesuai dengan filter pencarian.</p>
                    <a href="users" class="text-xs text-[#6558f5] hover:underline font-bold mt-1">Reset Filter</a>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($allUsers as $u): ?>
                <tr class="hover:bg-[#f7f7fb] dark:hover:bg-slate-800/50 transition-colors">
                  <td class="py-3.5 px-5 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-tr from-[#6558f5] to-indigo-400 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700">
                        <?php $uAvatar = get_user_avatar_url($u['foto'] ?? null); ?>
                        <?php if ($uAvatar): ?>
                          <img src="<?= $uAvatar ?>" alt="Avatar" class="w-full h-full object-cover">
                        <?php else: ?>
                          <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="font-extrabold text-[#181b2c] dark:text-white text-xs leading-tight">
                          <?= sanitize($u['nama_lengkap']) ?>
                        </div>
                        <div class="text-[11px] font-mono text-slate-400">@<?= sanitize($u['username']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 whitespace-nowrap">
                    <div class="space-y-1 text-[11px]">
                      <?php if (!empty($u['email'])): ?>
                        <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 font-medium">
                          <i class="ph-bold ph-envelope-simple text-[#6558f5]"></i>
                          <span><?= sanitize($u['email']) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($u['no_wa'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $u['no_wa']) ?>" target="_blank"
                           class="inline-flex items-center gap-1 text-[#1c7a52] dark:text-emerald-400 hover:underline font-bold">
                          <i class="ph-bold ph-whatsapp-logo text-xs"></i>
                          <span><?= sanitize($u['no_wa']) ?></span>
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 font-semibold whitespace-nowrap">
                    <?= sanitize($u['jabatan'] ?: '—') ?>
                  </td>
                  <td class="py-3.5 px-4 whitespace-nowrap">
                    <?= badge_role($u['role']) ?>
                  </td>
                  <td class="py-3.5 px-4 text-center whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $u['status'] === 'aktif' ? 'bg-[#e6f7f0] text-[#1c7a52] dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $u['status'] === 'aktif' ? 'bg-[#68cfa3]' : 'bg-slate-400' ?>"></span>
                      <?= strtoupper($u['status']) ?>
                    </span>
                  </td>
                  <td class="py-3.5 px-5 text-center whitespace-nowrap">
                    <?php if (!is_superadmin() && $u['role'] === 'superadmin'): ?>
                      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10.5px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200/70 dark:border-slate-700/70 select-none" title="Hanya Superadmin yang dapat mengelola akun ini">
                        <i class="ph-fill ph-lock-key text-xs text-rose-400"></i>
                        <span>Dilindungi</span>
                      </span>
                    <?php else: ?>
                    <div class="inline-flex items-center justify-center gap-1.5">
                      
                      <!-- Edit User Button -->
                      <button type="button" 
                              onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                              title="Edit Data & Hak Akses Pengguna"
                              class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-[#efeffb] hover:bg-[#e0dffc] dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-[#6558f5] dark:text-indigo-300 active:scale-95 transition flex items-center gap-1 cursor-pointer border border-indigo-200/50 dark:border-indigo-800/50 shadow-2xs">
                        <i class="ph-bold ph-pencil-simple text-xs"></i>
                        <span>Edit</span>
                      </button>

                      <!-- Reset Password Button -->
                      <button type="button" 
                              onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nama_lengkap'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                              title="Reset Password Akun"
                              class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/60 active:scale-95 transition flex items-center gap-1 border border-amber-200/80 dark:border-amber-800 cursor-pointer shadow-2xs">
                        <i class="ph-bold ph-key text-xs"></i>
                        <span>Reset</span>
                      </button>

                      <?php if ($u['id'] != $user['id']): ?>
                        <!-- Toggle Status Button -->
                        <form method="POST" action="" class="inline" onsubmit="return confirm('<?= $u['status'] === 'aktif' ? 'Nonaktifkan akun ' . addslashes(htmlspecialchars($u['nama_lengkap'])) . '?' : 'Aktifkan akun ' . addslashes(htmlspecialchars($u['nama_lengkap'])) . '?' ?>')">
                          <input type="hidden" name="action" value="toggle_status" />
                          <input type="hidden" name="id" value="<?= $u['id'] ?>" />
                          <button type="submit" 
                                  title="<?= $u['status'] === 'aktif' ? 'Klik untuk Nonaktifkan Akun' : 'Klik untuk Aktifkan Akun' ?>"
                                  class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold <?= $u['status'] === 'aktif' ? 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700' : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800' ?> active:scale-95 transition flex items-center gap-1 cursor-pointer shadow-2xs">
                            <i class="ph-bold <?= $u['status'] === 'aktif' ? 'ph-power' : 'ph-check-circle' ?> text-xs"></i>
                            <span><?= $u['status'] === 'aktif' ? 'Nonaktif' : 'Aktif' ?></span>
                          </button>
                        </form>

                        <!-- Delete User Button -->
                        <button type="button" 
                                onclick="openDeleteUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nama_lengkap'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                title="Hapus Akun Pengguna"
                                class="px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 active:scale-95 transition flex items-center gap-1 border border-rose-200/80 dark:border-rose-800 cursor-pointer shadow-2xs">
                          <i class="ph-bold ph-trash text-xs"></i>
                          <span>Hapus</span>
                        </button>
                      <?php else: ?>
                        <span class="text-[10px] font-extrabold text-[#6558f5] bg-[#efeffb] dark:bg-indigo-950/50 px-2.5 py-1.5 rounded-xl border border-indigo-100 dark:border-indigo-900/60">(Akun Anda)</span>
                      <?php endif; ?>

                    </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <!-- Info Text -->
        <div class="text-xs text-slate-500 dark:text-slate-400">
          Menampilkan <span class="font-extrabold text-slate-800 dark:text-white"><?= $totalFiltered > 0 ? $offset + 1 : 0 ?></span> - <span class="font-extrabold text-slate-800 dark:text-white"><?= min($offset + $perPage, $totalFiltered) ?></span> dari <span class="font-extrabold text-[#6558f5]"><?= $totalFiltered ?></span> pengguna <?= ($search !== '' || $filterRole !== '' || $filterStatus !== '') ? '(difilter)' : '' ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
          <div class="flex items-center gap-1.5 flex-wrap">
            <!-- Tombol Sebelumnya -->
            <?php if ($page > 1): ?>
              <a href="<?= get_user_page_url($page - 1, $search, $filterRole, $filterStatus, $perPage) ?>" 
                 class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#151828] text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition flex items-center gap-1">
                <i class="ph-bold ph-caret-left"></i>
                <span>Sebelumnya</span>
              </a>
            <?php else: ?>
              <span class="px-3 py-1.5 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-900/40 text-slate-300 dark:text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1">
                <i class="ph-bold ph-caret-left"></i>
                <span>Sebelumnya</span>
              </span>
            <?php endif; ?>

            <!-- Nomor Halaman -->
            <?php
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);
            if ($startPage > 1) {
                echo '<a href="' . get_user_page_url(1, $search, $filterRole, $filterStatus, $perPage) . '" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#151828] text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-center hover:bg-slate-50 transition">1</a>';
                if ($startPage > 2) echo '<span class="text-slate-400 px-1 text-xs font-bold">...</span>';
            }
            for ($p = $startPage; $p <= $endPage; $p++) {
                if ($p == $page) {
                    echo '<span class="w-8 h-8 rounded-xl bg-[#6558f5] text-white text-xs font-extrabold flex items-center justify-center shadow-sm">' . $p . '</span>';
                } else {
                    echo '<a href="' . get_user_page_url($p, $search, $filterRole, $filterStatus, $perPage) . '" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#151828] text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-center hover:bg-slate-50 transition">' . $p . '</a>';
                }
            }
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span class="text-slate-400 px-1 text-xs font-bold">...</span>';
                echo '<a href="' . get_user_page_url($totalPages, $search, $filterRole, $filterStatus, $perPage) . '" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#151828] text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-center hover:bg-slate-50 transition">' . $totalPages . '</a>';
            }
            ?>

            <!-- Tombol Berikutnya -->
            <?php if ($page < $totalPages): ?>
              <a href="<?= get_user_page_url($page + 1, $search, $filterRole, $filterStatus, $perPage) ?>" 
                 class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#151828] text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition flex items-center gap-1">
                <span>Berikutnya</span>
                <i class="ph-bold ph-caret-right"></i>
              </a>
            <?php else: ?>
              <span class="px-3 py-1.5 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-900/40 text-slate-300 dark:text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1">
                <span>Berikutnya</span>
                <i class="ph-bold ph-caret-right"></i>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</div>

<!-- MODAL EDIT PENGGUNA -->
<div id="editUserModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeEditUserModal()"></div>

  <div class="relative w-full max-w-lg bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100 animate-stagger-1">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-pencil-simple"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Edit Data &amp; Hak Akses</h3>
          <p class="text-[11px] text-slate-400">Perbarui profil, jabatan, peran, atau status pengguna</p>
        </div>
      </div>
      <button type="button" onclick="closeEditUserModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="edit_user" />
      <input type="hidden" name="id" id="edit_user_id" value="" />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div class="sm:col-span-2">
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nama Lengkap *</label>
          <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Username Login *</label>
          <input type="text" name="username" id="edit_username" required
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Jabatan / Posisi</label>
          <input type="text" name="jabatan" id="edit_jabatan"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Email Notifikasi</label>
          <input type="email" name="email" id="edit_email"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Nomor WhatsApp</label>
          <input type="text" name="no_wa" id="edit_no_wa"
                 class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-semibold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none transition" />
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Peran (Role) *</label>
          <select name="role" id="edit_role" required
                  class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
            <option value="user">User (Operator Shift)</option>
            <option value="admin">Admin (Supervisor Unit)</option>
            <?php if (is_superadmin()): ?>
              <option value="superadmin">Superadmin (Root Access)</option>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-extrabold text-slate-600 dark:text-slate-300 mb-1.5">Status Akun *</label>
          <select name="status" id="edit_status" required
                  class="w-full px-3.5 py-2.5 rounded-xl bg-[#f7f7fb] dark:bg-[#151828] border border-slate-200/80 dark:border-slate-700/70 text-xs font-extrabold focus:ring-2 focus:ring-[#6558f5]/40 focus:outline-none cursor-pointer transition">
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>

      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeEditUserModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-black text-xs shadow-md transition flex items-center gap-1.5 active:scale-95 cursor-pointer">
          <i class="ph-bold ph-check"></i>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <!-- Backdrop -->
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeResetPasswordModal()"></div>

  <!-- Modal Dialog -->
  <div class="relative w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-key"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Reset Password Akun</h3>
          <p class="text-[11px] text-slate-400">Atur password baru untuk akun pengguna ini</p>
        </div>
      </div>
      <button type="button" onclick="closeResetPasswordModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <!-- Target User Info -->
    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center font-bold text-xs uppercase" id="modalUserInitials">
        US
      </div>
      <div>
        <div class="font-extrabold text-xs text-slate-900 dark:text-white" id="modalUserName">Nama Pengguna</div>
        <div class="font-mono text-[11px] text-slate-400" id="modalUserHandle">@username</div>
      </div>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="reset_pass" />
      <input type="hidden" name="id" id="modalUserId" value="" />

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
          Password Baru *
        </label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="ph ph-lock text-base"></i>
          </span>
          <input type="text" name="new_password" id="modalNewPassword" required value="123456"
                 class="w-full pl-9 pr-24 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-[#6558f5] focus:outline-none" />
          <button type="button" onclick="generateRandomPass()" 
                  class="absolute inset-y-1.5 right-1.5 px-2.5 bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] text-[10px] font-bold rounded-lg hover:bg-[#6558f5] hover:text-white transition flex items-center gap-1 cursor-pointer">
            <i class="ph-bold ph-arrows-clockwise"></i>
            <span>Acak</span>
          </button>
        </div>
      </div>

      <!-- Quick Password Presets -->
      <div class="flex items-center gap-2">
        <span class="text-[10px] text-slate-400 uppercase font-bold">Preset:</span>
        <button type="button" onclick="setPresetPass('123456')" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold cursor-pointer">
          123456
        </button>
        <button type="button" onclick="setPresetPass('operator123')" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold cursor-pointer">
          operator123
        </button>
        <button type="button" onclick="setPresetPass('admin123')" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold cursor-pointer">
          admin123
        </button>
      </div>

      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeResetPasswordModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer active:scale-95">
          <i class="ph-bold ph-check"></i>
          <span>Simpan Password Baru</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL IMPORT CSV -->
<div id="importCsvModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeImportCsvModal()"></div>

  <div class="relative w-full max-w-lg bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-5 animate-stagger-1 text-slate-800 dark:text-slate-100">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#6558f5] flex items-center justify-center text-xl font-bold">
          <i class="ph-bold ph-file-csv"></i>
        </div>
        <div>
          <h3 class="font-black text-base text-slate-900 dark:text-white">Import Pengguna via CSV</h3>
          <p class="text-[11px] text-slate-400">Daftarkan banyak akun sekaligus secara cepat</p>
        </div>
      </div>
      <button type="button" onclick="closeImportCsvModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
        <i class="ph-bold ph-x text-lg"></i>
      </button>
    </div>

    <!-- Instructions & Template Download -->
    <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-slate-900/60 border border-indigo-100 dark:border-slate-800 space-y-2 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-extrabold text-[#6558f5] text-[11px] uppercase tracking-wider flex items-center gap-1">
          <i class="ph-bold ph-info"></i> Format Kolom CSV
        </span>
        <a href="?action=download_template" 
           class="text-[11px] font-extrabold text-[#6558f5] hover:underline flex items-center gap-1">
          <i class="ph-bold ph-download-simple"></i>
          <span>Unduh Template CSV</span>
        </a>
      </div>
      <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed font-mono bg-white dark:bg-slate-800/80 p-2 rounded-xl border border-slate-200 dark:border-slate-700">
        nama_lengkap, username, password, jabatan, role, email, no_wa
      </p>
      <ul class="text-[10.5px] text-slate-500 dark:text-slate-400 list-disc list-inside space-y-0.5">
        <li><strong>Role:</strong> <code>user</code>, <code>admin</code>, atau <code>superadmin</code>.</li>
        <li><strong>Password:</strong> Jika dikosongkan di CSV, default menjadi <code>123456</code>.</li>
      </ul>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="action" value="import_csv" />

      <!-- Drag & drop / upload box -->
      <div class="w-full p-6 border-2 border-dashed border-indigo-200 dark:border-slate-700 hover:border-[#6558f5] rounded-2xl bg-slate-50 dark:bg-slate-900/40 text-center cursor-pointer transition"
           onclick="document.getElementById('csv_file_input').click()">
        <i class="ph ph-upload-simple text-3xl text-[#6558f5] mb-2 inline-block"></i>
        <div class="font-extrabold text-xs text-slate-800 dark:text-white" id="csv_file_label">
          Pilih File CSV atau Tarik ke Sini
        </div>
        <p class="text-[10.5px] text-slate-400 mt-1">Mendukung file berekstensi .csv (pemisah koma atau titik koma)</p>
        <input type="file" name="csv_file" id="csv_file_input" accept=".csv,text/csv" required class="hidden" onchange="updateCsvFileName(this)">
      </div>

      <!-- Update existing option -->
      <label class="flex items-center gap-2.5 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="update_existing" value="1" class="rounded border-slate-300 text-[#6558f5] focus:ring-[#6558f5]">
        <span>Perbarui data jika username sudah terdaftar sebelumnya</span>
      </label>

      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeImportCsvModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-6 py-2.5 rounded-xl bg-[#6558f5] hover:bg-[#5244e8] text-white font-black text-xs shadow-md transition flex items-center gap-2 cursor-pointer active:scale-95">
          <i class="ph-bold ph-upload-simple text-sm"></i>
          <span>Mulai Impor Pengguna</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CONFIRM DELETE USER -->
<div id="deleteUserModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDeleteUserModal()"></div>

  <div class="relative w-full max-w-md bg-white dark:bg-[#131b2e] rounded-[28px] shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 z-10 space-y-4 text-slate-800 dark:text-slate-100">
    <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
      <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl font-bold">
        <i class="ph-bold ph-trash"></i>
      </div>
      <div>
        <h3 class="font-black text-base text-slate-900 dark:text-white">Konfirmasi Hapus Akun</h3>
        <p class="text-[11px] text-slate-400">Tindakan ini tidak dapat dibatalkan</p>
      </div>
    </div>

    <!-- Target User Info -->
    <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/40">
      <p class="text-xs text-rose-900 dark:text-rose-200 leading-relaxed">
        Apakah Anda yakin ingin menghapus akun pengguna <strong id="deleteUserName">Nama Pengguna</strong> (<span id="deleteUserHandle" class="font-mono">@username</span>)?
      </p>
      <p class="text-[10.5px] text-slate-500 dark:text-slate-400 mt-2">
        *Jika pengguna ini memiliki riwayat dokumen serah terima, akun akan otomatis dinonaktifkan demi melindungi arsip laporan.
      </p>
    </div>

    <form method="POST" action="" class="space-y-4">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="id" id="deleteUserId" value="" />

      <div class="pt-2 flex items-center justify-end gap-2.5">
        <button type="button" onclick="closeDeleteUserModal()" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 font-bold text-xs transition cursor-pointer">
          Batal
        </button>
        <button type="submit" 
                class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs shadow-md transition flex items-center gap-1.5 active:scale-95 cursor-pointer">
          <i class="ph-bold ph-trash"></i>
          <span>Ya, Hapus Pengguna</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  // Edit User Modal Handlers
  function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_nama_lengkap').value = user.nama_lengkap || '';
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_no_wa').value = user.no_wa || '';
    document.getElementById('edit_jabatan').value = user.jabatan || '';
    document.getElementById('edit_role').value = user.role || 'user';
    document.getElementById('edit_status').value = user.status || 'aktif';
    document.getElementById('editUserModal').classList.remove('hidden');
  }

  function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
  }

  // Reset Password Modal Handlers
  function openResetPasswordModal(userId, fullName, username) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUserName').innerText = fullName;
    document.getElementById('modalUserHandle').innerText = '@' + username;
    document.getElementById('modalUserInitials').innerText = fullName.substring(0, 2).toUpperCase();
    document.getElementById('modalNewPassword').value = '123456';
    document.getElementById('resetPasswordModal').classList.remove('hidden');
  }

  function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
  }

  function setPresetPass(p) {
    document.getElementById('modalNewPassword').value = p;
  }

  function generateRandomPass() {
    const chars = 'abcdefghijkmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    let res = '';
    for (let i = 0; i < 8; i++) {
      res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('modalNewPassword').value = res;
  }

  // Import CSV Modal Handlers
  function openImportCsvModal() {
    document.getElementById('importCsvModal').classList.remove('hidden');
  }
  function closeImportCsvModal() {
    document.getElementById('importCsvModal').classList.add('hidden');
  }
  function updateCsvFileName(input) {
    if (input.files && input.files[0]) {
      document.getElementById('csv_file_label').innerText = '✓ ' + input.files[0].name;
    }
  }

  // Delete User Modal Handlers
  function openDeleteUserModal(userId, fullName, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').innerText = fullName;
    document.getElementById('deleteUserHandle').innerText = '@' + username;
    document.getElementById('deleteUserModal').classList.remove('hidden');
  }
  function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').classList.add('hidden');
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
