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
$exam_id = (int)$_GET['exam_id'];
$exam = $conn->query("SELECT title FROM exams WHERE id=$exam_id")->fetch_assoc();
if (!$exam) die("Invalid exam");

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
    $stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_text, question_type, options, correct_answer, points, sort_order) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssii", $exam_id, $qtext, $type, $options, $correct, $points, $order);
    $stmt->execute();
    echo "<script>alert('Question added'); window.location='admin_add_questions.php?exam_id=$exam_id';</script>";
    exit;
}

// Handle delete
if (isset($_GET['delete_q'])) {
    $qid = (int)$_GET['delete_q'];
    $conn->query("DELETE FROM exam_questions WHERE id=$qid");
    header("Location: admin_add_questions.php?exam_id=$exam_id");
    exit;
}

$questions = $conn->query("SELECT * FROM exam_questions WHERE exam_id=$exam_id ORDER BY sort_order");
?>
<!DOCTYPE html><html><head><title>Add Questions</title><link rel="stylesheet" href="style.css">
<style>
    .question-card { background: var(--card-bg); padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.5rem; box-shadow: var(--card-shadow); border-left: 5px solid var(--accent); }
    .question-card .delete-btn { float: right; background: var(--error); color: white; border: none; padding: 4px 12px; border-radius: 20px; cursor: pointer; }
    .question-card .delete-btn:hover { opacity: 0.8; }
</style>
<script>function toggleOptions(){var t=document.getElementById('qtype').value; document.getElementById('options_div').style.display=(t=='multiple_choice')?'block':'none';}</script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div style="padding: 2rem;">
            <h2>📝 <?= htmlspecialchars($exam['title']) ?> – Manage Questions</h2>
            
            <!-- Existing Questions -->
            <h3>Existing Questions</h3>
            <?php if ($questions->num_rows == 0): ?>
                <p>No questions added yet.</p>
            <?php else: ?>
                <?php while($q = $questions->fetch_assoc()): ?>
                    <div class="question-card">
                        <a href="?delete_q=<?= $q['id'] ?>&exam_id=<?= $exam_id ?>" class="delete-btn" onclick="return confirm('Delete this question?')">✕ Delete</a>
                        <strong>Q<?= $q['sort_order'] ?>:</strong> <?= nl2br(htmlspecialchars($q['question_text'])) ?>
                        <br><small>(<?= $q['points'] ?> pts • <?= ucfirst(str_replace('_', ' ', $q['question_type'])) ?>)</small>
                        <?php if ($q['question_type'] == 'multiple_choice' && $q['options']): ?>
                            <br><small>Options: <?= htmlspecialchars(implode(', ', json_decode($q['options'], true))) ?></small>
                            <br><small>Correct: <strong><?= htmlspecialchars($q['correct_answer']) ?></strong></small>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <!-- Add New Question -->
            <h3>Add New Question</h3>
            <div class="card" style="padding: 2rem;">
                <form method="post">
                    <div class="form-group"><label>Question text</label><textarea name="question_text" rows="3" required></textarea></div>
                    <div class="form-group"><label>Type</label>
                        <select name="question_type" id="qtype" onchange="toggleOptions()">
                            <option value="essay">Essay</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="multiple_choice">Multiple Choice</option>
                        </select>
                    </div>
                    <div id="options_div" style="display:none;">
                        <div class="form-group"><label>Options (one per line)</label><textarea name="options_raw" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3&#10;Option 4"></textarea></div>
                        <div class="form-group"><label>Correct answer (exact match)</label><input type="text" name="correct_answer" placeholder="Type the correct option exactly as written above"></div>
                    </div>
                    <div class="form-group"><label>Points</label><input type="number" name="points" value="5"></div>
                    <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="<?= $questions->num_rows + 1 ?>"></div>
                    <button type="submit" class="btn">Add Question</button>
                </form>
            </div>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>