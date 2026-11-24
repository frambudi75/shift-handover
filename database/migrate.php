<?php
// Migration & Seeder Script for db_serah_terima

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "=== Running Database Migration ===\n";
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);
    echo "✓ Database and tables created successfully!\n";

    // Switch to db_serah_terima
    $pdo->exec("USE `db_serah_terima`");

    // 1. Seed Users
    $users = [
        [
            'nama_lengkap' => 'Administrator Sistem',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'jabatan' => 'System Supervisor'
        ],
        [
            'nama_lengkap' => 'Andi Pratama',
            'username' => 'andi',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'Control Room Senior Operator'
        ],
        [
            'nama_lengkap' => 'Budi Santoso',
            'username' => 'budi',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'Production Shift Leader'
        ],
        [
            'nama_lengkap' => 'Citra Lestari',
            'username' => 'citra',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'Quality & Operations'
        ],
        [
            'nama_lengkap' => 'Dewi Anggraini',
            'username' => 'dewi',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'Logistics Officer'
        ],
        [
            'nama_lengkap' => 'Eko Saputra',
            'username' => 'eko',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'IT & Systems Tech'
        ],
        [
            'nama_lengkap' => 'Fajar Nugroho',
            'username' => 'fajar',
            'password' => password_hash('operator123', PASSWORD_BCRYPT),
            'role' => 'operator',
            'jabatan' => 'Utility & Facility Tech'
        ]
    ];

    $stmtUser = $pdo->prepare("INSERT INTO `users` (`nama_lengkap`, `username`, `password`, `role`, `jabatan`) 
        VALUES (:nama_lengkap, :username, :password, :role, :jabatan)
        ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`), `jabatan` = VALUES(`jabatan`)");
    
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }
    echo "✓ Seeded default users (admin & operators)!\n";

    // 2. Seed Master Lokasi
    $lokasi = [
        ['nama_lokasi' => 'Control Room', 'keterangan' => 'Pusat Kendali Sistem & SCADA'],
        ['nama_lokasi' => 'Area Produksi A', 'keterangan' => 'Lini Produksi Utama Unit 1'],
        ['nama_lokasi' => 'Area Produksi B', 'keterangan' => 'Lini Produksi Unit 2'],
        ['nama_lokasi' => 'Gudang & Logistik', 'keterangan' => 'Penyimpanan Bahan & Hasil'],
        ['nama_lokasi' => 'IT & Jaringan', 'keterangan' => 'Server Room & Infrastruktur Jaringan'],
        ['nama_lokasi' => 'Utility', 'keterangan' => 'Power Plant, Genset, & Kompresor']
    ];

    $stmtLokasi = $pdo->prepare("INSERT IGNORE INTO `master_lokasi` (`nama_lokasi`, `keterangan`) VALUES (:nama_lokasi, :keterangan)");
    foreach ($lokasi as $l) {
        $stmtLokasi->execute($l);
    }
    echo "✓ Seeded master lokasi!\n";

    // 3. Seed Master Shift
    $shifts = [
        ['nama_shift' => 'Pagi', 'jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00', 'keterangan' => 'Shift 1 (07:00 - 15:00)'],
        ['nama_shift' => 'Siang', 'jam_mulai' => '15:00:00', 'jam_selesai' => '23:00:00', 'keterangan' => 'Shift 2 (15:00 - 23:00)'],
        ['nama_shift' => 'Malam', 'jam_mulai' => '23:00:00', 'jam_selesai' => '07:00:00', 'keterangan' => 'Shift 3 (23:00 - 07:00)'],
        ['nama_shift' => 'Non-Shift / Lembur', 'jam_mulai' => '08:00:00', 'jam_selesai' => '17:00:00', 'keterangan' => 'Reguler Operasional / Overtime']
    ];

    $stmtShift = $pdo->prepare("INSERT IGNORE INTO `master_shift` (`nama_shift`, `jam_mulai`, `jam_selesai`, `keterangan`) VALUES (:nama_shift, :jam_mulai, :jam_selesai, :keterangan)");
    foreach ($shifts as $s) {
        $stmtShift->execute($s);
    }
    echo "✓ Seeded master shift!\n";

    // 4. Seed 1 Sample Report Matching PDF template if empty
    $countLaporan = $pdo->query("SELECT COUNT(*) FROM `laporan_serah_terima`")->fetchColumn();
    if ($countLaporan == 0) {
        $adminId = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
        $andiId = $pdo->query("SELECT id FROM users WHERE username = 'andi'")->fetchColumn();
        $budiId = $pdo->query("SELECT id FROM users WHERE username = 'budi'")->fetchColumn();
        $shiftPagiId = $pdo->query("SELECT id FROM master_shift WHERE nama_shift = 'Pagi'")->fetchColumn();
        $locCtrlId = $pdo->query("SELECT id FROM master_lokasi WHERE nama_lokasi = 'Control Room'")->fetchColumn();

        $stmtLap = $pdo->prepare("INSERT INTO `laporan_serah_terima` 
            (`nomor_laporan`, `tanggal`, `shift_id`, `lokasi_id`, `jam_serah_terima`, `kondisi_operasional`, `petugas_menyerahkan_id`, `petugas_menerima_id`, `catatan_penting`, `check_lisan`, `check_tiket`, `check_risiko`, `check_kunci`, `status_validasi`, `created_by`)
            VALUES
            ('OMIT-20261124-001', '2026-11-24', :shift_id, :lokasi_id, '15:00:00', 'Normal', :petugas_menyerahkan_id, :petugas_menerima_id, 'Semua parameter operasional di ruang kontrol berjalan normal. Periksa suhu pendingin ruang server pukul 19:00.', 'Ya', 'Ya', 'Ya', 'Ya', 'TERVALIDASI', :created_by)");
        
        $stmtLap->execute([
            'shift_id' => $shiftPagiId,
            'lokasi_id' => $locCtrlId,
            'petugas_menyerahkan_id' => $andiId,
            'petugas_menerima_id' => $budiId,
            'created_by' => $andiId
        ]);
        $laporanId = $pdo->lastInsertId();

        // Seed Sample Pekerjaan
        $stmtPekerjaan = $pdo->prepare("INSERT INTO `laporan_pekerjaan` 
            (`laporan_id`, `no_tiket`, `pekerjaan_masalah`, `prioritas`, `status`, `kondisi_tindak_lanjut`, `pic`, `target_selesai`, `urutan`)
            VALUES
            (:laporan_id, 'TK-0192', 'Kalibrasi Sensor Tekanan Pompa 2B', 'Tinggi / Kritis', 'Berjalan', 'Sudah dibersihkan, menunggu pembacaan stabil selama 30 menit oleh teknisi shift siang.', 'Andi Pratama', '17:00 WIB', 1),
            (:laporan_id, 'TK-0193', 'Pengecekan Rutin Log Server SCADA', 'Sedang', 'Selesai', 'Backup database log harian selesai tanpa error.', 'Eko Saputra', '14:30 WIB', 2),
            (:laporan_id, 'TK-0194', 'Inspeksi Suhu Ruang Genset Cadangan', 'Rendah', 'Belum Mulai', 'Jadwal inspeksi berkala shift siang/malam.', 'Fajar Nugroho', '20:00 WIB', 3)");
        $stmtPekerjaan->execute(['laporan_id' => $laporanId]);

        echo "✓ Seeded sample report with initial tasks!\n";
    }

    echo "=== Migration & Seeding Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
