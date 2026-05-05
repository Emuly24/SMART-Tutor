<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) die("Access denied");
$conn = getDB();

// Add template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $title = $_POST['title'];
    $msg = $_POST['message'];
    $conn->query("INSERT INTO message_templates (title, message) VALUES ('$title', '$msg')");
    header("Location: admin_message_templates.php");
    exit;
}
// Delete template
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
<h2>Create New Template</h2>
<form method="post"><div class="form-group"><label>Title</label><input type="text" name="title" required></div><div class="form-group"><label>Message (use placeholders: {student}, {note_title}, {hours_remaining})</label><textarea name="message" rows="4" required></textarea></div><button type="submit" name="add_template">Save Template</button></form>
<h2>Existing Templates</h2>
<?php while($t=$templates->fetch_assoc()): ?>
<div class="marking-block"><strong><?=htmlspecialchars($t['title'])?></strong><br><?=nl2br(htmlspecialchars($t['message']))?><br><a href="?delete=<?=$t['id']?>" onclick="return confirm('Delete?')">Delete</a></div>
<?php endwhile; ?>
</div><div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>