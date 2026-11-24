<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('ttd_type', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ttd_type ENUM('barcode', 'upload', 'canvas') NOT NULL DEFAULT 'barcode' AFTER foto");
        echo "OK: Added 'ttd_type' column to 'users' table.\n";
    } else {
        echo "INFO: 'ttd_type' column already exists in 'users' table.\n";
    }

    if (!in_array('ttd_file', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ttd_file VARCHAR(255) NULL AFTER ttd_type");
        echo "OK: Added 'ttd_file' column to 'users' table.\n";
    } else {
        echo "INFO: 'ttd_file' column already exists in 'users' table.\n";
    }

    $sigDir = UPLOAD_DIR . 'signatures/';
    if (!is_dir($sigDir)) {
        mkdir($sigDir, 0777, true);
        echo "OK: Created signatures directory at {$sigDir}\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
