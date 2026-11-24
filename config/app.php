<?php
// config/app.php - Application Configuration & Dynamic Settings

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../includes/cache.php';

if (!defined('BASE_URL')) {
    $envBase = getenv('APP_BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('BASE_URL', $envBase);
    } else {
        // Auto-detect BASE_URL relative to DOCUMENT_ROOT (works on XAMPP, cPanel root /, aaPanel, DirectAdmin)
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
        $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
        if ($docRoot !== '' && $appRoot !== '' && strpos($appRoot, $docRoot) === 0) {
            $relPath = trim(substr($appRoot, strlen($docRoot)), '/');
            define('BASE_URL', $relPath === '' ? '/' : '/' . $relPath . '/');
        } else {
            define('BASE_URL', '/serah-terima/');
        }
    }
}
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . 'uploads/');

// Settings cache
$_app_settings_cache = null;

function get_all_settings(): array {
    global $pdo, $_app_settings_cache;
    if ($_app_settings_cache !== null) {
        return $_app_settings_cache;
    }

    // Ambil dari Redis Cache terlebih dahulu
    $cached = cache_get('app_settings');
    if (is_array($cached) && !empty($cached)) {
        $_app_settings_cache = $cached;
        return $_app_settings_cache;
    }

    if (!isset($pdo)) {
        require_once __DIR__ . '/database.php';
    }

    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $_app_settings_cache = $rows ?: [];
        cache_set('app_settings', $_app_settings_cache, 3600); // Cache selama 1 jam
    } catch (Exception $e) {
        $_app_settings_cache = [];
    }

    return $_app_settings_cache;
}

function get_setting(string $key, string $default = ''): string {
    $settings = get_all_settings();
    return $settings[$key] ?? $default;
}

function set_setting(string $key, ?string $value): bool {
    global $pdo, $_app_settings_cache;
    if (!isset($pdo)) {
        require_once __DIR__ . '/database.php';
    }
    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $res = $stmt->execute(['k' => $key, 'v' => $value]);
    if ($_app_settings_cache !== null) {
        $_app_settings_cache[$key] = $value;
    }
    // Hapus Redis cache agar update langsung terbaca seketika
    cache_delete('app_settings');
    return $res;
}

function get_app_name(): string {
    return get_setting('app_name', 'OMIT Shift Handover');
}

function get_app_full_name(): string {
    return get_setting('app_full_name', 'Sistem Laporan Serah Terima Shift Operasional');
}

function get_app_logo_url(): ?string {
    $logo = get_setting('app_logo', '');
    if (!empty($logo) && file_exists(UPLOAD_DIR . $logo)) {
        return UPLOAD_URL . $logo;
    }
    return null;
}

// Fallback constant definitions for backward compatibility
if (!defined('APP_NAME')) {
    define('APP_NAME', get_app_name());
}
if (!defined('APP_FULL_NAME')) {
    define('APP_FULL_NAME', get_app_full_name());
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', 'v2.6.0 LTS');
}
