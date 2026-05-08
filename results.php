<?php
require_once 'check_remember_me.php';

require_once 'config.php'; require_once 'check_access.php'; $conn=getDB(); $uid=$_SESSION['user_id']; $submissions=$conn->query("SELECT e.id, e.title, e.subject, s.total_score, s.status FROM exam_submissions s JOIN exams e ON s.exam_id=e.id WHERE s.user_id=$uid AND s.status IN('submitted','marked') ORDER BY s.end_time DESC"); ?>
<!DOCTYPE html><html><head><title>My Results</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><div class="content-grid"><?php while($r=$submissions->fetch_assoc()):?><div class="card"><h3><?=htmlspecialchars($r['title'])?> (<?=$r['subject']?>)</h3><p>Score: <?=($r['total_score']!==null)?$r['total_score']:'Pending'?></p><a href="exam_results.php?exam_id=<?=$r['id']?>">Details</a></div><?php endwhile;?></div><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>