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

if (isset($_GET['update'])) {
    $id = (int)$_GET['id'];
    $status = $_POST['status'];
    $response = $_POST['admin_response'] ?? '';
    $conn->query("UPDATE student_reports SET status='$status', admin_response='$response' WHERE id=$id");
    header("Location: admin_reports.php");
    exit;
}
$reports = $conn->query("SELECT r.*, u.fullname, u.class_level FROM student_reports r JOIN users u ON r.user_id=u.id ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Student Reports</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Student Reports</h1>
        <?php if($reports->num_rows == 0): ?>
            <div class="card"><p>No student reports submitted.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Class</th><th>Type</th><th>Description</th><th>Incident Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($r = $reports->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['fullname']) ?></td>
                        <td><?= $r['class_level'] ?></td>
                        <td><?= $r['report_type'] ?></td>
                        <td><?= nl2br(htmlspecialchars($r['description'])) ?></td>
                        <td><?= $r['incident_date'] ?></td>
                        <td><?= $r['status'] ?></td>
                        <td>
                            <form method="post" action="?update=1&id=<?= $r['id'] ?>">
                                <select name="status">
                                    <option value="pending" <?= ($r['status']=='pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="reviewed" <?= ($r['status']=='reviewed') ? 'selected' : '' ?>>Reviewed</option>
                                    <option value="resolved" <?= ($r['status']=='resolved') ? 'selected' : '' ?>>Resolved</option>
                                </select>
                                <textarea name="admin_response" placeholder="Response"><?= htmlspecialchars($r['admin_response']) ?></textarea>
                                <button type="submit" class="btn">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>