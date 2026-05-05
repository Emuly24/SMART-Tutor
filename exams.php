<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
// Ensure class_level is set
if (!isset($_SESSION['class_level'])) {
    $user = $conn->query("SELECT class_level FROM users WHERE id=$uid")->fetch_assoc();
    $_SESSION['class_level'] = $user['class_level'] ?? 'Form 3';
}
$class = $_SESSION['class_level'];

$exams = $conn->query("SELECT e.*, 
    (SELECT status FROM exam_submissions WHERE exam_id=e.id AND user_id=$uid) as status 
    FROM exams e 
    WHERE e.class_level='$class' 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='exam' AND gcl.content_id=e.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY e.created_at DESC");
?>
<!DOCTYPE html><html><head><title>Exams</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><div class="content-grid"><?php while($e=$exams->fetch_assoc()):?><div class="card"><h3><?=htmlspecialchars($e['title'])?> (<?=$e['subject']?>)</h3><p><?=$e['description']?></p><p>Duration: <?=$e['duration_minutes']?> min</p><?php if($e['status']=='in_progress') echo ""; elseif($e['status']=='submitted') echo ""; else echo "";?>
    <div class="card-buttons"><a href='take_exam.php?exam_id={$e['id']}'>Continue</a><a href='exam_results.php?exam_id={$e['id']}'>View Results</a><a href='take_exam.php?exam_id={$e['id']}'>Start Exam</a></div></div><?php endwhile;?></div><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>