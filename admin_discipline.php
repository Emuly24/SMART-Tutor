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
$uid = $_GET['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $reason = $_POST['reason'];
    $sus_end = $_POST['suspension_end'] ?? null;
    $conn->query("INSERT INTO discipline_log (user_id,action,reason,suspension_end,admin_notes) VALUES ($uid,'$action','$reason','$sus_end','{$_POST['admin_notes']}')");
    if ($action == 'suspension') {
    $conn->query("UPDATE users SET status='suspended', suspension_end='$sus_end' WHERE id=$uid");
} elseif ($action == 'dismissal') {
    $conn->query("UPDATE users SET status='dismissed', suspension_end=NULL WHERE id=$uid");
    // Remove student from any group
    $conn->query("DELETE FROM group_members WHERE user_id = $uid");
} else {
    $conn->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$uid");
}
    header("Location: admin_discipline.php?user_id=$uid");
    exit;
}
$students = $conn->query("SELECT id,fullname,class_level,status FROM users WHERE approved=1 ORDER BY fullname");
$student = null;
if ($uid) $student = $conn->query("SELECT fullname,class_level,status,suspension_end FROM users WHERE id=$uid")->fetch_assoc();
$history = $uid ? $conn->query("SELECT * FROM discipline_log WHERE user_id=$uid ORDER BY created_at DESC") : null;
?>
<!DOCTYPE html><html><head><title>Discipline</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <form method="get">
                <select name="user_id">
                    <option value="">-- Select Student --</option>
                    <?php while($s=$students->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>" <?= ($uid==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['fullname']) ?> (<?= $s['class_level'] ?>)</option>
                    <?php endwhile; ?>
                </select>
                <button type="submit">Select</button>
            </form>
            <?php if($student): ?>
                <div class="card">
                    <h2><?= htmlspecialchars($student['fullname']) ?> (<?= $student['class_level'] ?>)</h2>
                    <p>Status: <?= ucfirst($student['status']) ?> <?php if($student['suspension_end']) echo "until ".$student['suspension_end']; ?></p>
                    <h3>Discipline History</h3>
                    <?php if($history->num_rows == 0): ?>
                        <p>No previous actions.</p>
                    <?php else: ?>
                        <ul>
                        <?php while($h=$history->fetch_assoc()): ?>
                            <li><?= $h['created_at'] ?> - <?= strtoupper($h['action']) ?>: <?= htmlspecialchars($h['reason']) ?><?php if($h['suspension_end']) echo " (until $h[suspension_end])"; ?></li>
                        <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <div class="form-group"><label>Action:</label><select name="action" class="form-control"><option value="warning">Warning</option><option value="suspension">Suspension</option><option value="dismissal">Dismissal</option></select></div>
                        <div class="form-group"><label>Reason:</label><input type="text" name="reason" class="form-control" required></div>
                        <div class="form-group"><label>Suspension end (if suspension):</label><input type="date" name="suspension_end" class="form-control"></div>
                        <div class="form-group"><label>Admin notes:</label><textarea name="admin_notes" class="form-control"></textarea></div>
                        <button type="submit" class="btn">Apply</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>