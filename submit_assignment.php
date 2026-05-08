<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$aid = (int)$_GET['assignment_id'];
$check = $conn->query("SELECT id FROM assignment_submissions WHERE assignment_id=$aid AND user_id=$uid");
if ($check->num_rows) die("Already submitted.");
$as = $conn->query("SELECT title FROM assignments WHERE id=$aid")->fetch_assoc();
if (!$as) die("Invalid assignment.");
if (!is_content_unlocked('assignment', $aid, $uid)) {
    die("<!DOCTYPE html><html><head><title>Assignment Locked</title><link rel='stylesheet' href='style.css'></head><body>
    <?php include_once 'includes/header.php'; ?>
<div class='container'><div class='header'><a href='assignments.php'>Assignments</a><a href='logout.php' class='logout'>Logout</a></div><div class='error'>This assignment is not yet available for your group.</div><a href='assignments.php'>← Back</a></div><div class="footer"><a href="index.php" class="btn-back">← Back</a></div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['submission_text']);
    $file = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $dest = $dir . "user_{$uid}_assign_{$aid}_" . time() . ".$ext";
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $dest)) $file = $dest;
    }
    if (empty($text) && !$file) die("Provide text or file.");
    $conn->query("INSERT INTO assignment_submissions (assignment_id, user_id, submission_text, file_path) VALUES ($aid, $uid, '$text', '$file')");
    log_activity($uid, "submit_assignment", "Assignment ID: $aid");
    echo "<script>alert('Submitted'); window.location='assignments.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Submit Assignment</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="form-container"><form method="post" enctype="multipart/form-data"><div class="form-group"><label>Your Answer (text)</label><textarea name="submission_text" rows="6"></textarea></div><div class="form-group"><label>OR Upload File</label><input type="file" name="submission_file" accept=".jpg,.png,.pdf,.doc,.txt"></div><button type="submit">Submit</button></form></div></div><div class="footer"><a href="index.php" class="btn-back">← Back</a></div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>