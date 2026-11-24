<?php
// manifest.php - Dynamic Progressive Web App (PWA) Manifest
header('Content-Type: application/manifest+json; charset=utf-8');
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$appName     = get_app_name();
$appFullName = get_app_full_name();
$appLogo     = get_app_logo_url();

$defaultIcon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='112' fill='%236558f5'/%3E%3Cpath d='M160 168h192v32H160zm0 80h192v32H160zm0 80h128v32H160z' fill='white'/%3E%3C/svg%3E";

$icons = [];
if ($appLogo) {
    $icons[] = ['src' => $appLogo, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'];
    $icons[] = ['src' => $appLogo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
} else {
    $icons[] = ['src' => $defaultIcon, 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'];
    $icons[] = ['src' => $defaultIcon, 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'];
}

echo json_encode([
    'name'             => $appFullName,
    'short_name'       => $appName,
    'description'      => $appFullName,
    'start_url'        => BASE_URL . 'dashboard',
    'scope'            => BASE_URL,
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#f8fafc',
    'theme_color'      => '#6558f5',
    'icons'            => $icons,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
