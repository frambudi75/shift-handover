<?php
// includes/auth.php - Authentication & RBAC (superadmin, admin, user)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    
    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT id, nama_lengkap, username, email, no_wa, role, jabatan, foto, ttd_type, ttd_file FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['nama_lengkap'] = $u['nama_lengkap'];
            $_SESSION['username']     = $u['username'];
            $_SESSION['email']        = $u['email'];
            $_SESSION['no_wa']        = $u['no_wa'];
            $_SESSION['role']         = $u['role'];
            $_SESSION['jabatan']      = $u['jabatan'];
            $_SESSION['foto']         = $u['foto'];
            $_SESSION['ttd_type']     = $u['ttd_type'] ?? 'barcode';
            $_SESSION['ttd_file']     = $u['ttd_file'] ?? null;
            return $u;
        }
    }

    return [
        'id'           => $_SESSION['user_id'],
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User',
        'username'     => $_SESSION['username'] ?? '',
        'email'        => $_SESSION['email'] ?? '',
        'no_wa'        => $_SESSION['no_wa'] ?? '',
        'role'         => $_SESSION['role'] ?? 'user',
        'jabatan'      => $_SESSION['jabatan'] ?? '',
        'foto'         => $_SESSION['foto'] ?? null,
        'ttd_type'     => $_SESSION['ttd_type'] ?? 'barcode',
        'ttd_file'     => $_SESSION['ttd_file'] ?? null
    ];
}

function is_superadmin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'superadmin';
}

function is_admin(): bool {
    return is_logged_in() && in_array($_SESSION['role'] ?? '', ['superadmin', 'admin']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Silakan masuk terlebih dahulu untuk mengakses sistem.';
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Akses ditolak. Fitur ini membutuhkan hak akses Administrator atau Superadmin.';
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

function require_superadmin(): void {
    require_login();
    if (!is_superadmin()) {
        $_SESSION['flash_error'] = 'Akses ditolak. Halaman ini hanya untuk Super Administrator.';
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}
