<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM master_shift")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('jam_serah_terima', $cols)) {
        $pdo->exec("ALTER TABLE master_shift ADD COLUMN jam_serah_terima TIME NULL AFTER jam_selesai");
        $pdo->exec("UPDATE master_shift SET jam_serah_terima = jam_selesai WHERE jam_serah_terima IS NULL");
        echo "OK: Added 'jam_serah_terima' column to 'master_shift' table.\n";
    } else {
        echo "INFO: 'jam_serah_terima' column already exists in 'master_shift'.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
