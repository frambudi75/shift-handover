<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    echo "1. Checking and updating 'users' table...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('email', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(120) NULL AFTER username");
        echo " - Added 'email' column to users.\n";
    } else {
        echo " - 'email' column already exists in users.\n";
    }

    if (!in_array('no_wa', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN no_wa VARCHAR(30) NULL AFTER email");
        echo " - Added 'no_wa' column to users.\n";
    } else {
        echo " - 'no_wa' column already exists in users.\n";
    }

    // Populate initial dummy email & no_wa for existing default accounts if empty
    $pdo->exec("UPDATE users SET email = CONCAT(username, '@operasional.local') WHERE email IS NULL OR email = ''");
    $pdo->exec("UPDATE users SET no_wa = '081234567890' WHERE no_wa IS NULL OR no_wa = ''");

    echo "\n2. Adding SMTP and WhatsApp keys to 'app_settings' table...\n";
    $defaults = [
        'smtp_enabled'     => '0',
        'smtp_host'        => 'smtp.gmail.com',
        'smtp_port'        => '587',
        'smtp_user'        => '',
        'smtp_pass'        => '',
        'smtp_crypto'      => 'tls',
        'smtp_from_name'   => 'OMIT Shift Handover',
        'smtp_from_email'  => 'no-reply@operasional.local',
        
        'wa_enabled'       => '1',
        'wa_provider'      => 'direct', // 'direct' (wa.me click-to-chat), 'fonnte', 'generic_api'
        'wa_api_token'     => '',
        'wa_api_url'       => 'https://api.fonnte.com/send',
    ];

    $stmtCheck = $pdo->prepare("SELECT setting_key FROM app_settings WHERE setting_key = :k");
    $stmtInsert = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)");

    foreach ($defaults as $k => $v) {
        $stmtCheck->execute(['k' => $k]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute(['k' => $k, 'v' => $v]);
            echo " - Inserted setting key: {$k}\n";
        } else {
            echo " - Setting key already exists: {$k}\n";
        }
    }

    echo "\nDatabase migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
