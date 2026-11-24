<?php
// database/alter_signatures.php
require_once __DIR__ . '/../config/database.php';

try {
    $columns = $pdo->query("SHOW COLUMNS FROM laporan_serah_terima")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('jenis_ttd_menyerahkan', $columns)) {
        $pdo->exec("ALTER TABLE `laporan_serah_terima` ADD COLUMN `jenis_ttd_menyerahkan` VARCHAR(20) DEFAULT 'barcode'");
        echo "✓ Added jenis_ttd_menyerahkan\n";
    }
    if (!in_array('jenis_ttd_menerima', $columns)) {
        $pdo->exec("ALTER TABLE `laporan_serah_terima` ADD COLUMN `jenis_ttd_menerima` VARCHAR(20) DEFAULT NULL");
        echo "✓ Added jenis_ttd_menerima\n";
    }
    if (!in_array('catatan_approval', $columns)) {
        $pdo->exec("ALTER TABLE `laporan_serah_terima` ADD COLUMN `catatan_approval` TEXT DEFAULT NULL");
        echo "✓ Added catatan_approval\n";
    }
    echo "✓ Signature columns verified in laporan_serah_terima\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
