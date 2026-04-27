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
$msg = '';
if (isset($_POST['app_id'])) {
    $app_id = (int)$_POST['app_id'];
    $app = $conn->query("SELECT u.id as uid, u.class_level, u.gender FROM applications a JOIN users u ON a.user_id=u.id WHERE a.id=$app_id")->fetch_assoc();
    if ($_POST['action'] == 'approve') {
        $class = $app['class_level'];
        $gender = $app['gender'];
        
        // Find available group for this class and gender (2 males, 3 females per group, max 5 members)
        $available_group = null;
        $groups = $conn->query("SELECT g.id, g.group_number, 
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as current_count,
            (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Male') as male_count,
            (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Female') as female_count
            FROM groups g WHERE g.class_level = '$class' ORDER BY g.group_number ASC");
        
        while ($grp = $groups->fetch_assoc()) {
            $male_ok = ($gender == 'Male') ? ($grp['male_count'] < 2) : true;
            $female_ok = ($gender == 'Female') ? ($grp['female_count'] < 3) : true;
            if ($grp['current_count'] < 5 && $male_ok && $female_ok) {
                $available_group = $grp['id'];
                break;
            }
        }
        
        if (!$available_group) {
            $msg = "No available group for {$class} ($gender). All groups are full or gender limit reached.";
        } else {
            $conn->query("INSERT INTO group_members (user_id, group_id) VALUES ({$app['uid']}, $available_group)");
            $conn->query("UPDATE applications SET status='approved' WHERE id=$app_id");
            $conn->query("UPDATE users SET approved=1 WHERE id={$app['uid']}");
            $msg = "Approved and assigned to group.";
        }
    } else {
        $conn->query("UPDATE applications SET status='rejected' WHERE id=$app_id");
        $msg = "Rejected.";
    }
    header("Location: admin_approve.php?msg=" . urlencode($msg));
    exit;
}
$pending = $conn->query("SELECT a.*, u.fullname, u.phone, u.class_level, u.id as uid, u.gender FROM applications a JOIN users u ON a.user_id=u.id WHERE a.status='pending'");
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html><head><title>Approve Applications</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>Pending Applications</h1><a href="admin_dashboard.php">Dashboard</a></div>
<?php if($msg) echo "<div class='success'>$msg</div>"; while($r=$pending->fetch_assoc()): 
    // Calculate current group counts for display
    $class = $r['class_level'];
    $groups_info = $conn->query("SELECT g.group_number, 
        (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as cnt,
        (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Male') as males,
        (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Female') as females
        FROM groups g WHERE g.class_level = '$class' ORDER BY g.group_number");
    ?>
<div class="card"><h3><?=htmlspecialchars($r['fullname'])?></h3><p>Class: <?=$r['class_level']?> | Gender: <?=$r['gender']?> | Phone: <?=$r['phone']?><br>Target Points: <?=$r['target_points']?> | Ambition: <?=htmlspecialchars($r['ambition'])?></p>
<p><strong>Group status (max 5 per group, 2M/3F):</strong></p><ul><?php while($g=$groups_info->fetch_assoc()):?><li>Group <?=$g['group_number']?>: <?=$g['cnt']?>/5 members (<?=$g['males']?>M / <?=$g['females']?>F)</li><?php endwhile;?></ul>
<form method="post" style="display:inline"><input type="hidden" name="app_id" value="<?=$r['id']?>"><button type="submit" name="action" value="approve">Approve</button><button type="submit" name="action" value="reject">Reject</button></form></div>
<?php endwhile; ?>
</div></body></html>