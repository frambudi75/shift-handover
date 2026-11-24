<?php
// includes/functions.php - Helper Functions

require_once __DIR__ . '/../config/app.php';

function sanitize(?string $data): string {
    return htmlspecialchars(trim((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['flashes']) || !is_array($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = [
        'type'    => $type,
        'message' => $message,
    ];
    // Legacy support
    $_SESSION['flash_' . $type] = $message;
}

function flash_get(): array {
    $flashes = [];
    if (!empty($_SESSION['flashes']) && is_array($_SESSION['flashes'])) {
        $flashes = $_SESSION['flashes'];
        unset($_SESSION['flashes']);
        foreach (['success', 'error', 'warning', 'info'] as $type) {
            unset($_SESSION['flash_' . $type]);
        }
        return $flashes;
    }
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (isset($_SESSION['flash_' . $type])) {
            $flashes[] = [
                'type'    => $type,
                'message' => $_SESSION['flash_' . $type]
            ];
            unset($_SESSION['flash_' . $type]);
        }
    }
    return $flashes;
}

function format_tanggal(?string $dateStr): string {
    if (!$dateStr) return '-';
    $time = strtotime($dateStr);
    return date('d/m/Y', $time);
}

function format_jam(?string $timeStr): string {
    if (!$timeStr) return '-';
    return substr($timeStr, 0, 5);
}

function generate_nomor_laporan(PDO $pdo): string {
    $today = date('Ymd');
    $prefix = "OMIT-{$today}-";
    $stmt = $pdo->prepare("SELECT nomor_laporan FROM laporan_serah_terima WHERE nomor_laporan LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => "{$prefix}%"]);
    $last = $stmt->fetchColumn();

    if ($last) {
        $lastSeq = (int)substr($last, -3);
        $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '001';
    }
    return $prefix . $nextSeq;
}

function compress_and_save_image(string $sourcePath, string $destPath, int $quality = 80, int $maxWidth = 1600): bool {
    if (!extension_loaded('gd')) {
        return copy($sourcePath, $destPath);
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        return copy($sourcePath, $destPath);
    }

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null;
            break;
        default:
            $image = null;
    }

    if (!$image) {
        return copy($sourcePath, $destPath);
    }

    // Auto-rotate if EXIF orientation exists
    if (function_exists('exif_read_data') && ($mime === 'image/jpeg')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $rotated = @imagerotate($image, 180, 0);
                    if ($rotated) { unset($image); $image = $rotated; }
                    break;
                case 6:
                    $rotated = @imagerotate($image, -90, 0);
                    if ($rotated) { unset($image); $image = $rotated; }
                    break;
                case 8:
                    $rotated = @imagerotate($image, 90, 0);
                    if ($rotated) { unset($image); $image = $rotated; }
                    break;
            }
        }
    }

    $origW = imagesx($image);
    $origH = imagesy($image);

    // Calculate new dimensions if exceeds maxWidth
    if ($origW > $maxWidth) {
        $newW = $maxWidth;
        $newH = (int)round(($origH / $origW) * $newW);
        $resized = imagecreatetruecolor($newW, $newH);

        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        unset($image);
        $image = $resized;
    }

    // Save as compressed JPG/PNG
    $saved = false;
    if ($mime === 'image/png') {
        $pngQuality = (int)round((100 - $quality) / 10);
        $saved = @imagepng($image, $destPath, max(0, min(9, $pngQuality)));
    } else {
        $saved = @imagejpeg($image, $destPath, max(30, min(100, $quality)));
    }

    unset($image);
    return $saved ?: copy($sourcePath, $destPath);
}

function upload_foto(array $file, string $prefix = 'foto_'): ?string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $destination = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    // Read dynamic compression settings from app_settings
    $enabled = get_setting('compress_enabled', '1') === '1';
    $quality = (int)get_setting('compress_quality', '80');
    $maxWidth = (int)get_setting('compress_max_width', '1600');

    if ($enabled) {
        if (compress_and_save_image($file['tmp_name'], $destination, $quality, $maxWidth)) {
            return $filename;
        }
    } else {
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $filename;
        }
    }
    return null;
}

