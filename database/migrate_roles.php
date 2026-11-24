<?php
// database/migrate_roles.php - Update roles to superadmin, admin, user
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== Updating User Roles Schema ===\n";

    // 1. Alter table users role column
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user'");
    echo "✓ Altered users.role to ENUM('superadmin', 'admin', 'user')\n";

    // 2. Convert any existing 'operator' or other roles to 'user'
    $pdo->exec("UPDATE `users` SET `role` = 'user' WHERE `role` NOT IN ('superadmin', 'admin', 'user')");
    $pdo->exec("UPDATE `users` SET `role` = 'user' WHERE `username` != 'admin' AND `username` != 'superadmin'");
    echo "✓ Migrated operators to user role\n";

    // 3. Ensure superadmin exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'superadmin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmtInsert = $pdo->prepare("INSERT INTO users (nama_lengkap, username, password, role, jabatan, status) 
            VALUES ('Super Administrator', 'superadmin', :pass, 'superadmin', 'Head of Operations & Systems', 'aktif')");
        $stmtInsert->execute(['pass' => password_hash('superadmin123', PASSWORD_BCRYPT)]);
        echo "✓ Created superadmin account (username: superadmin, pass: superadmin123)\n";
    } else {
        $pdo->exec("UPDATE users SET role = 'superadmin', nama_lengkap = 'Super Administrator' WHERE username = 'superadmin'");
        echo "✓ Superadmin account verified\n";
    }

    // 4. Ensure admin exists
    $pdo->exec("UPDATE users SET role = 'admin', nama_lengkap = 'Administrator Operasional' WHERE username = 'admin'");
    echo "✓ Admin account verified\n";

    // 5. Ensure a standard demo 'user' exists with password 'user123'
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = 'user'");
    $stmtUser->execute();
    if (!$stmtUser->fetch()) {
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (nama_lengkap, username, password, role, jabatan, status) 
            VALUES ('Operator User Demo', 'user', :pass, 'user', 'Field Operator', 'aktif')");
        $stmtInsertUser->execute(['pass' => password_hash('user123', PASSWORD_BCRYPT)]);
        echo "✓ Created demo user account (username: user, pass: user123)\n";
    }

    echo "=== Role Migration Completed Successfully! ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
