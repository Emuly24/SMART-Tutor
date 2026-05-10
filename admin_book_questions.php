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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_question'])) {
    $qid = (int)$_POST['qid'];
    $answer = trim($_POST['answer']);
    $conn->query("UPDATE book_questions SET answer = '$answer', status = 'answered' WHERE id = $qid");
    // Notify student
    $q = $conn->query("SELECT user_id, book_title FROM book_questions WHERE id = $qid")->fetch_assoc();
    $msg = "Your question about \"{$q['book_title']}\" has been answered. Please check your notifications.";
    $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$q['user_id']}, '$msg')");
    header("Location: admin_book_questions.php");
    exit;
}
$questions = $conn->query("SELECT q.*, u.fullname FROM book_questions q JOIN users u ON q.user_id = u.id ORDER BY q.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Book Questions</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Student Questions from Books</h1>
    <div class="content-grid">
        <?php while($q = $questions->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($q['book_title']) ?> (Page <?= $q['page_number'] ?>)</h3>
                <p><strong>Student:</strong> <?= htmlspecialchars($q['fullname']) ?></p>
                <p><strong>Selected text:</strong> <em>"<?= nl2br(htmlspecialchars($q['selected_text'])) ?>"</em></p>
                <p><strong>Question:</strong> <?= nl2br(htmlspecialchars($q['question'])) ?></p>
                <?php if ($q['status'] == 'answered' && $q['answer']): ?>
                    <p><strong>Answer:</strong> <?= nl2br(htmlspecialchars($q['answer'])) ?></p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="qid" value="<?= $q['id'] ?>">
                        <textarea name="answer" rows="3" placeholder="Write your answer here..." required></textarea>
                        <button type="submit" name="answer_question" class="btn-success">Submit Answer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php if ($questions->num_rows == 0): ?>
            <div class="card"><p>No questions yet.</p></div>
        <?php endif; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
</body></html>