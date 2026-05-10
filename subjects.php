<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level'];

// Get distinct subjects from notes that are unlocked for the student's group
$subjects = $conn->query("SELECT DISTINCT n.subject 
    FROM notes n 
    WHERE n.class_level='$class' 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='note' AND gcl.content_id=n.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY n.subject");
?>
<!DOCTYPE html>
<html><head><title>Subjects - SMART Circle</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php while($s = $subjects->fetch_assoc()): ?>
                <div class="card">
                    <i class="fas fa-chalkboard"></i>
                    <h3><?= htmlspecialchars($s['subject']) ?></h3>
                    <div class="card-buttons">
                        <a href="subject.php?subject=<?= urlencode($s['subject']) ?>">Explore Subject</a>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php if($subjects->num_rows == 0): ?>
                <div class="card">
                    <p>No subjects available for your group yet. Please wait for admin to unlock content.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer">
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>