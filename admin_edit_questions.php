<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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
$qid = (int)$_GET['qid'];
$question = $conn->query("SELECT * FROM exam_questions WHERE id=$qid")->fetch_assoc();
if (!$question) die("Question not found");
$exam_id = $question['exam_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtext = $_POST['question_text'];
    $type = $_POST['question_type'];
    $points = (int)$_POST['points'];
    $order = (int)$_POST['sort_order'];
    $options = null;
    $correct = null;
    if ($type == 'multiple_choice') {
        $opts = explode("\n", trim($_POST['options_raw']));
        $options = json_encode(array_filter(array_map('trim', $opts)));
        $correct = trim($_POST['correct_answer']);
    }
    $conn->query("UPDATE exam_questions SET question_text='$qtext', question_type='$type', options='$options', correct_answer='$correct', points=$points, sort_order=$order WHERE id=$qid");
    echo "<script>alert('Question updated'); window.location='admin_add_questions.php?exam_id=$exam_id';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Question</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>✏️ Edit Question</h2>
            <form method="post">
                <div class="form-group"><label>Question text</label><textarea name="question_text" rows="3" required><?= htmlspecialchars($question['question_text']) ?></textarea></div>
                <div class="form-group"><label>Type</label>
                    <select name="question_type" id="qtype" onchange="toggleOptions()">
                        <option value="essay" <?= ($question['question_type'] == 'essay') ? 'selected' : '' ?>>Essay</option>
                        <option value="short_answer" <?= ($question['question_type'] == 'short_answer') ? 'selected' : '' ?>>Short Answer</option>
                        <option value="multiple_choice" <?= ($question['question_type'] == 'multiple_choice') ? 'selected' : '' ?>>Multiple Choice</option>
                    </select>
                </div>
                <div id="options_div" style="display: <?= ($question['question_type'] == 'multiple_choice') ? 'block' : 'none' ?>;">
                    <div class="form-group"><label>Options (one per line)</label><textarea name="options_raw" rows="4"><?php if($question['options']) echo implode("\n", json_decode($question['options'], true)); ?></textarea></div>
                    <div class="form-group"><label>Correct answer</label><input type="text" name="correct_answer" value="<?= htmlspecialchars($question['correct_answer']) ?>"></div>
                </div>
                <div class="form-group"><label>Points</label><input type="number" name="points" value="<?= $question['points'] ?>"></div>
                <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="<?= $question['sort_order'] ?>"></div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    <script>function toggleOptions(){var t=document.getElementById('qtype').value; document.getElementById('options_div').style.display=(t=='multiple_choice')?'block':'none';}</script>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>