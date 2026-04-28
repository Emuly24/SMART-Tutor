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
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $dir = 'uploads/books/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext != 'pdf') $msg = "Only PDF allowed.";
    else {
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['pdf_file']['name']);
        $dest = $dir . $name;
        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
            $conn->query("INSERT INTO books (title,subject,class_level,file_path) VALUES ('$title','$subject','$class','$dest')");
            $msg = "Book uploaded.";
        } else $msg = "Upload failed.";
    }
}
?>
<!DOCTYPE html><html><head><title>Upload Book</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_upload_book</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>📚 Upload PDF Book</h1><?php if($msg) echo "<p>$msg</p>";?><form method="post" enctype="multipart/form-data"><label>Title</label><input type="text" name="title" required><label>Subject</label><input type="text" name="subject" required><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select><label>PDF file</label><input type="file" name="pdf_file" accept="application/pdf" required><button type="submit">Upload</button></form> 
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn">← Back</a></div>
</div>
</body></html>