<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $subj = $_POST['subject'];
    $class = $_POST['class_level'];
    $due = $_POST['due_date'];
    $attach = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','png','pdf','doc','txt'])) {
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['attachment']['name']);
            $dest = $dir . $name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) $attach = $dest;
        }
    }
    $conn->query("INSERT INTO assignments (title,description,attachment_file_path,subject,class_level,due_date) VALUES ('$title','$desc','$attach','$subj','$class','$due')");
    echo "<script>alert('Assignment created'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Create Assignment</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4" required></textarea></div>
                <div class="form-group"><label>Attachment (optional)</label><input type="file" name="attachment" accept=".jpg,.png,.pdf,.doc,.txt"></div>
                <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
                <div class="form-group"><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select></div>
                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>
                <button type="submit" class="btn">Create Assignment</button>
            </form>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>