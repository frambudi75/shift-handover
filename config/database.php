<?php
// config/database.php - Konfigurasi Koneksi Database (PDO)

if (getenv('DB_HOST') !== false) {
    // =========================================================================
    // 1. KONFIGURASI OTOMATIS UNTUK DOCKER
    //    (Nilai diambil otomatis dari docker-compose.yml / file .env)
    // =========================================================================
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
    define('DB_NAME', getenv('DB_NAME') ?: 'db_serah_terima');
    define('DB_USER', getenv('DB_USER') ?: 'serahterima_user');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
} else {
    // =========================================================================
    // 2. KONFIGURASI UNTUK CPANEL / AAPANEL / DIRECTADMIN / XAMPP
    //    (Silakan ubah sesuai kredensial database hosting atau lokal Anda)
    // =========================================================================
    define('DB_HOST', '127.0.0.1');       // Di cPanel/aaPanel/XAMPP biasanya 'localhost' atau '127.0.0.1'
    define('DB_PORT', 3306);              // Port standar MySQL/MariaDB: 3306
    define('DB_NAME', 'db_serah_terima'); // Nama database yang dibuat di cPanel/XAMPP
    define('DB_USER', 'root');            // Username database (XAMPP default: 'root')
    define('DB_PASS', '');                // Password database (XAMPP default: kosong '')
}

// =========================================================================
// KONEKSI PDO (TIDAK PERLU DIUBAH)
// =========================================================================
$mysqlInitCmdKey = defined('Pdo\Mysql::ATTR_INIT_COMMAND')
    ? constant('Pdo\Mysql::ATTR_INIT_COMMAND')
    : PDO::MYSQL_ATTR_INIT_COMMAND;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            $mysqlInitCmdKey             => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
