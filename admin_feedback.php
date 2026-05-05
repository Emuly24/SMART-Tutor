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
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();

// Mark as read
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $conn->query("UPDATE student_messages SET status='read' WHERE id=$id");
    header("Location: admin_feedback.php");
    exit;
}

// Delete message
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM student_messages WHERE id=$id");
    header("Location: admin_feedback.php");
    exit;
}

// Fetch all messages, newest first
$messages = $conn->query("SELECT m.*, u.fullname, u.class_level, u.phone 
    FROM student_messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Student Feedback</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
<div class="container">
    
    
    <?php if ($messages->num_rows == 0): ?>
        <div class="card"><p>No messages yet.</p></div>
    <?php else: ?>
        <div class="grid">
        <?php while($m = $messages->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($m['subject']) ?></h3>
                <p><strong>From:</strong> <?= htmlspecialchars($m['fullname']) ?> (<?= $m['class_level'] ?>)<br>
                <strong>Phone:</strong> <?= $m['phone'] ?><br>
                <strong>Sent:</strong> <?= $m['created_at'] ?><br>
                <strong>Status:</strong> <?= $m['status'] == 'unread' ? '<span style="color:red;">Unread</span>' : 'Read' ?></p>
                <p><strong>Message:</strong><br><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                <div>
                    <?php if ($m['status'] == 'unread'): ?>
                        
                    <?php endif; ?>
    <div class="card-buttons"><a href="?mark_read=<?= $m['id'] ?>" class="btn">Mark as Read</a><a href="?delete=<?= $m['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this message?')">Delete</a></div></div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>