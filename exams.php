<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
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
<!DOCTYPE html><html><head><title>Exams</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>📝 Available Exams</h1><a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div><div class="content-grid"><?php while($e=$exams->fetch_assoc()):?><div class="card"><h3><?=htmlspecialchars($e['title'])?> (<?=$e['subject']?>)</h3><p><?=$e['description']?></p><p>Duration: <?=$e['duration_minutes']?> min</p><?php if($e['status']=='in_progress') echo "<a href='take_exam.php?exam_id={$e['id']}'>Continue</a>"; elseif($e['status']=='submitted') echo "<a href='exam_results.php?exam_id={$e['id']}'>View Results</a>"; else echo "<a href='take_exam.php?exam_id={$e['id']}'>Start Exam</a>";?></div><?php endwhile;?></div><div class="footer"><a href="dashboard.php">← Back</a></div></div></body></html>