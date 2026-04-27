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
$classes = ['Form 3', 'Form 4'];
foreach ($classes as $c) {
    $cnt = $conn->query("SELECT COUNT(*) FROM users WHERE class_level='$c' AND approved=1 AND status!='dismissed'")->fetch_row()[0];
    echo "<h2>$c: $cnt / 5 active</h2>";
    $students = $conn->query("SELECT fullname,status FROM users WHERE class_level='$c' AND approved=1 AND status!='dismissed'");
    while ($s = $students->fetch_assoc()) echo "- {$s['fullname']} ({$s['status']})<br>";
}
?><a href="admin_dashboard.php">Back</a>