<?php
// scripts/migrate_indexes.php - Enterprise Database Indexing & Performance Migration
require_once __DIR__ . '/../config/database.php';

echo "Starting database performance indexing...\n";

$indexesToCreate = [
    // Table: laporan_serah_terima
    'laporan_serah_terima' => [
        'idx_laporan_tgl_jam'     => '(`tanggal` DESC, `jam_serah_terima` DESC)',
        'idx_laporan_status_val'  => '(`status_validasi`, `tanggal` DESC)',
        'idx_laporan_lokasi_tgl'  => '(`lokasi_id`, `tanggal` DESC)',
        'idx_laporan_shift_tgl'   => '(`shift_id`, `tanggal` DESC)',
        'idx_laporan_created_by'  => '(`created_by`)',
    ],
    // Table: laporan_pekerjaan
    'laporan_pekerjaan' => [
        'idx_pekerjaan_status_prio'    => '(`status`, `prioritas`)',
        'idx_pekerjaan_laporan_status' => '(`laporan_id`, `status`)',
        'idx_pekerjaan_no_tiket'       => '(`no_tiket`)',
    ],
    // Table: users
    'users' => [
        'idx_users_role_status'   => '(`role`, `status`)',
        'idx_users_status_nama'   => '(`status`, `nama_lengkap`)',
        'idx_users_email'         => '(`email`)',
    ],
    // Table: master_shift
    'master_shift' => [
        'idx_shift_status'        => '(`status`)',
    ],
    // Table: master_lokasi
    'master_lokasi' => [
        'idx_lokasi_status'       => '(`status`)',
    ],
];

foreach ($indexesToCreate as $tableName => $indexes) {
    echo "\nProcessing table: {$tableName}...\n";
    
    // Check existing indexes
    $existing = $pdo->query("SHOW INDEX FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
    $existingNames = array_column($existing, 'Key_name');

    foreach ($indexes as $indexName => $columnsDef) {
        if (in_array($indexName, $existingNames)) {
            echo "  [SKIP] Index {$indexName} already exists.\n";
        } else {
            try {
                $sql = "CREATE INDEX `{$indexName}` ON `{$tableName}` {$columnsDef}";
                $pdo->exec($sql);
                echo "  [OK] Created index {$indexName} ON {$tableName} {$columnsDef}\n";
            } catch (Exception $e) {
                echo "  [ERROR] Failed to create index {$indexName}: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Analyze tables to update optimizer statistics
echo "\nUpdating table optimizer statistics (ANALYZE TABLE)...\n";
$tables = ['laporan_serah_terima', 'laporan_pekerjaan', 'users', 'master_shift', 'master_lokasi', 'app_settings'];
foreach ($tables as $t) {
    $pdo->query("ANALYZE TABLE `{$t}`");
    echo "  [OK] Analyzed table {$t}\n";
}

echo "\nDatabase indexing completed successfully!\n";
