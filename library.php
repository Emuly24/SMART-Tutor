<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$class = $_SESSION['class_level'];

// Fetch books grouped by subject
$books = $conn->query("SELECT subject, title, file_path FROM books WHERE class_level='$class' ORDER BY subject, title");
?>
<!DOCTYPE html>
<html><head><title>Library - Books</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>📚 Library - <?=$class?> Books</h1><a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<?php
$current_subject = '';
while ($b = $books->fetch_assoc()) {
    if ($b['subject'] != $current_subject) {
        if ($current_subject != '') echo "</div>"; // close previous subject group
        echo "<div class='card'><h3>" . htmlspecialchars($b['subject']) . " Books</h3>";
        $current_subject = $b['subject'];
    }
    echo "<p><strong>" . htmlspecialchars($b['title']) . "</strong><br><a href='download.php?type=book&file=" . urlencode(basename($b['file_path'])) . "' target='_blank'>Download</a></p>";
}
if ($current_subject != '') echo "</div>";
if ($books->num_rows == 0) echo "<div class='card'><p>No books available for your class yet.</p></div>";
?>
</div>
<div class="footer"><a href="dashboard.php">← Back</a></div>
</div></body></html>