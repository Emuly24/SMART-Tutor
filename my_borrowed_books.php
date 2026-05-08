<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$borrowed = $conn->query("SELECT * FROM borrowed_books WHERE user_id = $uid AND returned_at IS NULL ORDER BY due_date ASC");
?>
<!DOCTYPE html>
<html><head><title>My Borrowed Books</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>My Borrowed Books</h1>
        <?php if ($borrowed->num_rows == 0): ?>
            <div class="card"><p>You have not borrowed any books.</p></div>
        <?php else: ?>
            <div class="grid">
                <?php while($b = $borrowed->fetch_assoc()): 
                    $overdue = (strtotime($b['due_date']) < time());
                ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($b['book_title']) ?></h3>
                        <p><strong>Subject:</strong> <?= htmlspecialchars($b['subject']) ?></p>
                        <p><strong>Borrowed:</strong> <?= date('d M Y', strtotime($b['borrowed_at'])) ?></p>
                        <p><strong>Due date:</strong> <?= date('d M Y', strtotime($b['due_date'])) ?>
                            <?php if ($overdue): ?>
                                <span class="error">(Overdue)</span>
                            <?php endif; ?>
                        </p>
                        <div class="card-buttons">
                            <a href="read_book.php?id=<?= $b['book_id'] ?>" class="btn" target="_blank">📖 Read Online</a>
                            <a href="return_book.php?id=<?= $b['book_id'] ?>" class="btn-warning" onclick="return confirm('Return this book?')">↩️ Return</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        <div class="footer"><a href="library.php" class="btn-back">← Back to Library</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>