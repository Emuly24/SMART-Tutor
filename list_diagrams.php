<?php
require_once 'check_remember_me.php';

// list_diagrams.php - Admin-only API to get uploaded diagrams
require_once 'config.php';
session_start();

// Ensure only admin can access this endpoint
$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    // If not logged in via session, check HTTP Basic Auth
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
}

// List diagrams from uploads/diagrams/ directory
$diagramsDir = __DIR__ . '/uploads/diagrams/';
$result = [];

if (is_dir($diagramsDir)) {
    $files = glob($diagramsDir . '*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
    foreach ($files as $file) {
        $result[] = [
            'url' => 'uploads/diagrams/' . basename($file),
            'name' => basename($file),
            'size' => filesize($file)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
?>