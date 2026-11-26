<?php
// includes/ldap_helper.php - Integrasi Autentikasi & Sinkronisasi User LDAP / Active Directory

/**
 * Ambil seluruh konfigurasi LDAP dari app_settings dengan nilai default aman.
 */
function get_ldap_config(): array {
    return [
        'enabled'       => get_setting('ldap_enabled', '0') === '1',
        'type'          => get_setting('ldap_type', 'ad'), // ad | openldap | custom
        'host'          => trim(get_setting('ldap_host', '')),
        'port'          => (int)get_setting('ldap_port', '389') ?: 389,
        'use_tls'       => get_setting('ldap_use_tls', '0') === '1',
        'base_dn'       => trim(get_setting('ldap_base_dn', '')),
        'bind_dn'       => trim(get_setting('ldap_bind_dn', '')),
        'bind_pass'     => (string)get_setting('ldap_bind_pass', ''),
        'user_filter'   => trim(get_setting('ldap_user_filter', '(sAMAccountName=%s)')) ?: '(sAMAccountName=%s)',
        'sync_filter'   => trim(get_setting('ldap_sync_filter', '(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))')) ?: '(objectClass=person)',
        'attr_username' => trim(get_setting('ldap_attr_username', 'samaccountname')) ?: 'samaccountname',
        'attr_name'     => trim(get_setting('ldap_attr_name', 'displayname')) ?: 'displayname',
        'attr_email'    => trim(get_setting('ldap_attr_email', 'mail')) ?: 'mail',
        'attr_title'    => trim(get_setting('ldap_attr_title', 'title')) ?: 'title',
        'default_role'  => in_array(get_setting('ldap_default_role', 'user'), ['user', 'admin'], true)
                           ? get_setting('ldap_default_role', 'user')
                           : 'user',
    ];
}

/**
 * Buat koneksi LDAP dan lakukan service bind.
 * @return array{conn:mixed, error:?string}
 */
function ldap_connect_and_bind(array $cfg): array {
    if (!function_exists('ldap_connect')) {
        return ['conn' => null, 'error' => 'Ekstensi PHP ext-ldap belum terpasang di server ini.'];
    }
    if (empty($cfg['host']) || empty($cfg['base_dn'])) {
        return ['conn' => null, 'error' => 'Host LDAP dan Base DN wajib diisi.'];
    }

    $host = $cfg['host'];
    $port = (int)($cfg['port'] ?? 389);
    if (!preg_match('#^ldaps?://#i', $host)) {
        $uri = ($port === 636 ? 'ldaps://' : 'ldap://') . $host . ':' . $port;
    } else {
        $uri = preg_match('#:\d+$#', $host) ? $host : ($host . ':' . $port);
    }

    $conn = @ldap_connect($uri);
    if (!$conn) {
        return ['conn' => null, 'error' => "Gagal menginisialisasi koneksi ke {$uri}."];
    }

    @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    if (defined('LDAP_OPT_NETWORK_TIMEOUT')) {
        @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);
    }

    if (!empty($cfg['use_tls']) && stripos($uri, 'ldaps://') !== 0) {
        if (!@ldap_start_tls($conn)) {
            $err = @ldap_error($conn);
            @ldap_unbind($conn);
            return ['conn' => null, 'error' => "Gagal StartTLS: {$err}"];
        }
    }

    if (!empty($cfg['bind_dn'])) {
        $bound = @ldap_bind($conn, $cfg['bind_dn'], $cfg['bind_pass']);
    } else {
        $bound = @ldap_bind($conn);
    }

    if (!$bound) {
        $err = @ldap_error($conn);
        @ldap_unbind($conn);
        return ['conn' => null, 'error' => "Bind ke server LDAP gagal: {$err}"];
    }

    return ['conn' => $conn, 'error' => null];
}

/**
 * Uji koneksi & bind LDAP serta hitung perkiraan objek user di Base DN.
 */
function ldap_test_connection(array $cfg): array {
    $res = ldap_connect_and_bind($cfg);
    if (!$res['conn']) {
        return ['success' => false, 'message' => $res['error'], 'count' => 0];
    }
    $conn = $res['conn'];
    $attrs = [strtolower($cfg['attr_username']), strtolower($cfg['attr_name'])];
    $search = @ldap_search($conn, $cfg['base_dn'], $cfg['sync_filter'], $attrs, 0, 100);
    if (!$search) {
        $err = @ldap_error($conn);
        @ldap_unbind($conn);
        return ['success' => false, 'message' => "Bind berhasil, tetapi pencarian di Base DN gagal: {$err}", 'count' => 0];
    }
    $count = @ldap_count_entries($conn, $search);
    @ldap_unbind($conn);
    return [
        'success' => true,
        'message' => "Koneksi & Bind LDAP berhasil! Ditemukan {$count} entri user (sample maks. 100).",
        'count'   => (int)$count,
    ];
}

/**
 * Buat atau perbarui user lokal berdasarkan data LDAP.
 */
