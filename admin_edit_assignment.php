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
$assign = $conn->query("SELECT * FROM assignments WHERE id=$id")->fetch_assoc();
if (!$assign) die("Not found");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $subj = $_POST['subject'];
    $class = $_POST['class_level'];
    $due = $_POST['due_date'];
    $attach = $assign['attachment_file_path'];
    if (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == 1) {
        if ($attach && file_exists($attach)) unlink($attach);
        $attach = null;
    }
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','png','pdf','doc','txt'])) {
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['attachment']['name']);
            $dest = $dir . $name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                if ($attach && file_exists($attach)) unlink($attach);
                $attach = $dest;
            }
        }
    }
    $conn->query("UPDATE assignments SET title='$title', description='$desc', attachment_file_path='$attach', subject='$subj', class_level='$class', due_date='$due' WHERE id=$id");
    echo "<script>alert('Updated'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Assignment</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_edit_assignment</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>✏️ Edit Assignment</h1><form method="post" enctype="multipart/form-data"><label>Title</label><input type="text" name="title" value="<?=htmlspecialchars($assign['title'])?>" required><label>Description</label><textarea name="description" rows="4"><?=htmlspecialchars($assign['description'])?></textarea><?php if($assign['attachment_file_path']):?><p>Current attachment: <a href="admin_download.php?type=assignment&file=<?=urlencode(basename($assign['attachment_file_path']))?>" target="_blank">View</a> <label><input type="checkbox" name="remove_attachment" value="1"> Remove</label></p><?php endif;?><label>Replace/Add Attachment</label><input type="file" name="attachment"><label>Subject</label><input type="text" name="subject" value="<?=$assign['subject']?>"><label>Class</label><select name="class_level"><option value="Form 3" <?=($assign['class_level']=='Form 3')?'selected':''?>>Form 3</option><option value="Form 4" <?=($assign['class_level']=='Form 4')?'selected':''?>>Form 4</option></select><label>Due Date</label><input type="date" name="due_date" value="<?=$assign['due_date']?>"><button type="submit">Save</button></form>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>