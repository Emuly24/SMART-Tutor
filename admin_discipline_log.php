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
$log = $conn->query("SELECT d.*, u.fullname, u.class_level FROM discipline_log d JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC");
?>
<!DOCTYPE html><html><head><title>Discipline Log</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_discipline_log</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📜 Discipline Log</h1><table class="data-table" border="1"><tr><th>Date</th><th>Student</th><th>Class</th><th>Action</th><th>Reason</th><th>Suspension End</th></tr><?php while($r=$log->fetch_assoc()):?><tr><td><?=$r['created_at']?></td><td><?=htmlspecialchars($r['fullname'])?></td><td><?=$r['class_level']?></td><td><?=strtoupper($r['action'])?></td><td><?=htmlspecialchars($r['reason'])?></td><td><?=$r['suspension_end']??'-'?></td></tr><?php endwhile;?></table><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>