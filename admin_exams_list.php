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
    $conn->query("DELETE FROM exams WHERE id=$id");
    header("Location: admin_exams_list.php");
    exit;
}
$exams = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM exam_questions WHERE exam_id=e.id) as qcnt FROM exams e ORDER BY e.created_at DESC");
?>
<!DOCTYPE html><html><head><title>Manage Exams</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_exams_list</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📝 Manage Exams</h1><a href="admin_create_exam.php">+ Create New Exam</a><table class="data-table" border="1"><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Duration</th><th>Questions</th><th>Actions</th></tr><?php while($e=$exams->fetch_assoc()):?><tr><td><?=$e['id']?></td><td><?=htmlspecialchars($e['title'])?></td><td><?=$e['subject']?></td><td><?=$e['class_level']?></td><td><?=$e['duration_minutes']?></td><td><?=$e['qcnt']?></td><td><a href="admin_add_questions.php?exam_id=<?=$e['id']?>">Edit Q's</a> | <a href="admin_mark_exams.php?exam_id=<?=$e['id']?>">Mark</a> | <a href="?delete=<?=$e['id']?>" onclick="return confirm('Delete?')">Delete</a></td></tr><?php endwhile;?></table> 
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn">← Back</a></div>
</div>
</body></html>