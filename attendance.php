<?php
require_once 'config.php'; require_once 'check_access.php'; $conn=getDB(); $uid=$_SESSION['user_id']; $att=$conn->query("SELECT date,status,remarks FROM attendance WHERE user_id=$uid ORDER BY date DESC LIMIT 30"); $disc=$conn->query("SELECT action,reason,suspension_end,created_at FROM discipline_log WHERE user_id=$uid ORDER BY created_at DESC"); ?>
<!DOCTYPE html><html><head><title>Attendance</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><h2>Attendance (last 30 days)</h2><table class="data-table"><?php while($r=$att->fetch_assoc()):?><tr><td><?=$r['date']?></td><td><?=$r['status']?></td><td><?=$r['remarks']?></td></tr><?php endwhile;?></table><h2>Discipline History</h2><table class="data-table"><?php while($d=$disc->fetch_assoc()):?><tr><td><?=$d['created_at']?></td><td><?=strtoupper($d['action'])?></td><td><?=$d['reason']?></td></tr><?php endwhile;?></table><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>