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
$covered = $conn->query("SELECT * FROM topics_covered ORDER BY covered_date DESC");
?>
<!DOCTYPE html><html><head><title>Covered Topics</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_topics_covered</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📜 Topics Already Covered</h1><table class="data-table" border="1"><tr><th>Subject</th><th>Topic</th><th>Class</th><th>Covered Date</th></tr><?php while($c=$covered->fetch_assoc()):?><tr><td><?=htmlspecialchars($c['subject'])?></td><td><?=htmlspecialchars($c['topic'])?></td><td><?=$c['class_level']?></td><td><?=$c['covered_date']?></td></tr><?php endwhile;?></table><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>