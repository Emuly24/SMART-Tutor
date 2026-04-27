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
$backup = "-- SMART Tutor Backup\n-- " . date('Y-m-d H:i:s') . "\n\n";
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