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
$id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$id")->fetch_assoc();
if (!$note) die("Note not found");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $conn->query("UPDATE notes SET title='$title', subject='$subject', class_level='$class', content='$content' WHERE id=$id");
    echo "<script>alert('Note updated'); window.location='admin_notes_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Note</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<style>
    .ck-editor__editable { min-height: 500px; width: 100% !important; }
    .ck-editor { width: 100% !important; }
</style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div style="padding: 2rem;">
            <form method="post">
                <div class="form-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($note['title']) ?>" required></div>
                <div class="form-group"><label>Subject</label><input type="text" name="subject" value="<?= $note['subject'] ?>" required></div>
                <div class="form-group"><label>Class</label><select name="class_level">
                    <option value="Form 3" <?= ($note['class_level']=='Form 3') ? 'selected' : '' ?>>Form 3</option>
                    <option value="Form 4" <?= ($note['class_level']=='Form 4') ? 'selected' : '' ?>>Form 4</option>
                </select></div>
                <div class="form-group"><label>Content</label><textarea name="content" id="editor"><?= htmlspecialchars($note['content']) ?></textarea></div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <script>ClassicEditor.create(document.querySelector('#editor'), {}).catch(console.error);</script>
</body></html>