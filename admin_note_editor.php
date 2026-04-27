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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDB();
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $conn->query("INSERT INTO notes (title,subject,class_level,content) VALUES ('$title','$subject','$class','$content')");
    echo "<script>alert('Note saved');</script>";
}
?>
<!DOCTYPE html><html><head><title>Write Note</title><script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script><script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_note_editor</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>✍️ Write Note</h1><form method="post"><label>Title</label><input type="text" name="title" required><label>Subject</label><input type="text" name="subject" required><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select><label>Content</label><textarea name="content" id="editor"></textarea><button type="submit">Save</button></form><script>ClassicEditor.create(document.querySelector('#editor')).catch(console.error);</script><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>