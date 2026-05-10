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
$note_id = (int)($_GET['note_id'] ?? 0);
if (!$note_id) die("No note specified.");
$note = $conn->query("SELECT title FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found.");

if (isset($_GET['delete_quiz'])) {
    $quiz_id = (int)$_GET['delete_quiz'];
    $conn->query("DELETE FROM quizzes WHERE id=$quiz_id");
    header("Location: admin_manage_quiz.php?note_id=$note_id");
    exit;
}
if (isset($_GET['delete_question'])) {
    $qid = (int)$_GET['delete_question'];
    $conn->query("DELETE FROM quiz_questions WHERE id=$qid");
    header("Location: admin_manage_quiz.php?note_id=$note_id");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $quiz_id = (int)$_POST['quiz_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $time = (int)$_POST['time_limit'];
    $pass = (int)$_POST['passing_percentage'];
    if ($quiz_id) {
        $conn->query("UPDATE quizzes SET title='$title', description='$desc', time_limit=$time, passing_percentage=$pass WHERE id=$quiz_id");
    } else {
        $conn->query("INSERT INTO quizzes (note_id, title, description, time_limit, passing_percentage) VALUES ($note_id, '$title', '$desc', $time, $pass)");
        $quiz_id = $conn->insert_id;
    }
    header("Location: admin_manage_quiz.php?note_id=$note_id");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $quiz_id = (int)$_POST['quiz_id'];
    $qtext = $_POST['question_text'];
    $type = $_POST['question_type'];
    $points = (int)$_POST['points'];
    $order = (int)$_POST['sort_order'];
    $options = null;
    $correct = $_POST['correct_answer'];
    if ($type == 'multiple_choice') {
        $opts = array_filter(array_map('trim', explode("\n", $_POST['options_raw'])));
        $options = json_encode(array_values($opts));
    } elseif ($type == 'true_false') {
        $options = json_encode(['True', 'False']);
    }
    $conn->query("INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer, points, sort_order) 
                  VALUES ($quiz_id, '$qtext', '$type', '$options', '$correct', $points, $order)");
    header("Location: admin_manage_quiz.php?note_id=$note_id");
    exit;
}
$quizzes = $conn->query("SELECT * FROM quizzes WHERE note_id=$note_id");
?>
<!DOCTYPE html><html><head><title>Manage Quiz - <?=htmlspecialchars($note['title'])?></title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Quizzes for <?= htmlspecialchars($note['title']) ?></h1><a href="admin_dashboard.php" class="btn-back">Back</a></div>
        <?php if($quizzes->num_rows == 0): ?>
            <div class="card"><p>No quizzes yet. Create one below.</p></div>
        <?php else: ?>
            <?php while($qz = $quizzes->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($qz['title']) ?></h3>
                    <p><?= nl2br(htmlspecialchars($qz['description'])) ?></p>
                    <p><strong>Time:</strong> <?= $qz['time_limit'] ?> min | <strong>Passing:</strong> <?= $qz['passing_percentage'] ?>%</p>
                    <div class="card-buttons">
                        <a href="?note_id=<?=$note_id?>&delete_quiz=<?=$qz['id']?>" onclick="return confirm('Delete quiz?')" class="btn-danger">Delete Quiz</a>
                        <a href="admin_edit_quiz_questions.php?quiz_id=<?=$qz['id']?>" class="btn">Edit Questions</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        <div class="card"><h2>Create New Quiz</h2>
            <form method="post">
                <input type="hidden" name="quiz_id" value="0">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description"></textarea></div>
                <div class="form-group"><label>Time limit (minutes)</label><input type="number" name="time_limit" value="30"></div>
                <div class="form-group"><label>Passing percentage</label><input type="number" name="passing_percentage" value="70"></div>
                <button type="submit" name="save_quiz" class="btn">Create Quiz</button>
            </form>
        </div>
        <div class="card"><h2>Add Question to Quiz</h2>
            <form method="post">
                <div class="form-group"><label>Select Quiz</label><select name="quiz_id"><?php $qlist=$conn->query("SELECT id,title FROM quizzes WHERE note_id=$note_id"); while($q=$qlist->fetch_assoc()):?><option value="<?=$q['id']?>"><?=htmlspecialchars($q['title'])?></option><?php endwhile;?></select></div>
                <div class="form-group"><label>Question Text</label><textarea name="question_text" rows="3" required></textarea></div>
                <div class="form-group"><label>Type</label><select name="question_type" id="qtype" onchange="toggleOptions()"><option value="multiple_choice">Multiple Choice</option><option value="true_false">True/False</option><option value="short_answer">Short Answer</option></select></div>
                <div id="options_div" style="display:none;"><div class="form-group"><label>Options (one per line)</label><textarea name="options_raw" rows="4"></textarea></div></div>
                <div class="form-group"><label>Correct Answer</label><input type="text" name="correct_answer" required></div>
                <div class="form-group"><label>Points</label><input type="number" name="points" value="5"></div>
                <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="1"></div>
                <button type="submit" name="add_question" class="btn">Add Question</button>
            </form>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <script>function toggleOptions(){var t=document.getElementById('qtype').value; document.getElementById('options_div').style.display=(t=='multiple_choice')?'block':'none';}</script>
</body></html>