function ldap_upsert_local_user(PDO $pdo, string $username, string $fullName, string $email, string $title, string $defaultRole): array {
    $username = mb_substr(trim($username), 0, 50);
    $fullName = mb_substr(trim($fullName) ?: $username, 0, 100);
    $email    = mb_substr(trim($email), 0, 150) ?: null;
    $title    = mb_substr(trim($title) ?: 'Staff Operasional', 0, 100);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => $username]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("
            UPDATE users
            SET nama_lengkap = :nama,
                email = COALESCE(:email, email),
                jabatan = CASE WHEN :jabatan != '' THEN :jabatan2 ELSE jabatan END
            WHERE id = :id
        ")->execute([
            'nama'     => $fullName,
            'email'    => $email,
            'jabatan'  => $title,
            'jabatan2' => $title,
            'id'       => (int)$existing['id'],
        ]);

        $stmt->execute(['u' => $username]);
        return ['action' => 'updated', 'user' => $stmt->fetch()];
    }

    $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $pdo->prepare("
        INSERT INTO users (nama_lengkap, username, password, role, jabatan, email, status)
        VALUES (:nama, :uname, :pass, :role, :jabatan, :email, 'aktif')
    ")->execute([
        'nama'    => $fullName,
        'uname'   => $username,
        'pass'    => $randomHash,
        'role'    => $defaultRole,
        'jabatan' => $title,
        'email'   => $email,
    ]);

    $stmt->execute(['u' => $username]);
    return ['action' => 'created', 'user' => $stmt->fetch()];
}

/**
 * Autentikasi 1 user ke server LDAP (dengan proteksi LDAP Injection).
 * Mengembalikan baris user lokal jika berhasil, atau null jika gagal.
 */
function ldap_authenticate_user(PDO $pdo, string $username, string $password): ?array {
    if ($username === '' || $password === '' || !function_exists('ldap_connect')) {
        return null;
    }

    $cfg = get_ldap_config();
    if (!$cfg['enabled']) {
        return null;
    }

    $res = ldap_connect_and_bind($cfg);
    if (!$res['conn']) {
        return null;
    }
    $conn = $res['conn'];

    $escapedUser = function_exists('ldap_escape')
        ? ldap_escape($username, '', LDAP_ESCAPE_FILTER)
        : str_replace(['\\', '*', '(', ')', "\x00"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $username);

    $filter = str_replace('%s', $escapedUser, $cfg['user_filter']);
    $attrU  = strtolower($cfg['attr_username']);
    $attrN  = strtolower($cfg['attr_name']);
    $attrE  = strtolower($cfg['attr_email']);
    $attrT  = strtolower($cfg['attr_title']);

    $search = @ldap_search($conn, $cfg['base_dn'], $filter, [$attrU, $attrN, $attrE, $attrT, 'cn'], 0, 1);
    if (!$search) {
        @ldap_unbind($conn);
        return null;
    }

    $entries = @ldap_get_entries($conn, $search);
    if (empty($entries['count']) || $entries['count'] < 1) {
        @ldap_unbind($conn);
        return null;
    }

    $entry  = $entries[0];
    $userDn = $entry['dn'] ?? '';
    if ($userDn === '') {
        @ldap_unbind($conn);
        return null;
    }

    // Verifikasi password user dengan bind langsung menggunakan DN user tersebut
    $userBind = @ldap_bind($conn, $userDn, $password);
    @ldap_unbind($conn);

    if (!$userBind) {
        return null;
    }

    $ldapUname = $entry[$attrU][0] ?? $username;
    $ldapName  = $entry[$attrN][0] ?? ($entry['cn'][0] ?? $ldapUname);
    $ldapEmail = $entry[$attrE][0] ?? '';
    $ldapTitle = $entry[$attrT][0] ?? '';

    $upserted = ldap_upsert_local_user($pdo, $ldapUname, $ldapName, $ldapEmail, $ldapTitle, $cfg['default_role']);
    $localUser = $upserted['user'] ?? null;

    if ($localUser && ($localUser['status'] ?? 'aktif') !== 'aktif') {
        return null;
    }

    return $localUser ?: null;
}

/**
 * Impor / Sinkronisasi massal seluruh user dari LDAP ke tabel users lokal.
 */
function ldap_sync_all_users(PDO $pdo, array $cfg): array {
    $res = ldap_connect_and_bind($cfg);
    if (!$res['conn']) {
        return ['success' => false, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'message' => $res['error']];
    }
    $conn = $res['conn'];

    $attrU = strtolower($cfg['attr_username']);
    $attrN = strtolower($cfg['attr_name']);
    $attrE = strtolower($cfg['attr_email']);
    $attrT = strtolower($cfg['attr_title']);

    $search = @ldap_search($conn, $cfg['base_dn'], $cfg['sync_filter'], [$attrU, $attrN, $attrE, $attrT, 'cn']);
    if (!$search) {
        $err = @ldap_error($conn);
        @ldap_unbind($conn);
        return ['success' => false, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'message' => "Pencarian user LDAP gagal: {$err}"];
    }

    $entries = @ldap_get_entries($conn, $search);
    @ldap_unbind($conn);

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $total   = (int)($entries['count'] ?? 0);

    for ($i = 0; $i < $total; $i++) {
        $e = $entries[$i];
        $uname = trim((string)($e[$attrU][0] ?? ''));
        if ($uname === '' || str_ends_with($uname, '$')) {
            $skipped++;
            continue;
        }
        $name  = (string)($e[$attrN][0] ?? ($e['cn'][0] ?? $uname));
        $email = (string)($e[$attrE][0] ?? '');
        $title = (string)($e[$attrT][0] ?? '');

        $up = ldap_upsert_local_user($pdo, $uname, $name, $email, $title, $cfg['default_role']);
        if ($up['action'] === 'created') {
            $created++;
        } else {
            $updated++;
        }
    }

    return [
        'success' => true,
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'message' => "Sinkronisasi LDAP selesai: {$created} user baru diimpor, {$updated} diperbarui, {$skipped} dilewati.",
    ];
}
