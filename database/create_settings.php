<?php
// database/create_settings.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== Creating app_settings Table ===\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $defaults = [
        'app_name'           => 'OMIT Shift Handover',
        'app_full_name'      => 'Sistem Laporan Serah Terima Shift Operasional',
        'app_logo'           => '',
        'compress_enabled'   => '1',
        'compress_quality'   => '80',
        'compress_max_width' => '1600'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES (:k, :v)");
    foreach ($defaults as $k => $v) {
        $stmt->execute(['k' => $k, 'v' => $v]);
    }

    echo "✓ app_settings table created and default values seeded!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
