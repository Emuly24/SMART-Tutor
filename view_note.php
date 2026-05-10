<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];

// Check if user is admin
$is_admin = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
$role = $_SESSION['role'] ?? '';

$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

// Content lock check for students (admin bypass)
if (!$is_admin && !is_content_unlocked('note', $note_id, $uid)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Content Locked</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card error">
            <h2>🔒 Content Locked</h2>
            <p>This note is not yet available for your group. Please wait until the admin unlocks it after your group meeting.</p>
            <div class="card-buttons">
                <a href="library.php" class="btn-back">← Back to Library</a>
            </div>
        </div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <?php include_once 'includes/testimonial_prompt.php'; ?>
    </body></html>
    <?php
    exit;
}

// Log the view activity (only after confirming the note is accessible)
if (function_exists('log_activity')) {
    log_activity($uid, "view_note", "Note ID: $note_id");
}

// --------------------- ADMIN ACTIONS ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    // Edit note
    if (isset($_POST['edit_note'])) {
        header("Location: admin_note_editor.php?id=$note_id");
        exit;
    }

    // Resend notification to all unlocked groups
    if (isset($_POST['resend_notification'])) {
        $unlocked_groups = $conn->query("
            SELECT g.id FROM groups g
            JOIN group_content_locks l ON g.id = l.group_id
            WHERE l.content_type = 'note' AND l.content_id = $note_id AND l.is_locked = 0
        ");
        while ($ug = $unlocked_groups->fetch_assoc()) {
            $members = $conn->query("SELECT user_id FROM group_members WHERE group_id = {$ug['id']}");
            while ($m = $members->fetch_assoc()) {
                $msg_text = "📘 A new note '{$note['title']}' has been unlocked for your group. Check it out!";
                $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$m['user_id']}, '$msg_text')");
            }
        }
        $admin_success = "Notification resent to all unlocked groups.";
    }
}

// --------------------- STUDENT EXERCISE HANDLING (unchanged) ---------------------
// (Keep all your existing exercise submission code here – digital, paper promise, etc.)
// I've included it below for completeness, but it's identical to your earlier file.

// Handle digital submission (text / file)
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
<!DOCTYPE html>
<html><head><title><?=htmlspecialchars($note['title'])?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
</head>
<body><div class="container">

<!-- ========== ADMIN ACTIONS (visible only to admin) ========== -->
<?php if ($is_admin): ?>
    <div style="margin: 1rem 0; display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <form method="post">
            <button type="submit" name="edit_note" class="btn">✏️ Edit Note</button>
        </form>
        <form method="post">
            <button type="submit" name="resend_notification" class="btn btn-secondary">📨 Resend Notification</button>
        </form>
        <a href="admin_group_locks.php?content_type=note&content_id=<?= $note_id ?>&class_level=<?= $note['class_level'] ?>&route=sciences" class="btn btn-secondary">🔒 Manage Locks</a>
        <?php if (isset($admin_success)): ?>
            <div class="success" style="width:100%; margin-top:0.5rem;"><?= htmlspecialchars($admin_success) ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="note-container"><?=$note['content']?></div>

<?php
// Quiz link (available anytime after reading, only if note is unlocked)
$quiz = $conn->query("SELECT id FROM quizzes WHERE note_id = $note_id LIMIT 1")->fetch_assoc();
if ($quiz && is_content_unlocked('quiz', $quiz['id'], $uid)):
    $attempt = $conn->query("SELECT id, status FROM quiz_attempts WHERE user_id = $uid AND quiz_id = {$quiz['id']} LIMIT 1")->fetch_assoc();
    $quiz_link = ($attempt && $attempt['status'] == 'submitted') ? "quiz_results.php?quiz_id={$quiz['id']}" : "take_quiz.php?quiz_id={$quiz['id']}";
    $button_text = ($attempt && $attempt['status'] == 'submitted') ? "View Quiz Results" : "Take Quiz";
?>
<div class="card" style="margin: 20px 0; background: #f0f7ff; border-left: 5px solid var(--accent);">
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

<div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
</div>
<script>mermaid.initialize({startOnLoad:true});</script>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>