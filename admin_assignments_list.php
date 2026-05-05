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
    <?php include_once 'includes/header.php'; ?>

    

<div class="container">

<div class="content-grid">
<a href="admin_create_assignment.php">+ New Assignment</a><table class="data-table" border="1"><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Due</th><th>Actions</th></tr><?php while($a=$assignments->fetch_assoc()):?><tr><td><?=$a['id']?></td><td><?=htmlspecialchars($a['title'])?></td><td><?=$a['subject']?></td><td><?=$a['class_level']?></td><td><?=$a['due_date']?></td><td><a href="admin_edit_assignment.php?id=<?=$a['id']?>">Edit</a> | <a href="?delete=<?=$a['id']?>" onclick="return confirm('Delete?')">Delete</a></td></tr><?php endwhile;?></table>
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>