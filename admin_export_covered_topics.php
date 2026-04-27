<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
}
$conn = getDB();
$class = $_GET['class'] ?? '';
$where = "";
if ($class && $class != 'all') $where = "WHERE class_level='$class'";
$res = $conn->query("SELECT subject, topic, class_level, covered_date FROM topics_covered $where ORDER BY covered_date DESC");
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="covered_topics_'.date('Y-m-d').'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Subject', 'Topic', 'Class', 'Date Covered']);
while ($r = $res->fetch_assoc()) fputcsv($out, [$r['subject'], $r['topic'], $r['class_level'], $r['covered_date']]);
fclose($out);
exit;