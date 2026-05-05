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
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$end = date('Y-m-d');
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-30 days'));
$students = $conn->query("SELECT id,fullname,class_level FROM users WHERE approved=1 AND status!='dismissed' ORDER BY class_level");
?>
<!DOCTYPE html><html><head><title>Attendance Report</title>    <link rel="stylesheet" href="style.css">
</head><body>
    <?php include_once 'includes/header.php'; ?>

<div class="container">

<div class="content-grid">
<form method="get">From: <input type="date" name="start" value="<?=$start?>"> To: <input type="date" name="end" value="<?=$end?>"><button type="submit">Filter</button></form><table class="data-table" border="1"><tr><th>Student</th><th>Class</th><th>Present</th><th>Late</th><th>Absent</th><th>Total</th><th>% (P+L)</th></tr><?php while($s=$students->fetch_assoc()): $stats=$conn->query("SELECT SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as p, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as l, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as a, COUNT(*) as t FROM attendance WHERE user_id={$s['id']} AND date BETWEEN '$start' AND '$end'")->fetch_assoc(); $p=$stats['p']??0; $l=$stats['l']??0; $a=$stats['a']??0; $t=$stats['t']??0; $rate=$t?round(($p+$l)/$t*100,1):0;?><tr><td><?=htmlspecialchars($s['fullname'])?></td><td><?=$s['class_level']?></td><td><?=$p?></td><td><?=$l?></td><td><?=$a?></td><td><?=$t?></td><td><?=$rate?>%</td></tr><?php endwhile;?></table>
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>