<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$pending = $conn->query("SELECT a.promised_at, e.question, n.title as note_title, n.id as note_id
    FROM exercise_attempts a
    JOIN note_exercises e ON a.exercise_id = e.id
    JOIN notes n ON e.note_id = n.id
    WHERE a.user_id = $uid AND a.status = 'paper_pending'
    ORDER BY a.promised_at ASC");
?>
<!DOCTYPE html><html><head><title>Pending Paper Exercises</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    

<div class="container">
<div class="content-grid">
<?php while($p = $pending->fetch_assoc()):
    $deadline = strtotime($p['promised_at']) + 86400; // 24h
    $remaining = $deadline - time();
    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
?>
    <div class="card">
        <h3><?=htmlspecialchars($p['note_title'])?></h3>
        <p><strong>Exercise:</strong> <?=nl2br(htmlspecialchars($p['question']))?></p>
        <p><strong>Promised on:</strong> <?=$p['promised_at']?></p>
        <?php if ($remaining > 0): ?>
            <p><strong>Time left:</strong> <?=$hours?>h <?=$minutes?>m</p>
        <?php else: ?>
            <p class="error">⚠️ Deadline passed! Please contact admin immediately.</p>
        <?php endif; ?>
        <a href="view_note.php?id=<?=$p['note_id']?>" class="btn">Go to Note</a>
    </div>
<?php endwhile; ?>
</div></div><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>