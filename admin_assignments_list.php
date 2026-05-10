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
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM assignments WHERE id=$id");
    header("Location: admin_assignments_list.php");
    exit;
}
$assignments = $conn->query("SELECT * FROM assignments ORDER BY due_date ASC");
?>
<!DOCTYPE html><html><head><title>Manage Assignments</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Manage Assignments</h1><a href="admin_create_assignment.php" class="btn">+ New Assignment</a></div>
        <?php if($assignments->num_rows == 0): ?>
            <div class="card"><p>No assignments yet. Click "New Assignment" to create one.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Due</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($a=$assignments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['title']) ?></td>
                        <td><?= $a['subject'] ?></td>
                        <td><?= $a['class_level'] ?></td>
                        <td><?= $a['due_date'] ?></td>
                        <td class="card-buttons"><a href="admin_edit_assignment.php?id=<?= $a['id'] ?>">Edit</a> | <a href="?delete=<?= $a['id'] ?>" onclick="return confirm('Delete?')" class="btn-danger">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>