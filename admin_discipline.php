<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
}
$conn = getDB();
$uid = $_GET['user_id'] ?? 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $reason = $_POST['reason'];
    $sus_end = $_POST['suspension_end'] ?? null;
    $conn->query("INSERT INTO discipline_log (user_id,action,reason,suspension_end,admin_notes) VALUES ($uid,'$action','$reason','$sus_end','{$_POST['admin_notes']}')");
    if ($action == 'suspension') $conn->query("UPDATE users SET status='suspended', suspension_end='$sus_end' WHERE id=$uid");
    elseif ($action == 'dismissal') $conn->query("UPDATE users SET status='dismissed', suspension_end=NULL WHERE id=$uid");
    else $conn->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$uid");
    header("Location: admin_discipline.php?user_id=$uid");
    exit;
}
$students = $conn->query("SELECT id,fullname,class_level,status FROM users WHERE approved=1 ORDER BY fullname");
$student = null;
if ($uid) $student = $conn->query("SELECT fullname,class_level,status,suspension_end FROM users WHERE id=$uid")->fetch_assoc();
$history = $uid ? $conn->query("SELECT * FROM discipline_log WHERE user_id=$uid ORDER BY created_at DESC") : null;
?>
<!DOCTYPE html><html><head><title>Discipline</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_discipline</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>⚖️ Discipline Management</h1><form method="get"><select name="user_id"><option value="">-- Select Student --</option><?php while($s=$students->fetch_assoc()):?><option value="<?=$s['id']?>" <?=($uid==$s['id'])?'selected':''?>><?=htmlspecialchars($s['fullname'])?> (<?=$s['class_level']?>)</option><?php endwhile;?></select><button type="submit">Select</button></form><?php if($student):?><h2><?=htmlspecialchars($student['fullname'])?> (<?=$student['class_level']?>) - Status: <?=$student['status']?> <?php if($student['suspension_end']) echo "until ".$student['suspension_end'];?></h2><h3>History</h3><ul><?php while($h=$history->fetch_assoc()):?><li><?=$h['created_at']?> - <?=strtoupper($h['action'])?>: <?=htmlspecialchars($h['reason'])?><?php if($h['suspension_end']) echo " (until $h[suspension_end])";?></li><?php endwhile;?></ul><form method="post"><input type="hidden" name="user_id" value="<?=$uid?>"><label>Action:</label><select name="action"><option value="warning">Warning</option><option value="suspension">Suspension</option><option value="dismissal">Dismissal</option></select><label>Reason:</label><input type="text" name="reason" required><label>Suspension end (if suspension):</label><input type="date" name="suspension_end"><label>Admin notes:</label><textarea name="admin_notes"></textarea><button type="submit">Apply</button></form><?php endif;?><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>