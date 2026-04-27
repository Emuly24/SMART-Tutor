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
$today = date('Y-m-d');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['status'] as $uid => $stat) {
        $conn->query("INSERT INTO attendance (user_id,date,status,marked_by_admin) VALUES ($uid,'$today','$stat',1) ON DUPLICATE KEY UPDATE status='$stat'");
        if ($stat == 'late') {
            $late = $conn->query("SELECT COUNT(*) FROM attendance WHERE user_id=$uid AND status='late' AND date>=DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0];
            if ($late >= 4 && !$conn->query("SELECT id FROM discipline_log WHERE user_id=$uid AND action='warning' AND reason LIKE '%late%' AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)")->num_rows) {
                $conn->query("INSERT INTO discipline_log (user_id,action,reason) VALUES ($uid,'warning','4 late arrivals in 30 days')");
            }
        }
    }
    $msg = "Attendance saved.";
}
$students = $conn->query("SELECT id,fullname,class_level FROM users WHERE approved=1 AND status!='dismissed' ORDER BY class_level");
$existing = [];
$r = $conn->query("SELECT user_id,status FROM attendance WHERE date='$today'");
while ($row = $r->fetch_assoc()) $existing[$row['user_id']] = $row['status'];
?>
<!DOCTYPE html><html><head><title>Mark Attendance</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_attendance</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📅 Attendance - <?=$today?></h1><?php if($msg) echo "<p style='color:green'>$msg</p>";?><form method="post"><table class="data-table" border="1"><tr><th>Student</th><th>Class</th><th>Status</th></tr><?php while($s=$students->fetch_assoc()):?><tr><td><?=htmlspecialchars($s['fullname'])?></td><td><?=$s['class_level']?></td><td><select name="status[<?=$s['id']?>]"><option value="present" <?=($existing[$s['id']]??'')=='present'?'selected':''?>>Present</option><option value="late" <?=($existing[$s['id']]??'')=='late'?'selected':''?>>Late</option><option value="absent" <?=($existing[$s['id']]??'')=='absent'?'selected':''?>>Absent</option></select></td></tr><?php endwhile;?></table><button type="submit">Save</button></form><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>