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
    $conn->query("DELETE FROM notes WHERE id=$id");
    header("Location: admin_notes_list.php");
    exit;
}
$notes = $conn->query("SELECT id,title,subject,class_level,created_at FROM notes ORDER BY created_at DESC");
?>
<!DOCTYPE html><html><head><title>Manage Notes</title>    <link rel="stylesheet" href="style.css">
</head><body>
    <?php include_once 'includes/header.php'; ?>

<div class="container">

<div class="content-grid">
<a href="admin_note_editor.php">+ New Note</a><table class="data-table" border="1"><tr><th>Title</th><th>Subject</th><th>Class</th><th>Created</th><th>Actions</th></tr><?php while($n=$notes->fetch_assoc()):?><tr><td><?=htmlspecialchars($n['title'])?></td><td><?=$n['subject']?></td><td><?=$n['class_level']?></td><td><?=$n['created_at']?></td><td><a href="view_note.php?id=<?=$n['id']?>" target="_blank">View</a> | <a href="admin_edit_note.php?id=<?=$n['id']?>">Edit</a> | <a href="?delete=<?=$n['id']?>" onclick="return confirm('Delete?')">Delete</a></td></tr><?php endwhile;?></table> 
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>