function get_user_avatar_url(?string $foto): ?string {
    if (empty($foto)) {
        return null;
    }
    $fullPath = UPLOAD_DIR . $foto;
    if (file_exists($fullPath)) {
        return BASE_URL . 'uploads/' . $foto;
    }
    return null;
}

function upload_avatar(array $file): ?string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        return null;
    }

    $dir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'avatars/avatar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;

    if (compress_and_save_image($file['tmp_name'], $destination, 85, 500)) {
        return $filename;
    }
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }
    return null;
}

function process_signature(string $mode, ?array $file, ?string $base64Data, string $prefix = 'ttd_'): ?string {
    $sigDir = UPLOAD_DIR . 'signatures/';
    if (!is_dir($sigDir)) {
        mkdir($sigDir, 0777, true);
    }

    // Mode 1: Upload File (TTD Basah)
    if ($mode === 'upload' && $file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (in_array($mime, $allowedTypes)) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'signatures/' . $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (compress_and_save_image($file['tmp_name'], UPLOAD_DIR . $filename, 85, 800)) {
                return $filename;
            }
        }
    }

    // Mode 2 & 3: Base64 Data URL (Barcode QR Badge or Canvas Drawing)
    if (!empty($base64Data) && str_starts_with($base64Data, 'data:image')) {
        $parts = explode(',', $base64Data);
        if (count($parts) === 2) {
            $decoded = base64_decode($parts[1]);
            if ($decoded !== false) {
                $filename = 'signatures/' . $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.png';
                file_put_contents(UPLOAD_DIR . $filename, $decoded);
                return $filename;
            }
        }
    }

    return null;
}

function get_signature_url(?string $sig): ?string {
    if (empty($sig)) {
        return null;
    }
    if (file_exists(UPLOAD_DIR . $sig)) {
        return BASE_URL . 'uploads/' . $sig;
    }
    return null;
}

function badge_prioritas(string $prioritas): string {
    if ($prioritas === 'Tinggi / Kritis') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300"><i class="ph-bold ph-warning-circle mr-1"></i> Tinggi / Kritis</span>';
    } elseif ($prioritas === 'Sedang') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"><i class="ph-bold ph-clock mr-1"></i> Sedang</span>';
    } else {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"><i class="ph-bold ph-arrow-down mr-1"></i> Rendah</span>';
    }
}

function badge_status_pekerjaan(string $status): string {
    switch ($status) {
        case 'Selesai':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"><i class="ph-bold ph-check-circle mr-1"></i> Selesai</span>';
        case 'Berjalan':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"><i class="ph-bold ph-arrows-clockwise mr-1"></i> Berjalan</span>';
        case 'Tertunda':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"><i class="ph-bold ph-pause-circle mr-1"></i> Tertunda</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"><i class="ph-bold ph-hourglass-simple mr-1"></i> Belum Mulai</span>';
    }
}

function get_master_shift(bool $onlyActive = true): array {
    global $pdo;
    if (!isset($pdo)) return [];
    $cacheKey = $onlyActive ? 'master_shift:active' : 'master_shift:all';
    return cache_remember($cacheKey, 1800, function() use ($pdo, $onlyActive) {
        $sql = "SELECT * FROM master_shift";
        if ($onlyActive) {
            $sql .= " WHERE status = 'aktif'";
        }
        $sql .= " ORDER BY id ASC";
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    });
}

function get_master_lokasi(bool $onlyActive = true): array {
    global $pdo;
    if (!isset($pdo)) return [];
    $cacheKey = $onlyActive ? 'master_lokasi:active' : 'master_lokasi:all';
    return cache_remember($cacheKey, 1800, function() use ($pdo, $onlyActive) {
        $sql = "SELECT * FROM master_lokasi";
        if ($onlyActive) {
            $sql .= " WHERE status = 'aktif'";
        }
        $sql .= " ORDER BY nama_lokasi ASC";
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    });
}

