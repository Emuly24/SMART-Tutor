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
$backup = "-- SMART Circle Backup\n-- " . date('Y-m-d H:i:s') . "\n\n";
$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_array()) {
    $table = $t[0];
    $create = $conn->query("SHOW CREATE TABLE $table")->fetch_assoc();
    $backup .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";
    $rows = $conn->query("SELECT * FROM $table");
    while ($row = $rows->fetch_assoc()) {
        $cols = array_keys($row);
        $vals = array_map([$conn, 'real_escape_string'], array_values($row));
        $backup .= "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES ('" . implode("', '", $vals) . "');\n";
    }
    $backup .= "\n";
}
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
echo $backup;
exit;