<?php
// includes/holiday_helper.php - Indonesian National Holidays (Cached API + Built-in Fallback)

/**
 * Fetch Indonesian national holidays for a given year as ['YYYY-MM-DD' => ['name' => string, 'is_national' => bool]].
 */
function get_indonesian_holidays(int $year): array {
    static $memCache = [];
    if (isset($memCache[$year])) {
        return $memCache[$year];
    }

    $cacheDir = __DIR__ . '/../storage/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir();
    }
    $cacheFile = rtrim($cacheDir, '/\\') . '/holidays_' . $year . '.json';

    // 1. Return fresh file cache (< 7 days)
    if (is_file($cacheFile) && (time() - @filemtime($cacheFile)) < 604800) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) {
            $memCache[$year] = $cached;
            return $cached;
        }
    }

    // 2. Try live public Indonesian Holiday API with short 2s timeout
    $holidays = [];
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api-harilibur.vercel.app/api?year=' . $year);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = @curl_exec($ch);
        $code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($code === 200 && is_string($raw) && $raw !== '') {
            $rows = json_decode($raw, true);
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $d = $r['holiday_date'] ?? '';
                    $n = trim($r['holiday_name'] ?? '');
                    $nat = (bool)($r['is_national_holiday'] ?? true);
                    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $d) && $n !== '') {
                        $ts = strtotime($d);
                        if ($ts) {
                            $iso = date('Y-m-d', $ts);
                            $holidays[$iso] = ['name' => $n, 'is_national' => $nat];
                        }
                    }
                }
            }
        }
    }

    // 3. Built-in fallback for 2025-2027 if offline or API unavailable
    if (empty($holidays)) {
        $fallback = [
            2025 => [
                '2025-01-01' => 'Tahun Baru 2025 Masehi',
                '2025-01-27' => 'Isra Mikraj Nabi Muhammad SAW',
                '2025-01-29' => 'Tahun Baru Imlek 2576 Kongzili',
                '2025-03-29' => 'Hari Suci Nyepi (Tahun Baru Saka 1947)',
                '2025-03-31' => 'Hari Raya Idul Fitri 1446 H',
                '2025-04-01' => 'Hari Raya Idul Fitri 1446 H',
                '2025-04-18' => 'Wafat Yesus Kristus',
                '2025-04-20' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2025-05-01' => 'Hari Buruh Internasional',
                '2025-05-12' => 'Hari Raya Waisak 2569 BE',
                '2025-05-29' => 'Kenaikan Yesus Kristus',
                '2025-06-01' => 'Hari Lahir Pancasila',
                '2025-06-06' => 'Hari Raya Idul Adha 1446 H',
                '2025-06-27' => 'Tahun Baru Islam 1447 H',
                '2025-08-17' => 'Hari Kemerdekaan RI',
                '2025-09-05' => 'Maulid Nabi Muhammad SAW',
                '2025-12-25' => 'Hari Raya Natal',
            ],
            2026 => [
                '2026-01-01' => 'Tahun Baru 2026 Masehi',
                '2026-01-16' => 'Isra Mikraj Nabi Muhammad SAW',
                '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
                '2026-03-19' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)',
                '2026-03-21' => 'Hari Raya Idul Fitri 1447 H',
                '2026-03-22' => 'Hari Raya Idul Fitri 1447 H',
                '2026-04-03' => 'Wafat Yesus Kristus',
                '2026-04-05' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2026-05-01' => 'Hari Buruh Internasional',
                '2026-05-14' => 'Kenaikan Yesus Kristus',
                '2026-05-27' => 'Hari Raya Idul Adha 1447 H',
                '2026-05-31' => 'Hari Raya Waisak 2570 BE',
                '2026-06-01' => 'Hari Lahir Pancasila',
                '2026-06-16' => 'Tahun Baru Islam 1448 H',
                '2026-08-17' => 'Hari Kemerdekaan RI',
                '2026-08-25' => 'Maulid Nabi Muhammad SAW',
                '2026-12-25' => 'Hari Raya Natal',
            ],
            2027 => [
                '2027-01-01' => 'Tahun Baru 2027 Masehi',
                '2027-01-05' => 'Isra Mikraj Nabi Muhammad SAW',
                '2027-02-06' => 'Tahun Baru Imlek 2578 Kongzili',
                '2027-03-08' => 'Hari Suci Nyepi (Tahun Baru Saka 1949)',
                '2027-03-10' => 'Hari Raya Idul Fitri 1448 H',
                '2027-03-11' => 'Hari Raya Idul Fitri 1448 H',
                '2027-03-26' => 'Wafat Yesus Kristus',
                '2027-03-28' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2027-05-01' => 'Hari Buruh Internasional',
                '2027-05-06' => 'Kenaikan Yesus Kristus',
                '2027-05-17' => 'Hari Raya Idul Adha 1448 H',
                '2027-05-20' => 'Hari Raya Waisak 2571 BE',
                '2027-06-01' => 'Hari Lahir Pancasila',
                '2027-06-06' => 'Tahun Baru Islam 1449 H',
                '2027-08-17' => 'Hari Kemerdekaan RI',
                '2027-08-15' => 'Maulid Nabi Muhammad SAW',
                '2027-12-25' => 'Hari Raya Natal',
            ],
        ];
        if (isset($fallback[$year])) {
            foreach ($fallback[$year] as $d => $n) {
                $holidays[$d] = ['name' => $n, 'is_national' => true];
            }
        }
    }

    if (!empty($holidays)) {
        @file_put_contents($cacheFile, json_encode($holidays, JSON_UNESCAPED_UNICODE));
    }

    $memCache[$year] = $holidays;
    return $holidays;
}
