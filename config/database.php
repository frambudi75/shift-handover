<?php
// config/database.php - PDO Database Connection

$env_host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
$env_port = (int)(getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306));
$env_name = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'db_serah_terima');
$env_user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$env_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');

if (!defined('DB_HOST')) define('DB_HOST', $env_host);
if (!defined('DB_PORT')) define('DB_PORT', $env_port);
if (!defined('DB_NAME')) define('DB_NAME', $env_name);
if (!defined('DB_USER')) define('DB_USER', $env_user);
if (!defined('DB_PASS')) define('DB_PASS', $env_pass);

$db_host = DB_HOST;
$db_port = DB_PORT;
$db_name = DB_NAME;
$db_user = DB_USER;
$db_pass = DB_PASS;

try {
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
