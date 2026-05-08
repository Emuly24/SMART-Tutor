<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$class = $_SESSION['class_level'];
$uid = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all books for this class (with search)
$sql = "SELECT id, subject, title, file_path FROM books WHERE class_level='$class'";
if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (title LIKE '%$search%' OR subject LIKE '%$search%')";
}
$sql .= " ORDER BY subject, title";
$books = $conn->query($sql);

// Get currently borrowed book IDs by this user
$borrowed = [];
$borrowedRes = $conn->query("SELECT book_id FROM borrowed_books WHERE user_id = $uid AND returned_at IS NULL");
while ($row = $borrowedRes->fetch_assoc()) {
    $borrowed[] = $row['book_id'];
}
$avg_rating = $conn->query("SELECT AVG(rating) as avg FROM book_reviews WHERE book_id = {$b['id']}")->fetch_assoc()['avg'];
if ($avg_rating) echo "<br><small>⭐ " . round($avg_rating,1) . "/5</small>";
?>
<!DOCTYPE html>
<html><head><title>Library - Books</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <!-- Search Bar -->
        <div class="search-bar" style="margin: 1rem 0; display: flex; gap: 0.5rem;">
            <form method="get" style="flex:1; display: flex; gap: 0.5rem;">
                <input type="text" name="search" placeholder="Search by title or subject..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
                <button type="submit" class="btn">🔍 Search</button>
                <?php if ($search): ?>
                    <a href="library.php" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="content-grid">
        <?php
        $current_subject = '';
        while ($b = $books->fetch_assoc()):
            $is_borrowed = in_array($b['id'], $borrowed);
            if ($b['subject'] != $current_subject):
                if ($current_subject != '') echo "</div>";
                echo "<div class='card'><h3>" . htmlspecialchars($b['subject']) . " Books</h3>";
                $current_subject = $b['subject'];
            endif;
        ?>
            <div class="book-item" style="border-bottom:1px solid #eee; padding:0.5rem 0;">
                <strong><?= htmlspecialchars($b['title']) ?></strong>
                <div class="card-buttons" style="margin-top:0.5rem;">
                    
                    <?php if ($is_borrowed): ?>
                        <a href="read_book.php?id=<?= $b['id'] ?>" class="btn" target="_blank">📖 Read Online</a>
                        <a href="return_book.php?id=<?= $b['id'] ?>" class="btn-warning" onclick="return confirm('Return this book?')">↩️ Return</a>
                    <?php else: ?>
                        <a href="borrow_book.php?id=<?= $b['id'] ?>" class="btn-success" onclick="return confirm('Borrow this book for 7 days?')">📚 Borrow (7 days)</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php
        endwhile;
        if ($current_subject != '') echo "</div>";
        if ($books->num_rows == 0) echo "<div class='card'><p>No books found for your class or search term.</p></div>";
        ?>
        </div>
        
        <div style="margin-top: 2rem;">
            <a href="my_borrowed_books.php" class="btn-secondary">📖 My Borrowed Books</a>
        </div>
        <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>