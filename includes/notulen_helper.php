<?php
// includes/notulen_helper.php - Auto-Migrating Meeting & Briefing Minutes Helper

/**
 * Ensure notulen_meeting & notulen_action_items tables exist.
 */
function ensure_notulen_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notulen_meeting` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `nomor_notulen` varchar(50) NOT NULL,
              `judul` varchar(200) NOT NULL,
              `jenis` enum('Briefing Shift','Meeting Operasional','Evaluasi','Koordinasi') NOT NULL DEFAULT 'Briefing Shift',
              `tanggal` date NOT NULL,
              `jam_mulai` time NOT NULL,
              `jam_selesai` time DEFAULT NULL,
              `lokasi` varchar(120) NOT NULL,
              `pimpinan_rapat` varchar(100) NOT NULL,
              `peserta_ids` text DEFAULT NULL,
              `peserta_eksternal` varchar(255) DEFAULT NULL,
              `agenda` text DEFAULT NULL,
              `pembahasan` longtext DEFAULT NULL,
              `keputusan` text DEFAULT NULL,
              `lampiran_foto` varchar(255) DEFAULT NULL,
              `status` enum('DRAFT','FINAL') NOT NULL DEFAULT 'DRAFT',
              `notified_at` datetime DEFAULT NULL,
              `created_by` int(11) NOT NULL,
              `updated_by` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_nomor_notulen` (`nomor_notulen`),
              KEY `idx_notulen_tanggal` (`tanggal`),
              KEY `idx_notulen_status` (`status`),
              KEY `idx_notulen_jenis` (`jenis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notulen_action_items` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `notulen_id` int(11) NOT NULL,
              `uraian_tugas` text NOT NULL,
              `pic` varchar(100) DEFAULT NULL,
              `target_tanggal` date DEFAULT NULL,
              `status` enum('Belum Mulai','Berjalan','Selesai') NOT NULL DEFAULT 'Belum Mulai',
              `urutan` int(11) NOT NULL DEFAULT 1,
              PRIMARY KEY (`id`),
              KEY `idx_action_notulen` (`notulen_id`),
              CONSTRAINT `fk_action_notulen` FOREIGN KEY (`notulen_id`) REFERENCES `notulen_meeting` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
    }
}

/**
 * Generate sequential document number: MOM-YYYYMMDD-001
 */
function generate_nomor_notulen(PDO $pdo): string {
    ensure_notulen_tables($pdo);
    $today = date('Ymd');
    $prefix = "MOM-{$today}-";
    $stmt = $pdo->prepare("SELECT nomor_notulen FROM notulen_meeting WHERE nomor_notulen LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Status badge helper for Notulen.
 */
function badge_status_notulen(string $status): string {
    if ($status === 'FINAL') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500 text-white shadow-sm"><i class="ph-bold ph-check-circle mr-1"></i>FINAL</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-400 text-white shadow-sm"><i class="ph-bold ph-pencil-simple mr-1"></i>DRAFT</span>';
}

/**
 * Notify internal participants via Bell Notification when Notulen is finalized.
 */
function notify_notulen_participants(PDO $pdo, int $notulenId, array $participantIds, string $nomor, string $judul, int $actorUserId): void {
    if (empty($participantIds)) return;
    require_once __DIR__ . '/bell_helper.php';
    $linkUrl = BASE_URL . 'notulen/detail?id=' . $notulenId;
    $title   = "Notulen {$nomor} Difinalisasi";
    $msg     = "Anda tercatat sebagai peserta pada \"{$judul}\".";
    foreach (array_unique(array_map('intval', $participantIds)) as $uid) {
        if ($uid > 0 && $uid !== $actorUserId) {
            bell_create_notification($pdo, $uid, 'notulen', $title, $msg, $linkUrl);
        }
    }
}
