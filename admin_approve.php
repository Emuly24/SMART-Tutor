<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();

$msg = '';
if (isset($_POST['app_id'])) {
    $app_id = (int)$_POST['app_id'];
    $app = $conn->query("SELECT u.id as uid, u.class_level, u.gender, u.route 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = $app_id")->fetch_assoc();
    
    if ($_POST['action'] == 'approve') {
        $class = $app['class_level'];
        $gender = $app['gender'];
        $route = $app['route'];
        
        // Check if admin selected a manual route
        $manual_route = trim($_POST['manual_route'] ?? '');
        if (!empty($manual_route) && in_array($manual_route, ['sciences', 'humanities'])) {
            $route = $manual_route;
            $conn->query("UPDATE users SET route = '$route' WHERE id = {$app['uid']}");
        }
        
        if (empty($class)) {
            $msg = "Student class level is missing.";
        } elseif (empty($route)) {
            $msg = "Student route not determined and no manual route provided.";
        } else {
            // Balanced group selection: order by current member count ascending (fill smallest groups first)
            $available_group = null;
            $groups = $conn->query("SELECT g.id, g.group_number, 
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as current_count,
                (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = g.id AND u.gender='Male') as male_count,
                (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = g.id AND u.gender='Female') as female_count
                FROM groups g 
                WHERE g.class_level = '$class' AND g.route = '$route' 
                ORDER BY current_count ASC, g.group_number ASC");
            while ($grp = $groups->fetch_assoc()) {
                $male_ok = ($gender == 'Male') ? ($grp['male_count'] < 2) : true;
                $female_ok = ($gender == 'Female') ? ($grp['female_count'] < 3) : true;
                if ($grp['current_count'] < 5 && $male_ok && $female_ok) {
                    $available_group = $grp['id'];
                    break;
                }
            }
            if (!$available_group) {
                $msg = "No available group for {$class} ({$route}, {$gender}). All groups are full or gender limit reached.";
            } else {
                $conn->query("INSERT INTO group_members (user_id, group_id) VALUES ({$app['uid']}, $available_group)");
                $conn->query("UPDATE applications SET status='approved' WHERE id=$app_id");
                $conn->query("UPDATE users SET approved=1 WHERE id={$app['uid']}");
                $app_data = $conn->query("SELECT ambition, university, target_points FROM applications WHERE user_id={$app['uid']}")->fetch_assoc();
                $motivation = "Congratulations! Your application is approved. Remember your goal: to become {$app_data['ambition']} at {$app_data['university']} with {$app_data['target_points']} points. We believe in you!";
                $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$app['uid']}, '$motivation')");
                $msg = "Approved and assigned to group (balanced).";
            }
        }
    } else { // REJECT
        $rejection_reason = trim($_POST['rejection_reason'] ?? 'No specific reason provided.');
        $conn->query("UPDATE applications SET status='rejected', admin_notes='$rejection_reason' WHERE id=$app_id");
        $msg = "Rejected. Reason saved.";
    }
    header("Location: admin_approve.php?msg=" . urlencode($msg));
    exit;
}
$pending = $conn->query("SELECT a.*, u.fullname, u.phone, u.class_level, u.id as uid, u.gender, u.route 
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.status='pending'");
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html><head><title>Approve Applications</title><link rel="stylesheet" href="style.css"></head>
<body class="admin-page">
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Pending Applications</h1>
    <?php if($msg) echo "<div class='success'>$msg</div>"; ?>
    <?php if($pending->num_rows == 0): ?>
        <div class="card"><p>No pending applications.</p></div>
    <?php else: ?>
        <?php while($r = $pending->fetch_assoc()): 
            $class = $r['class_level'];
            $route = $r['route'];
            $groups_info = $conn->query("SELECT g.group_number, 
                (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as cnt,
                (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Male') as males,
                (SELECT COUNT(*) FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = g.id AND u.gender='Female') as females
                FROM groups g WHERE g.class_level = '$class' AND g.route = '$route' ORDER BY g.group_number");
        ?>
            <div class="card">
                <h3><?= htmlspecialchars($r['fullname']) ?></h3>
                <p>Class: <?= $r['class_level'] ?> | Gender: <?= $r['gender'] ?> | Route: <?= ucfirst($r['route']) ?> | Phone: <?= $r['phone'] ?><br>
                Target Points: <?= $r['target_points'] ?> | Ambition: <?= htmlspecialchars($r['ambition']) ?></p>
                <p><strong>Group status (max 5 per group, 2M/3F):</strong></p>
                <ul>
                <?php while($g = $groups_info->fetch_assoc()): ?>
                    <li>Group <?= $g['group_number'] ?>: <?= $g['cnt'] ?>/5 members (<?= $g['males'] ?>M / <?= $g['females'] ?>F)</li>
                <?php endwhile; ?>
                </ul>
                <form method="post">
                    <input type="hidden" name="app_id" value="<?= $r['id'] ?>">
                    <div class="form-group">
                        <label>Override Route (optional)</label>
                        <select name="manual_route">
                            <option value="">-- Auto (current: <?= ucfirst($r['route'] ?? 'not set') ?>) --</option>
                            <option value="sciences">Sciences</option>
                            <option value="humanities">Humanities</option>
                        </select>
                    </div>
                    <button type="submit" name="action" value="approve" class="btn-success">Approve</button>
                    <br><br>
                    <label>Rejection reason (if rejecting):</label>
                    <textarea name="rejection_reason" rows="2" style="width:100%"></textarea>
                    <br>
                    <button type="submit" name="action" value="reject" class="btn-danger">Reject</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>