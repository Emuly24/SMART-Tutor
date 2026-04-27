<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

// Check if note is unlocked for this student's group
if (!is_content_unlocked('note', $note_id, $uid)) {
    die("<!DOCTYPE html><html><head><title>Content Locked</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Content Locked</h1><a href='library.php'>Library</a><a href='logout.php' class='logout'>Logout</a></div><div class='error'>This note is not yet available for your group. Please wait until the admin unlocks it after your group meeting.</div><a href='library.php'>← Back to Library</a></div></body></html>");
}

// Handle digital submission (text / file) – same as before
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_digital'])) {
    $ex_id = (int)$_POST['exercise_id'];
    $answer_text = trim($_POST['answer_text'] ?? '');
    $file_path = null;
    if (isset($_FILES['answer_file']) && $_FILES['answer_file']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/exercises/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['answer_file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','pdf','txt'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "exercise_{$ex_id}_user_{$uid}_".time().".$ext";
            if (move_uploaded_file($_FILES['answer_file']['tmp_name'], $dir.$filename)) {
                $file_path = $dir.$filename;
            }
        }
    }
    if (empty($answer_text) && !$file_path) {
        $error = "Please provide an answer (text or file).";
    } else {
        $stmt = $conn->prepare("INSERT INTO exercise_attempts (exercise_id, user_id, answer_text, answer_file_path, status) 
            VALUES (?, ?, ?, ?, 'digital_pending')
            ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), answer_file_path = VALUES(answer_file_path), status = 'digital_pending', updated_at = NOW()");
        $stmt->bind_param("iiss", $ex_id, $uid, $answer_text, $file_path);
        $stmt->execute();
        $success = "Digital answer submitted! Admin will mark it soon.";
    }
}

// Handle "submit on paper" (promise)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_paper'])) {
    $ex_id = (int)$_POST['exercise_id'];
    $promised_at = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO exercise_attempts (exercise_id, user_id, status, promised_at) 
        VALUES ($ex_id, $uid, 'paper_pending', '$promised_at')
        ON DUPLICATE KEY UPDATE status = 'paper_pending', promised_at = '$promised_at', reminder_sent = 0, warning_sent = 0, suspended_for_exercise = 0");
    $success = "You have promised to submit this exercise on paper. You can continue reading. Please submit within 24 hours.";
    header("Location: view_note.php?id=$note_id&msg=paper_promised");
    exit;
}

// Fetch exercises with current status and attempts
$exercises = $conn->query("SELECT e.*, a.answer_text, a.answer_file_path, a.marks_awarded, a.feedback, a.status, a.promised_at 
    FROM note_exercises e 
    LEFT JOIN exercise_attempts a ON e.id = a.exercise_id AND a.user_id = $uid
    WHERE e.note_id = $note_id ORDER BY e.sort_order");

$all_attempted = true;
while ($ex = $exercises->fetch_assoc()) {
    if ($ex['status'] !== 'marked' && empty($ex['answer_text']) && empty($ex['answer_file_path']) && $ex['status'] !== 'paper_pending') {
        $all_attempted = false;
        break;
    }
}
$exercises->data_seek(0);

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'paper_promised') $msg = "Thank you. Your promise to submit on paper has been recorded.";
?>
<!DOCTYPE html><html><head><title><?=htmlspecialchars($note['title'])?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
</head>
<body><div class="container"><div class="header"><h1><?=htmlspecialchars($note['title'])?></h1><a href="library.php">Library</a><a href="logout.php" class="logout">Logout</a></div>
<div class="note-container"><?=$note['content']?></div>

<?php
// Quiz link (available anytime after reading, only if note is unlocked)
$quiz = $conn->query("SELECT id FROM quizzes WHERE note_id = $note_id LIMIT 1")->fetch_assoc();
if ($quiz && is_content_unlocked('quiz', $quiz['id'], $uid)):
    $attempt = $conn->query("SELECT id, status FROM quiz_attempts WHERE user_id = $uid AND quiz_id = {$quiz['id']} LIMIT 1")->fetch_assoc();
    $quiz_link = ($attempt && $attempt['status'] == 'submitted') ? "quiz_results.php?quiz_id={$quiz['id']}" : "take_quiz.php?quiz_id={$quiz['id']}";
    $button_text = ($attempt && $attempt['status'] == 'submitted') ? "View Quiz Results" : "Take Quiz";
?>
<div class="card" style="margin: 20px 0; background: #f0f7ff; border-left: 5px solid var(--gold);">
    <h3>📌 Test Your Understanding</h3>
    <p>Take the quiz to check if you truly understand this topic. You can take it anytime.</p>
    <a href="<?= $quiz_link ?>" class="btn"><?= $button_text ?></a>
</div>
<?php endif; ?>

<h2>📝 Exercises</h2>
<?php if (isset($error)) echo "<div class='error'>$error</div>"; 
      if (isset($success)) echo "<div class='success'>$success</div>";
      if ($msg) echo "<div class='success'>$msg</div>"; ?>

<?php while($ex = $exercises->fetch_assoc()): ?>
<div class="exercise">
    <strong>Exercise <?=$ex['sort_order']?></strong> (<?=$ex['points']?> pts)<br>
    <?=nl2br(htmlspecialchars($ex['question']))?>
    
    <?php if ($ex['status'] == 'marked'): ?>
        <div class="student-answer">
            <strong>Your answer:</strong><br>
            <?=nl2br(htmlspecialchars($ex['answer_text']))?>
            <?php if ($ex['answer_file_path']): ?>
                <br><a href="download.php?type=exercise&file=<?=urlencode(basename($ex['answer_file_path']))?>" target="_blank">View uploaded file</a>
            <?php endif; ?>
            <br><strong>Marked:</strong> <?=$ex['marks_awarded']?>/<?=$ex['points']?> points
            <br><strong>Feedback:</strong> <?=htmlspecialchars($ex['feedback'])?>
        </div>
    <?php elseif ($ex['status'] == 'paper_pending'): ?>
        <div class="warning">
            ✅ You promised to submit this exercise on paper. Deadline: <?=date('Y-m-d H:i:s', strtotime($ex['promised_at'].' +24 hours'))?><br>
            Please bring your written answer to the admin.
        </div>
    <?php elseif (!empty($ex['answer_text']) || !empty($ex['answer_file_path'])): ?>
        <div class="student-answer">
            <strong>Your answer:</strong><br>
            <?=nl2br(htmlspecialchars($ex['answer_text']))?>
            <?php if ($ex['answer_file_path']): ?>
                <br><a href="download.php?type=exercise&file=<?=urlencode(basename($ex['answer_file_path']))?>" target="_blank">View uploaded file</a>
            <?php endif; ?>
            <br><em>Waiting for marking.</em>
        </div>
    <?php else: ?>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
            <form method="post" enctype="multipart/form-data" style="flex:1;">
                <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
                <div class="form-group"><label>Your answer (text)</label><textarea name="answer_text" rows="2"></textarea></div>
                <div class="form-group"><label>OR upload file (image, PDF, text)</label><input type="file" name="answer_file" accept=".jpg,.png,.pdf,.txt"></div>
                <button type="submit" name="submit_digital">Submit Digital Answer</button>
            </form>
            <form method="post" style="flex:0;">
                <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
                <button type="submit" name="submit_paper" class="btn btn-secondary" style="background:#f39c12;">I will submit on paper</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php endwhile; ?>

<div class="footer"><a href="library.php">← Back to Library</a></div>
</div>
<script>mermaid.initialize({startOnLoad:true});</script>
</body></html>