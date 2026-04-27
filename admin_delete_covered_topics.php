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
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = $_POST['class_level'];
    $older = $_POST['older_than'];
    $where = [];
    $params = [];
    $types = "";
    if ($class && $class != 'all') { $where[] = "class_level=?"; $params[] = $class; $types .= "s"; }
    if ($older) { $where[] = "covered_date < ?"; $params[] = $older; $types .= "s"; }
    if (empty($where)) $msg = "Select a filter.";
    else {
        $sql = "DELETE FROM topics_covered WHERE " . implode(" AND ", $where);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $msg = "Deleted " . $stmt->affected_rows . " record(s).";
    }
}
?>
<!DOCTYPE html><html><head><title>Delete Covered Topics</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_delete_covered_topics</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>🗑️ Batch Delete Covered Topics</h1><p><?=$msg?></p><form method="post"><label>Class:</label><select name="class_level"><option value="all">All</option><option value="Form 3">Form 3</option><option value="Form 4">Form 4</option></select><br><label>Delete older than date:</label><input type="date" name="older_than"><br><button type="submit" onclick="return confirm('Delete?')">Delete</button></form><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>