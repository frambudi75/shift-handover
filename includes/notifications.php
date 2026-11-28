<?php
// includes/notifications.php - SMTP Email & WhatsApp Notification Engine
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Format phone number to international WhatsApp standard (e.g. 0812... -> 62812...)
 */
function format_phone_wa(?string $phone): string {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    } elseif (str_starts_with($phone, '8')) {
        $phone = '62' . $phone;
    }
    return $phone;
}

/**
 * Dapatkan URL absolut lengkap (termasuk protokol dan domain/host) agar link dapat diklik di WhatsApp
 */
function get_full_web_url(string $path = ''): string {
    $path = ltrim($path, '/');
    $baseUrl = BASE_URL; // e.g. /serah-terima/ or /
    
    $customDomain = get_setting('app_url', get_setting('app_base_url_full', ''));
    if (!empty($customDomain)) {
        return rtrim($customDomain, '/') . '/' . $path;
    }

    if (isset($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "{$protocol}://{$host}{$baseUrl}{$path}";
    }

    return "http://localhost:2032/{$path}";
}

/**
 * Generate Click-to-Chat WhatsApp URL
 * If phone is empty, generates universal share URL (opens WhatsApp contact/group picker)
 */
function get_whatsapp_url(?string $phone, string $message): string {
    $formatted = format_phone_wa($phone);
    if (empty($formatted)) {
        return "https://api.whatsapp.com/send?text=" . rawurlencode($message);
    }
    return "https://wa.me/{$formatted}?text=" . rawurlencode($message);
}

/**
 * Bangun pesan WhatsApp laporan serah terima yang rapi, profesional, dan elegan
 * tanpa tumpukan emoji warna-warni yang mengganggu tampilan resmi.
 * 
 * @param array $report Data laporan serah terima
 * @param array $tasks Daftar rincian pekerjaan
 * @param bool $includeLink Apakah menyertakan tautan dokumen publik (default: false sesuai kebutuhan)
 */
function build_handover_whatsapp_message(array $report, array $tasks, bool $includeLink = false): string {
    $appName = get_app_name();
    $repId   = (int)($report['id'] ?? 0);
    $nomorLaporan = $report['nomor_laporan'] ?? '-';
    $shareToken   = get_report_share_token($repId, $nomorLaporan);
    $docUrl  = get_full_web_url("laporan/share?id={$repId}&token={$shareToken}");
    $tglFmt  = format_tanggal($report['tanggal'] ?? null);
    $jamFmt  = format_jam($report['jam_serah_terima'] ?? null);
    $kondisi = $report['kondisi_operasional'] ?? 'Normal';
    $status  = $report['status_validasi'] ?? 'MENUNGGU VALIDASI';

    $petugasMenyerahkan = $report['nama_menyerahkan'] ?? 'Petugas';
    $petugasMenerima    = $report['nama_menerima'] ?? 'Menunggu Persetujuan';
    $nomorLaporan       = $report['nomor_laporan'] ?? '-';
    $namaLokasi         = $report['nama_lokasi'] ?? '-';

    // Ambil jam shift jika tersedia
    if (empty($report['jam_mulai']) && !empty($report['shift_id']) && function_exists('get_master_shift')) {
        $allShifts = get_master_shift(false);
        foreach ($allShifts as $sh) {
            if ($sh['id'] == $report['shift_id']) {
                $report['jam_mulai'] = $sh['jam_mulai'];
                $report['jam_selesai'] = $sh['jam_selesai'];
                break;
            }
        }
    }

    $jamShift = (!empty($report['jam_mulai']) && !empty($report['jam_selesai']))
        ? (format_jam($report['jam_mulai']) . ' - ' . format_jam($report['jam_selesai']) . ' WIB')
        : ($report['nama_shift'] ?? '-');

    $divider = "─────────────────────────────";

    $msg = "*LAPORAN SERAH TERIMA SHIFT*\n"
         . "*{$appName}*\n"
         . "{$divider}\n"
         . "*No. Dokumen* : {$nomorLaporan}\n"
         . "*Waktu*       : {$tglFmt} ({$jamFmt} WIB)\n"
         . "*Shift*       : {$jamShift}\n"
         . "*Lokasi/Unit* : {$namaLokasi}\n"
         . "*Kondisi*     : {$kondisi}\n";

    if (!empty($status) && !in_array(strtoupper(trim((string)$status)), ['TERVALIDASI', 'TERVALIDASI (LENGKAP)'])) {
        $msg .= "*Status*      : {$status}\n";
    }

    $msg .= "\n"
         . "*Personil:*\n"
         . "• Menyerahkan : {$petugasMenyerahkan}\n"
         . "• Menerima    : {$petugasMenerima}\n"
         . "{$divider}\n";

    if (!empty($report['catatan_penting'])) {
        $catatan = trim($report['catatan_penting']);
        $msg .= "*CATATAN PENTING:*\n"
              . "{$catatan}\n"
              . "{$divider}\n";
    }

    // Rincian Pekerjaan Shift Hari Ini
    $msg .= "*RINCIAN PEKERJAAN:*\n";
    $outstanding = [];
    if (empty($tasks)) {
        $msg .= "_Tidak ada rincian pekerjaan tercatat pada shift ini._\n";
    } else {
        $i = 1;
        foreach ($tasks as $t) {
            $ticketStr = !empty($t['no_tiket']) ? "[{$t['no_tiket']}] " : "";

            $msg .= "{$i}. {$ticketStr}{$t['pekerjaan_masalah']}\n"
                  . "   • Status : {$t['status']}\n"
                  . "   • PIC : {$t['pic']}" . (!empty($t['target_selesai']) ? " (Target: {$t['target_selesai']})" : "") . "\n";
            $tindakLanjut = trim((string)($t['kondisi_tindak_lanjut'] ?? ''));
            $msg .= "   • Tindak Lanjut: " . ($tindakLanjut !== '' ? $tindakLanjut : "-") . "\n";
            $i++;

            if ($t['status'] !== 'Selesai') {
                $outstanding[] = $t;
            }
        }
    }

    // Bagian Pekerjaan Belum Selesai (Outstanding)
    $msg .= "{$divider}\n"
          . "*STATUS TINDAK LANJUT / OUTSTANDING:*\n";
    if (empty($outstanding)) {
        $msg .= "Seluruh pekerjaan pada shift ini telah selesai (Nol Outstanding).\n";
    } else {
        $totalOut = count($outstanding);
        $msg .= "Terdapat {$totalOut} pekerjaan belum selesai yang dilanjutkan shift berikutnya:\n";

        $j = 1;
        foreach ($outstanding as $ot) {
            $tNo = !empty($ot['no_tiket']) ? "[{$ot['no_tiket']}] " : "";
            $msg .= "  {$j}. {$tNo}{$ot['pekerjaan_masalah']} (PIC: {$ot['pic']})\n";
            $j++;
        }
    }
    $msg .= "{$divider}\n";

    // Sertakan tautan dokumen publik jika diaktifkan (opsional, default: tidak disertakan)
    if ($includeLink) {
        $msg .= "*Tautan Dokumen & Pengesahan:*\n"
              . "{$docUrl}\n\n"
              . "_{$appName} — Sistem Berita Acara Operasional_\n"
              . "{$divider}\n";
    }

    return $msg;
}

/**
 * Send WhatsApp Message via API (Fonnte, Wablas, or Generic Webhook)
 */
function send_whatsapp_api(string $toPhone, string $message): array {
    $enabled  = (int)get_setting('wa_enabled', 1);
    $provider = get_setting('wa_provider', 'direct');
    $token    = get_setting('wa_api_token', '');
    $apiUrl   = get_setting('wa_api_url', 'https://api.fonnte.com/send');

    $cleanPhone = format_phone_wa($toPhone);
    if (empty($cleanPhone)) {
        return ['success' => false, 'message' => 'Nomor WhatsApp tujuan kosong atau tidak valid'];
    }

    if (!$enabled || $provider === 'direct' || empty($token)) {
        // Direct mode: no API configured, uses click-to-chat link
        return [
            'success' => true,
            'mode'    => 'direct',
            'url'     => get_whatsapp_url($toPhone, $message),
            'message' => 'Pesan WhatsApp siap dikirimkan melalui tautan wa.me'
        ];
    }

    // Call API via cURL
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'Ekstensi PHP cURL tidak aktif di server'];
    }

    $ch = curl_init();
    if ($provider === 'fonnte') {
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl ?: 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'target' => $cleanPhone,
                'message' => $message,
                'countryCode' => '62'
            ],
            CURLOPT_HTTPHEADER => [
                "Authorization: {$token}"
            ],
            CURLOPT_TIMEOUT => 12
        ]);
    } else {
        // Generic JSON Webhook API
        $payload = json_encode([
            'phone'   => $cleanPhone,
            'target'  => $cleanPhone,
            'message' => $message,
        ]);
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer {$token}"
            ],
            CURLOPT_TIMEOUT => 12
        ]);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($error) {
        return ['success' => false, 'message' => "Gagal menghubungi API WhatsApp: {$error}"];
    }

    return [
        'success'   => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response'  => $response,
        'message'   => "Pesan WhatsApp terkirim ke {$cleanPhone}"
    ];
}

