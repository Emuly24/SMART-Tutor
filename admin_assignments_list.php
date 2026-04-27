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
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM assignments WHERE id=$id");
    header("Location: admin_assignments_list.php");
    exit;
}
$assignments = $conn->query("SELECT * FROM assignments ORDER BY due_date ASC");
?>
<!DOCTYPE html><html><head><title>Manage Assignments</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_assignments_list</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📋 Manage Assignments</h1><a href="admin_create_assignment.php">+ New Assignment</a><table class="data-table" border="1"><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Due</th><th>Actions</th></tr><?php while($a=$assignments->fetch_assoc()):?><tr><td><?=$a['id']?></td><td><?=htmlspecialchars($a['title'])?></td><td><?=$a['subject']?></td><td><?=$a['class_level']?></td><td><?=$a['due_date']?></td><td><a href="admin_edit_assignment.php?id=<?=$a['id']?>">Edit</a> | <a href="?delete=<?=$a['id']?>" onclick="return confirm('Delete?')">Delete</a></td></tr><?php endwhile;?></table><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>