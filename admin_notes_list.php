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
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM notes WHERE id=$id");
    header("Location: admin_notes_list.php");
    exit;
}
$notes = $conn->query("SELECT id,title,subject,class_level,created_at FROM notes ORDER BY created_at DESC");
?>
<!DOCTYPE html><html><head><title>Manage Notes</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Manage Notes</h1><a href="admin_note_editor.php" class="btn">+ New Note</a></div>
        <?php if($notes->num_rows == 0): ?>
            <div class="card"><p>No notes yet. Click "New Note" to create one.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Title</th><th>Subject</th><th>Class</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($n=$notes->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['title']) ?></td>
                        <td><?= $n['subject'] ?></td>
                        <td><?= $n['class_level'] ?></td>
                        <td><?= $n['created_at'] ?></td>
                        <td class="card-buttons"><a href="view_note.php?id=<?= $n['id'] ?>" target="_blank">View</a> | <a href="admin_edit_note.php?id=<?= $n['id'] ?>">Edit</a> | <a href="?delete=<?= $n['id'] ?>" onclick="return confirm('Delete?')" class="btn-danger">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>