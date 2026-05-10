<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
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
$where = ($class && $class != 'all') ? "WHERE class_level='$class'" : "";
$res = $conn->query("SELECT subject, topic, class_level, covered_date FROM topics_covered $where ORDER BY covered_date DESC");
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="covered_topics_'.date('Y-m-d').'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Subject', 'Topic', 'Class', 'Date Covered']);
while ($r = $res->fetch_assoc()) fputcsv($out, [$r['subject'], $r['topic'], $r['class_level'], $r['covered_date']]);
fclose($out);
exit;