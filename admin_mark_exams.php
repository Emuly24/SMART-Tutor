<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication
if (function_exists('getAdminHash')) {
    $admin_hash = getAdminHash();
} elseif (defined('ADMIN_HASH')) {
    $admin_hash = ADMIN_HASH;
} else {
    $admin_hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
}

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

// Handle marking an answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_answer'])) {
    $aid = (int)$_POST['answer_id'];
    $marks = (int)$_POST['marks'];
    $fb = $_POST['feedback'];
    $conn->query("UPDATE exam_answers SET marks_awarded=$marks, feedback='$fb', marked_by_admin=1 WHERE id=$aid");
    $ans = $conn->query("SELECT exam_id,user_id FROM exam_answers WHERE id=$aid")->fetch_assoc();
    $eid = $ans['exam_id'];
    $uid = $ans['user_id'];
    $total = $conn->query("SELECT SUM(marks_awarded) FROM exam_answers WHERE exam_id=$eid AND user_id=$uid AND marks_awarded IS NOT NULL")->fetch_row()[0];
    if ($total !== null) $conn->query("UPDATE exam_submissions SET total_score=$total, status='marked' WHERE exam_id=$eid AND user_id=$uid");
    header("Location: admin_mark_exams.php?exam_id=$eid&user_id=$uid");
    exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);
$user_id = (int)($_GET['user_id'] ?? 0);

if ($exam_id && $user_id) {
    $exam = $conn->query("SELECT * FROM exams WHERE id=$exam_id")->fetch_assoc();
    $student = $conn->query("SELECT fullname FROM users WHERE id=$user_id")->fetch_assoc();
    $answers = $conn->query("SELECT q.id as qid, q.question_text, q.points, a.id as answer_id, a.answer_text, a.answer_file_path, a.marks_awarded, a.feedback FROM exam_questions q LEFT JOIN exam_answers a ON q.id=a.question_id AND a.exam_id=$exam_id AND a.user_id=$user_id WHERE q.exam_id=$exam_id ORDER BY q.sort_order");
    ?>
    <!DOCTYPE html>
    <html><head><title>Mark Exam</title><link rel="stylesheet" href="style.css"></head><body>
        <?php include_once 'includes/header.php'; ?>
        <div class="container">
            <div class="content-grid">
                <?php while($a=$answers->fetch_assoc()): ?>
                    <div>
                        <strong><?=nl2br(htmlspecialchars($a['question_text']))?></strong> (<?=$a['points']?> pts)<br>
                        <em>Answer:</em> <?=nl2br(htmlspecialchars($a['answer_text']))?>
                        <?php if($a['answer_file_path']) echo "<br><a href='admin_download.php?type=exam&file=" . urlencode(basename($a['answer_file_path'])) . "' target='_blank'>View file</a>"; ?>
                        <?php if($a['marks_awarded']!==null): ?>
                            <p>Marked: <?=$a['marks_awarded']?>/<?=$a['points']?> | Feedback: <?=htmlspecialchars($a['feedback'])?></p>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="answer_id" value="<?=$a['answer_id']?>">
                                <label>Marks (max <?=$a['points']?>):</label>
                                <input type="number" name="marks" min="0" max="<?=$a['points']?>" required>
                                <label>Feedback:</label>
                                <input type="text" name="feedback">
                                <button type="submit" name="mark_answer" class="btn">Save Marks</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <hr>
                <?php endwhile; ?>
                <a href="admin_mark_exams.php?exam_id=<?=$exam_id?>" class="btn-back">Back to students</a>
            </div>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
        <a href="#" class="back-to-top" id="backToTop">↑</a>
    </body></html>
    <?php
    exit;
}

if ($exam_id) {
    $students = $conn->query("SELECT u.id, u.fullname, s.status, s.total_score FROM exam_submissions s JOIN users u ON s.user_id=u.id WHERE s.exam_id=$exam_id AND s.status='submitted'");
    $examTitle = $conn->query("SELECT title FROM exams WHERE id=$exam_id")->fetch_assoc()['title'];
    ?>
    <!DOCTYPE html>
    <html><head><title>Submissions for <?= htmlspecialchars($examTitle) ?></title><link rel="stylesheet" href="style.css"></head><body>
        <?php include_once 'includes/header.php'; ?>
        <div class="container">
            <h2><?= htmlspecialchars($examTitle) ?> – Submissions</h2>
            <div class="content-grid">
                <?php if($students->num_rows == 0): ?>
                    <div class="card"><p>No submissions for this exam yet.</p></div>
                <?php else: ?>
                    <?php while($s=$students->fetch_assoc()): ?>
                        <div class="card">
                            <a href="admin_mark_exams.php?exam_id=<?=$exam_id?>&user_id=<?=$s['id']?>"><?=htmlspecialchars($s['fullname'])?></a>
                            (Score: <?=$s['total_score'] ?? 'pending'?>)
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <a href="admin_mark_exams.php" class="btn-back">Back to all exams</a>
            </div>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
        <a href="#" class="back-to-top" id="backToTop">↑</a>
    </body></html>
    <?php
    exit;
}

// Main exam list
$exams = $conn->query("SELECT e.id, e.title, e.subject, (SELECT COUNT(DISTINCT user_id) FROM exam_submissions WHERE exam_id=e.id AND status='submitted') as pending FROM exams e ORDER BY e.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Mark Exams</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Mark Exams</h1>
        <div class="content-grid">
            <?php if($exams->num_rows == 0): ?>
                <div class="card"><p>No exams have been created yet.</p></div>
            <?php else: ?>
                <?php while($e=$exams->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($e['title']) ?></h3>
                        <p><?= $e['subject'] ?></p>
                        <?php if($e['pending'] > 0): ?>
                            <a href="admin_mark_exams.php?exam_id=<?= $e['id'] ?>" class="btn">View students (<?= $e['pending'] ?> pending)</a>
                        <?php else: ?>
                            <p class="text-muted">No submissions yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>