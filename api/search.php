<?php
// api/search.php - Live AJAX Quick Search (Reports, Tickets, Locations, Staff)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'results' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$like = "%{$q}%";

try {
    // Search matching reports + tickets (unique placeholders for native PDO)
    $stmt = $pdo->prepare("
        SELECT DISTINCT l.id, l.nomor_laporan, l.tanggal, l.status_validasi,
               m.nama_lokasi, s.nama_shift, u1.nama_lengkap AS nama_menyerahkan
        FROM laporan_serah_terima l
        JOIN master_lokasi m ON l.lokasi_id = m.id
        JOIN master_shift s ON l.shift_id = s.id
        JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
        LEFT JOIN laporan_pekerjaan p ON p.laporan_id = l.id
        WHERE l.nomor_laporan LIKE :q1
           OR m.nama_lokasi LIKE :q2
           OR u1.nama_lengkap LIKE :q3
           OR p.no_tiket LIKE :q4
           OR p.pekerjaan_masalah LIKE :q5
        ORDER BY l.tanggal DESC, l.id DESC
        LIMIT 8
    ");
    $stmt->execute([
        'q1' => $like,
        'q2' => $like,
        'q3' => $like,
        'q4' => $like,
        'q5' => $like,
    ]);
    $rows = $stmt->fetchAll();

    $results = array_map(function ($r) {
        return [
            'id'        => (int)$r['id'],
            'nomor'     => $r['nomor_laporan'],
            'tanggal'   => format_tanggal($r['tanggal']),
            'lokasi'    => $r['nama_lokasi'],
            'shift'     => $r['nama_shift'],
            'petugas'   => $r['nama_menyerahkan'],
            'status'    => $r['status_validasi'],
            'url'       => BASE_URL . 'laporan/detail?id=' . (int)$r['id'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'results' => []]);
}