/**
 * Native Socket-based SMTP Mailer (RFC 5321 Compliant, SSL/TLS, no Composer required)
 */
function send_smtp_email(?string $toEmail, ?string $toName = null, string $subject = '', string $htmlBody = '', ?string $attachmentPath = null): array {
    $enabled   = (int)get_setting('smtp_enabled', 0);
    $host      = get_setting('smtp_host', 'smtp.gmail.com');
    $port      = (int)get_setting('smtp_port', 587);
    $username  = trim(get_setting('smtp_user', ''));
    $password  = trim(str_replace(' ', '', get_setting('smtp_pass', ''))); // strip accidental spaces from 16-character app passwords
    $crypto    = strtolower(get_setting('smtp_crypto', 'tls')); // 'ssl', 'tls', or 'none'
    $fromName  = get_setting('smtp_from_name', get_app_name());
    $fromEmail = get_setting('smtp_from_email', 'no-reply@operasional.local');

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Alamat email penerima tidak valid.'];
    }

    if (!$enabled || empty($host)) {
        return ['success' => false, 'message' => 'Layanan SMTP dinonaktifkan di Pengaturan Aplikasi.'];
    }

    // 6-second timeout prevents user interface hanging on slow/unreachable networks
    $timeout = 6;
    $socketHost = ($crypto === 'ssl') ? "ssl://{$host}" : $host;
    
    $socket = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'message' => "Koneksi ke SMTP ({$host}:{$port}) gagal: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $sendCommand = function(string $cmd, $expectCode = 250, string $logLabel = '') use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        $res = $readResponse();
        $code = (int)substr($res, 0, 3);
        if ($expectCode !== null && $code !== (int)$expectCode) {
            $displayCmd = ($logLabel === 'PASSWORD') ? '[AUTH PASSWORD MASKED]' : (($logLabel === 'USERNAME') ? '[AUTH USERNAME]' : $cmd);
            if (str_contains($res, '535') || str_contains($res, 'BadCredentials') || str_contains($res, 'Username and Password not accepted')) {
                throw new Exception("Autentikasi Gmail Ditolak (535 Bad Credentials). Jika menggunakan Gmail (smtp.gmail.com), Anda WAJIB menggunakan 'Sandi Aplikasi' (App Password 16 karakter dari https://myaccount.google.com/apppasswords) dan mengaktifkan Verifikasi 2 Langkah, BUKAN password biasa akun Google Anda.");
            }
            throw new Exception("SMTP Error pada perintah {$displayCmd}: {$res}");
        }
        return $res;
    };

    try {
        $readResponse(); // Greeting

        $localhost = gethostname() ?: 'localhost';
        $sendCommand("EHLO {$localhost}");

        // STARTTLS negotiation if TLS specified and port is typically 587
        if ($crypto === 'tls') {
            $sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new Exception("Gagal menegosiasikan enkripsi TLS dengan server SMTP.");
            }
            $sendCommand("EHLO {$localhost}");
        }

        // Authentication
        if (!empty($username) && !empty($password)) {
            $sendCommand("AUTH LOGIN", 334);
            $sendCommand(base64_encode($username), 334, 'USERNAME');
            $sendCommand(base64_encode($password), 235, 'PASSWORD');
        }

        // Sender & Recipient
        $sendCommand("MAIL FROM: <{$fromEmail}>", 250);
        $sendCommand("RCPT TO: <{$toEmail}>", 250);

        // DATA
        $sendCommand("DATA", 354);

        // Construct MIME Headers
        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $headers[] = "To: =?UTF-8?B?" . base64_encode($toName ?: 'Rekan Operasional') . "?= <{$toEmail}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "Date: " . date('r');
        $headers[] = "MIME-Version: 1.0";

        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $boundary = "==Multipart_Boundary_x" . md5(microtime()) . "x";
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

            $attachName = basename($attachmentPath);
            $attachData = chunk_split(base64_encode(file_get_contents($attachmentPath)));

            $bodyParts = [];
            $bodyParts[] = "--{$boundary}";
            $bodyParts[] = "Content-Type: text/html; charset=UTF-8";
            $bodyParts[] = "Content-Transfer-Encoding: 8bit\r\n";
            $bodyParts[] = $htmlBody;
            $bodyParts[] = "--{$boundary}";
            $bodyParts[] = "Content-Type: application/octet-stream; name=\"{$attachName}\"";
            $bodyParts[] = "Content-Transfer-Encoding: base64";
            $bodyParts[] = "Content-Disposition: attachment; filename=\"{$attachName}\"\r\n";
            $bodyParts[] = $attachData;
            $bodyParts[] = "--{$boundary}--";

            $fullData = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyParts) . "\r\n.";
        } else {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
            $fullData = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        }
        $sendCommand($fullData, 250);

        $sendCommand("QUIT", 221);
        fclose($socket);

        return ['success' => true, 'message' => "Email berhasil dikirim ke {$toEmail}"];
    } catch (Exception $e) {
        if ($socket) @fclose($socket);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Trigger Notification: Laporan Serah Terima Baru Dibuat (Menunggu Approval)
 */
function notify_handover_created(int $laporanId, PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT l.*, s.nama_shift, s.jam_mulai, s.jam_selesai, m.nama_lokasi,
               u1.nama_lengkap AS nama_menyerahkan, u1.email AS email_menyerahkan, u1.no_wa AS wa_menyerahkan,
               u2.nama_lengkap AS nama_menerima, u2.email AS email_menerima, u2.no_wa AS wa_menerima
        FROM laporan_serah_terima l
        JOIN master_shift s ON l.shift_id = s.id
        JOIN master_lokasi m ON l.lokasi_id = m.id
        JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
        LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
        WHERE l.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $laporanId]);
    $rep = $stmt->fetch();

    if (!$rep) {
        return ['success' => false, 'message' => 'Laporan tidak ditemukan'];
    }

    $appName = get_app_name();
    $docUrl = BASE_URL . "laporan/detail?id={$rep['id']}";
    $tanggalFmt = format_tanggal($rep['tanggal']);
    $jamFmt = format_jam($rep['jam_serah_terima']);

    // Fetch tasks for complete job details and outstanding items
    $stmtT = $pdo->prepare("SELECT * FROM laporan_pekerjaan WHERE laporan_id = :id ORDER BY urutan ASC, id ASC");
    $stmtT->execute(['id' => $laporanId]);
    $tasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // 1. WhatsApp Message Text with Full Task Details & Outstanding
    $waText = build_handover_whatsapp_message($rep, $tasks);

    $waResult = null;
    if (!empty($rep['wa_menerima'])) {
        $waResult = send_whatsapp_api($rep['wa_menerima'], $waText);
    }

    // 2. HTML Email Body (NoteFlow Branded Design)
    $emailSubject = "[APPROVAL] Serah Terima Shift {$rep['nomor_laporan']} - {$rep['nama_lokasi']}";
    $htmlEmail = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f7f7fb; color: #181b2c; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 30px rgba(45,40,105,0.08); overflow: hidden; border: 1px solid #ebebf7; }
        .header { background: linear-gradient(135deg, #6558f5, #5143eb); padding: 32px 24px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { padding: 28px 24px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0fa; font-size: 13px; }
        .info-table td.label { font-weight: bold; color: #64748b; width: 40%; }
        .info-table td.value { font-weight: 600; color: #181b2c; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: bold; }
        .btn { display: block; width: fit-content; margin: 28px auto 10px; padding: 12px 28px; background: #6558f5; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 14px; border-radius: 14px; box-shadow: 0 6px 18px rgba(101,88,245,0.35); text-align: center; }
        .footer { padding: 20px 24px; background: #fcfcfe; border-top: 1px solid #f0f0fa; text-align: center; font-size: 11px; color: #94a3b8; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='header'>
          <h1>NOTIFIKASI SERAH TERIMA SHIFT</h1>
          <p>{$appName} — Permintaan Validasi Penerimaan</p>
        </div>
        <div class='body'>
          <p style='font-size: 14px; margin-top: 0;'>Yth. <strong>{$rep['nama_menerima']}</strong>,</p>
          <p style='font-size: 13px; color: #475569; line-height: 1.6;'>
            Rekan kerja Anda, <strong>{$rep['nama_menyerahkan']}</strong>, telah membuat formulir serah terima shift operasional dan memerlukan <strong>pengesahan tanda tangan penerimaan</strong> dari Anda.
          </p>

          <table class='info-table'>
            <tr><td class='label'>Nomor Laporan</td><td class='value'><strong>{$rep['nomor_laporan']}</strong></td></tr>
            <tr><td class='label'>Tanggal &amp; Waktu</td><td class='value'>{$tanggalFmt} • {$jamFmt} WIB</td></tr>
            <tr><td class='label'>Shift Kerja</td><td class='value'>{$rep['nama_shift']}</td></tr>
            <tr><td class='label'>Lokasi / Unit</td><td class='value'>{$rep['nama_lokasi']}</td></tr>
            <tr><td class='label'>Kondisi Operasional</td><td class='value'>{$rep['kondisi_operasional']}</td></tr>
            <tr><td class='label'>Status Pengesahan</td><td class='value'><span class='badge'>MENUNGGU VALIDASI</span></td></tr>
          </table>

          <a href='{$docUrl}' class='btn'>Buka Dokumen &amp; Berikan Tanda Tangan</a>
        </div>
        <div class='footer'>
          Email ini dikirimkan otomatis oleh sistem {$appName}. Harap tidak membalas email ini.
        </div>
      </div>
    </body>
    </html>
    ";

    $emailResult = null;
    if (!empty($rep['email_menerima'])) {
        $emailResult = send_smtp_email($rep['email_menerima'], $rep['nama_menerima'], $emailSubject, $htmlEmail);
    }

    // In-App Bell Notification to Receiver
    if (!empty($rep['petugas_menerima_id'])) {
        require_once __DIR__ . '/bell_helper.php';
        bell_create_notification(
            $pdo,
            (int)$rep['petugas_menerima_id'],
            'handover',
            "Serah Terima Baru: {$rep['nomor_laporan']}",
            "{$rep['nama_menyerahkan']} menyerahkan shift {$rep['nama_shift']} di {$rep['nama_lokasi']}. Klik untuk tinjau & validasi.",
            "laporan/detail?id={$rep['id']}"
        );
    }

    return [
        'success'      => true,
        'email_result' => $emailResult,
        'wa_result'    => $waResult,
        'wa_url'       => !empty($rep['wa_menerima']) ? get_whatsapp_url($rep['wa_menerima'], $waText) : ''
    ];
}

/**
 * Trigger Notification: Laporan Berhasil Divalidasi / Disahkan oleh Shift Penerima
 */
function notify_handover_approved(int $laporanId, PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT l.*, s.nama_shift, s.jam_mulai, s.jam_selesai, m.nama_lokasi,
               u1.nama_lengkap AS nama_menyerahkan, u1.email AS email_menyerahkan, u1.no_wa AS wa_menyerahkan,
               u2.nama_lengkap AS nama_menerima, u2.email AS email_menerima, u2.no_wa AS wa_menerima
        FROM laporan_serah_terima l
        JOIN master_shift s ON l.shift_id = s.id
        JOIN master_lokasi m ON l.lokasi_id = m.id
        JOIN users u1 ON l.petugas_menyerahkan_id = u1.id
        LEFT JOIN users u2 ON l.petugas_menerima_id = u2.id
        WHERE l.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $laporanId]);
    $rep = $stmt->fetch();

    if (!$rep) {
        return ['success' => false, 'message' => 'Laporan tidak ditemukan'];
    }

    $appName = get_app_name();
    $docUrl = get_full_web_url("laporan/detail?id={$rep['id']}");
    $tanggalFmt = format_tanggal($rep['tanggal']);
    $divider = "─────────────────────────────";

    // WhatsApp Message
    $waText = "*PENGESAHAN SERAH TERIMA SHIFT*\n"
            . "*{$appName}*\n"
            . "{$divider}\n"
            . "Yth. *{$rep['nama_menyerahkan']}*,\n\n"
            . "Laporan Serah Terima Shift *{$rep['nomor_laporan']}* ({$rep['nama_lokasi']}) telah *DISETUJUI & DISAHKAN* oleh *{$rep['nama_menerima']}*.\n\n"
            . "*Tautan Dokumen Resmi:*\n"
            . "{$docUrl}\n"
            . "{$divider}\n"
            . "_{$appName} — Sistem Berita Acara Operasional_";

    $waResult = null;
    if (!empty($rep['wa_menyerahkan'])) {
        $waResult = send_whatsapp_api($rep['wa_menyerahkan'], $waText);
    }

    // HTML Email
    $emailSubject = "[TERVALIDASI] Serah Terima Shift {$rep['nomor_laporan']} Selesai Disahkan";
    $htmlEmail = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f7f7fb; color: #181b2c; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 30px rgba(45,40,105,0.08); overflow: hidden; border: 1px solid #ebebf7; }
        .header { background: linear-gradient(135deg, #10b981, #059669); padding: 32px 24px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { padding: 28px 24px; }
        .btn { display: block; width: fit-content; margin: 24px auto 10px; padding: 12px 28px; background: #10b981; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 14px; border-radius: 14px; text-align: center; }
        .footer { padding: 20px 24px; background: #fcfcfe; border-top: 1px solid #f0f0fa; text-align: center; font-size: 11px; color: #94a3b8; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='header'>
          <h1>SERAH TERIMA TELAH DIVALIDASI</h1>
          <p>{$appName} — Dokumen Resmi Disahkan</p>
        </div>
        <div class='body'>
          <p style='font-size: 14px; margin-top: 0;'>Yth. <strong>{$rep['nama_menyerahkan']}</strong>,</p>
          <p style='font-size: 13px; color: #475569; line-height: 1.6;'>
            Laporan serah terima tugas Anda untuk nomor <strong>{$rep['nomor_laporan']}</strong> pada unit <strong>{$rep['nama_lokasi']}</strong> telah berhasil divalidasi dan ditandatangani oleh <strong>{$rep['nama_menerima']}</strong>.
          </p>
          <a href='{$docUrl}' class='btn'>Lihat &amp; Cetak Dokumen Lengkap</a>
        </div>
        <div class='footer'>
          Email otomatis {$appName}.
        </div>
      </div>
    </body>
    </html>
    ";

    $emailResult = null;
    if (!empty($rep['email_menyerahkan'])) {
        $emailResult = send_smtp_email($rep['email_menyerahkan'], $rep['nama_menyerahkan'], $emailSubject, $htmlEmail);
    }

    // In-App Bell Notification to Submitter
    if (!empty($rep['petugas_menyerahkan_id'])) {
        require_once __DIR__ . '/bell_helper.php';
        bell_create_notification(
            $pdo,
            (int)$rep['petugas_menyerahkan_id'],
            'approved',
            "Laporan Divalidasi: {$rep['nomor_laporan']}",
            "{$rep['nama_menerima']} telah menyetujui laporan serah terima di {$rep['nama_lokasi']}.",
            "laporan/detail?id={$rep['id']}"
        );
    }

    return [
        'success'      => true,
        'email_result' => $emailResult,
        'wa_result'    => $waResult,
        'wa_url'       => !empty($rep['wa_menyerahkan']) ? get_whatsapp_url($rep['wa_menyerahkan'], $waText) : ''
    ];
}
