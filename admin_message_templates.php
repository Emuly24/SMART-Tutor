<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

if (function_exists('getAdminHash')) {
    $admin_hash = getAdminHash();
} elseif (defined('ADMIN_HASH')) {
    $admin_hash = ADMIN_HASH;
} else {
    $admin_hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $title = $_POST['title'];
    $msg = $_POST['message'];
    $conn->query("INSERT INTO message_templates (title, message) VALUES ('$title', '$msg')");
    header("Location: admin_message_templates.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM message_templates WHERE id=$id");
    header("Location: admin_message_templates.php");
    exit;
}
$templates = $conn->query("SELECT * FROM message_templates ORDER BY id");
?>
<!DOCTYPE html><html><head><title>Message Templates</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Message Templates</h1>
        <div class="content-grid">
            <div class="card">
                <h3>Create New Template</h3>
                <form method="post">
                    <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                    <div class="form-group"><label>Message (use placeholders: {student}, {note_title}, {hours_remaining})</label><textarea name="message" rows="4" required></textarea></div>
                    <button type="submit" name="add_template" class="btn">Save Template</button>
                </form>
            </div>
            <div class="card">
                <h3>Existing Templates</h3>
                <?php if($templates->num_rows == 0): ?>
                    <p>No templates yet. Create one above.</p>
                <?php else: ?>
                    <?php while($t=$templates->fetch_assoc()): ?>
                        <div class="marking-block">
                            <strong><?=htmlspecialchars($t['title'])?></strong><br>
                            <?=nl2br(htmlspecialchars($t['message']))?><br>
                            <a href="?delete=<?=$t['id']?>" onclick="return confirm('Delete?')" class="btn-danger">Delete</a>
                        </div>
                        <hr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>