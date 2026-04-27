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
$filter = $_GET['filter'] ?? 'all';
$where = "approved=1 AND status!='dismissed'";
if ($filter == 'suspended') $where = "status='suspended'";
elseif ($filter == 'pending') $where = "approved=0";
$students = $conn->query("SELECT id,fullname,class_level,status,suspension_end,approved FROM users WHERE $where ORDER BY class_level");
?>
<!DOCTYPE html><html><head><title>Student List</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_students_list</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>👥 Student List</h1><div><a href="?filter=all">All Active</a> | <a href="?filter=suspended">Suspended</a> | <a href="?filter=pending">Pending Approval</a></div><table class="data-table" border="1"><tr><th>Name</th><th>Class</th><th>Status</th><th>Actions</th></tr><?php while($s=$students->fetch_assoc()):?><tr><td><?=htmlspecialchars($s['fullname'])?></td><td><?=$s['class_level']?></td><td><?=$s['status']?> <?php if($s['suspension_end']) echo "until ".$s['suspension_end']; if(!$s['approved']) echo "(pending approval)";?></td><td><a href="admin_discipline.php?user_id=<?=$s['id']?>">Discipline</a></td></tr><?php endwhile;?></table><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>