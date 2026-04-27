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
$id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$id")->fetch_assoc();
if (!$note) die("Note not found");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $conn->query("UPDATE notes SET title='$title', subject='$subject', class_level='$class', content='$content' WHERE id=$id");
    echo "<script>alert('Updated'); window.location='admin_notes_list.php';</script>";
}
?>
<!DOCTYPE html><html><head><title>Edit Note</title><script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_edit_note</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>✏️ Edit Note</h1><form method="post"><label>Title</label><input type="text" name="title" value="<?=htmlspecialchars($note['title'])?>" required><label>Subject</label><input type="text" name="subject" value="<?=$note['subject']?>" required><label>Class</label><select name="class_level"><option value="Form 3" <?=($note['class_level']=='Form 3')?'selected':''?>>Form 3</option><option value="Form 4" <?=($note['class_level']=='Form 4')?'selected':''?>>Form 4</option></select><label>Content</label><textarea name="content" id="editor"><?=htmlspecialchars($note['content'])?></textarea><button type="submit">Save</button></form><script>ClassicEditor.create(document.querySelector('#editor')).catch(console.error);</script>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>