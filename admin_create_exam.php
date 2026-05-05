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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDB();
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $desc = $_POST['description'];
    $dur = (int)$_POST['duration_minutes'];
    $conn->query("INSERT INTO exams (title,subject,class_level,description,duration_minutes) VALUES ('$title','$subject','$class','$desc',$dur)");
    $id = $conn->insert_id;
    header("Location: admin_add_questions.php?exam_id=$id");
    exit;
}
?>
<!DOCTYPE html><html><head><title>Create Exam</title>    <link rel="stylesheet" href="style.css">
</head><body>
    <?php include_once 'includes/header.php'; ?>

    

<div class="container">

<div class="content-grid">
<form method="post"><label>Title</label><input type="text" name="title" required><label>Subject</label><input type="text" name="subject" required><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select><label>Description</label><textarea name="description"></textarea><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="60"><button type="submit">Create & Add Questions</button></form>
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>