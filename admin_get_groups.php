<?php
require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$class = $_GET['class'] ?? '';
$route = $_GET['route'] ?? '';
if (!$class || !$route) {
    echo json_encode([]);
    exit;
}
$groups = $conn->query("SELECT g.id, g.group_number, 
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as current_members
    FROM groups g WHERE g.class_level = '$class' AND g.route = '$route' ORDER BY g.group_number");
$result = [];
while ($g = $groups->fetch_assoc()) {
    $result[] = $g;
}
header('Content-Type: application/json');
echo json_encode($result);
?>