<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level'];

// Show only unlocked notes for this student's group
$notes = $conn->query("SELECT n.id, n.title, n.subject, n.created_at 
    FROM notes n 
    WHERE n.class_level='$class' 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='note' AND gcl.content_id=n.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY n.subject, n.title");
?>
<!DOCTYPE html>
<html><head><title>Notes</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
<div class="container">
<div class="content-grid">
<?php
$current_subject = '';
while ($n = $notes->fetch_assoc()) {
    if ($n['subject'] != $current_subject) {
        if ($current_subject != '') echo "</div>";
        echo "<div class='card'><h3>" . htmlspecialchars($n['subject']) . "</h3>";
        $current_subject = $n['subject'];
    }
    echo "<p><strong>" . htmlspecialchars($n['title']) . "</strong><br><a href='view_note.php?id={$n['id']}'>Read Note</a></p>";
}
if ($current_subject != '') echo "</div>";
if ($notes->num_rows == 0) echo "<div class='card'><p>No notes available for your group yet.</p></div>";
?>
</div>
<div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>