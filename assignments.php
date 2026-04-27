<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level'];

$assignments = $conn->query("SELECT a.*, 
    (SELECT submitted_at FROM assignment_submissions WHERE assignment_id=a.id AND user_id=$uid) as submitted, 
    (SELECT marks FROM assignment_submissions WHERE assignment_id=a.id AND user_id=$uid) as marks 
    FROM assignments a 
    WHERE a.class_level='$class' 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='assignment' AND gcl.content_id=a.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY a.due_date");
?>
<!DOCTYPE html><html><head><title>Assignments</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>📋 Assignments</h1><a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div><div class="content-grid"><?php while($a=$assignments->fetch_assoc()):?><div class="card"><h3><?=htmlspecialchars($a['title'])?> (<?=$a['subject']?>)</h3><p><?=nl2br(htmlspecialchars($a['description']))?></p><?php if($a['attachment_file_path']) echo "<p><a href='admin_download.php?type=assignment&file=".urlencode(basename($a['attachment_file_path']))."' target='_blank'>📎 Download attachment</a></p>";?><p>Due: <?=$a['due_date']?></p><?php if($a['submitted']):?>Submitted on <?=$a['submitted']?>. Marks: <?=($a['marks']!==null)?$a['marks']:'Pending'?><?php else:?><a href="submit_assignment.php?assignment_id=<?=$a['id']?>">Submit</a><?php endif;?></div><?php endwhile;?></div><div class="footer"><a href="dashboard.php">← Back</a></div></div></body></html>