function get_master_kondisi(bool $onlyActive = true): array {
    global $pdo;
    if (!isset($pdo)) return [];
    $cacheKey = $onlyActive ? 'master_kondisi:active' : 'master_kondisi:all';
    return cache_remember($cacheKey, 1800, function() use ($pdo, $onlyActive) {
        try {
            $sql = "SELECT * FROM master_kondisi";
            if ($onlyActive) {
                $sql .= " WHERE status = 'aktif'";
            }
            $sql .= " ORDER BY urutan ASC, id ASC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [
                ['id' => 1, 'nama_kondisi' => 'Normal', 'badge_color' => 'emerald', 'is_default' => 1],
                ['id' => 2, 'nama_kondisi' => 'Perlu Pantauan', 'badge_color' => 'amber', 'is_default' => 0],
                ['id' => 3, 'nama_kondisi' => 'Ada Gangguan', 'badge_color' => 'rose', 'is_default' => 0],
            ];
        }
    });
}

function badge_kondisi_operasional(string $kondisi): string {
    static $kondisiCache = null;
    if ($kondisiCache === null) {
        global $pdo;
        $kondisiCache = [];
        if (isset($pdo)) {
            try {
                $rows = $pdo->query("SELECT nama_kondisi, badge_color FROM master_kondisi")->fetchAll(PDO::FETCH_KEY_PAIR);
                $kondisiCache = $rows ?: [];
            } catch (Exception $e) {}
        }
    }

    $color = $kondisiCache[$kondisi] ?? null;
    if (!$color) {
        if ($kondisi === 'Normal') $color = 'emerald';
        elseif ($kondisi === 'Perlu Pantauan') $color = 'amber';
        elseif ($kondisi === 'Ada Gangguan') $color = 'rose';
        else $color = 'indigo';
    }

    $colorMap = [
        'emerald' => ['bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300', 'icon' => 'ph-check-circle'],
        'amber'   => ['bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300', 'icon' => 'ph-eye'],
        'rose'    => ['bg' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300', 'icon' => 'ph-warning'],
        'indigo'  => ['bg' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300', 'icon' => 'ph-info'],
        'purple'  => ['bg' => 'bg-purple-100 text-[#6558f5] dark:bg-purple-950/60 dark:text-purple-300', 'icon' => 'ph-sparkle'],
        'blue'    => ['bg' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300', 'icon' => 'ph-gear'],
        'slate'   => ['bg' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', 'icon' => 'ph-circle']
    ];

    $cfg = $colorMap[$color] ?? $colorMap['indigo'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' . $cfg['bg'] . '"><i class="ph-bold ' . $cfg['icon'] . ' mr-1"></i> ' . sanitize($kondisi) . '</span>';
}

function badge_role(string $role): string {
    switch ($role) {
        case 'superadmin':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800"><i class="ph-fill ph-crown mr-1"></i> SUPERADMIN</span>';
        case 'admin':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 text-[#6558f5] dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800"><i class="ph-fill ph-shield-check mr-1"></i> ADMIN</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800"><i class="ph-fill ph-user mr-1"></i> USER</span>';
    }
}

function badge_status_validasi(string $status): string {
    if ($status === 'TERVALIDASI') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-500 text-white shadow-sm"><i class="ph-bold ph-shield-check mr-1 text-sm"></i> TERVALIDASI</span>';
    } elseif ($status === 'MENUNGGU VALIDASI') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-500 text-white shadow-sm"><i class="ph-bold ph-hourglass-medium mr-1 text-sm"></i> MENUNGGU PENERIMA</span>';
    } else {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-400 text-white shadow-sm"><i class="ph-bold ph-pencil-simple mr-1 text-sm"></i> DRAFT</span>';
    }
}
