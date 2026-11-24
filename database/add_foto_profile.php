<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('foto', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL AFTER jabatan");
        echo "OK: Added 'foto' column to 'users' table.\n";
    } else {
        echo "INFO: 'foto' column already exists in 'users' table.\n";
    }

    $avatarDir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($avatarDir)) {
        mkdir($avatarDir, 0777, true);
        echo "OK: Created avatars directory at {$avatarDir}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
