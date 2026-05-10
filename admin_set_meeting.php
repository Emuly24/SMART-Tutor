<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = (int)$_POST['group_id'];
    $meeting_date = $_POST['meeting_date'];
    $start_time = $_POST['start_time'];
    
    $check = $conn->query("SELECT id FROM group_meetings WHERE group_id = $group_id AND meeting_date = '$meeting_date'");
    if ($check->num_rows) {
        $conn->query("UPDATE group_meetings SET start_time = '$start_time' WHERE group_id = $group_id AND meeting_date = '$meeting_date'");
        $msg = "Meeting time updated.";
    } else {
        $conn->query("INSERT INTO group_meetings (group_id, meeting_date, start_time) VALUES ($group_id, '$meeting_date', '$start_time')");
        $msg = "Meeting time set.";
    }
    
    // Get group details and members to send notification
    $group = $conn->query("SELECT g.class_level, g.group_number, g.route FROM groups g WHERE id = $group_id")->fetch_assoc();
    $members = $conn->query("SELECT u.id, u.fullname FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = $group_id");
    $notification = "📅 Meeting scheduled for {$group['class_level']} – Group {$group['group_number']} ({$group['route']}) on $meeting_date at $start_time. Please be punctual!";
    while ($m = $members->fetch_assoc()) {
        $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$m['id']}, '$notification')");
    }
}

$groups = $conn->query("SELECT g.id, g.class_level, g.group_number, g.route, 
    (SELECT start_time FROM group_meetings WHERE group_id = g.id AND meeting_date = CURDATE()) as today_start
    FROM groups g ORDER BY g.class_level, g.route, g.group_number");
?>
<!DOCTYPE html>
<html><head><title>Set Group Meeting Time</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Set Group Meeting Time</h1>
        <?php if ($msg): ?>
            <div class="success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <div class="content-grid">
            <?php while($g = $groups->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?> (<?= ucfirst($g['route']) ?>)</h3>
                    <?php if ($g['today_start']): ?>
                        <p>Today's meeting time: <?= date('h:i A', strtotime($g['today_start'])) ?></p>
                    <?php else: ?>
                        <p>No meeting scheduled for today.</p>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                        <div class="form-group">
                            <label>Meeting Date (default today)</label>
                            <input type="date" name="meeting_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" value="<?= $g['today_start'] ?: '14:00' ?>" required>
                        </div>
                        <button type="submit" class="btn">Set Meeting Time</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>