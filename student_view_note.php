<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

if (!is_content_unlocked('note', $note_id, $uid)) {
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
    <?php include_once 'includes/testimonial_prompt.php'; ?>
    </body></html>
    <?php
    exit;
}

if (function_exists('log_activity')) {
    log_activity($uid, "view_note", "Note ID: $note_id");
}

// --------------------- STUDENT EXERCISE HANDLING ---------------------
$error = $success = '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_paper'])) {
    $ex_id = (int)$_POST['exercise_id'];
    $promised_at = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO exercise_attempts (exercise_id, user_id, status, promised_at) 
        VALUES ($ex_id, $uid, 'paper_pending', '$promised_at')
        ON DUPLICATE KEY UPDATE status = 'paper_pending', promised_at = '$promised_at', reminder_sent = 0, warning_sent = 0, suspended_for_exercise = 0");
    $success = "You have promised to submit this exercise on paper. You can continue reading. Please submit within 24 hours.";
    header("Location: student_view_note.php?id=$note_id&msg=paper_promised");
    exit;
}

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'paper_promised') $msg = "Thank you. Your promise to submit on paper has been recorded.";

// Fetch existing attempts to check which sections are unlocked
$exercise_attempts = [];
$ex_result = $conn->query("SELECT e.id, e.sort_order, a.status, a.answer_text FROM note_exercises e LEFT JOIN exercise_attempts a ON e.id = a.exercise_id AND a.user_id = $uid WHERE e.note_id = $note_id");
while ($row = $ex_result->fetch_assoc()) {
    $exercise_attempts[$row['id']] = [
        'status' => $row['status'] ?? 'not_attempted',
        'answer_text' => $row['answer_text'] ?? '',
        'sort_order' => $row['sort_order']
    ];
}
?>
<!DOCTYPE html>
<html><head><title><?=htmlspecialchars($note['title'])?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<!-- ===== FIXED MathJax CONFIG ===== -->
<script>
MathJax = {
    tex: {
        inlineMath: [['$', '$'], ['\\(', '\\)']],
        displayMath: [['$$', '$$'], ['\\[', '\\]']]
    },
    svg: {
        fontCache: 'global'
    }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>

<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    
    .student-note-container {
        max-width: 1000px;
        margin: 2rem auto;
        background: var(--card-bg);
        border-radius: 1rem;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
        border-top: 5px solid var(--accent);
        line-height: 1.8;
        font-size: 1.1rem;
        text-align: inherit;   
    }
    .student-note-container h1, .student-note-container h2, .student-note-container h3 {
        color: var(--accent);
    }
    
    /* ---- Equation Box Styling ---- */
    .equation-box {
        display: block;
        width: 90%;
        max-width: 800px;
        margin: 1.5rem auto;
        padding: 1.5rem;
        background: #f8fafc;
        border: 2px solid #F1C40F;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        text-align: center;
        overflow-x: auto;
    }
    .equation-box .MathJax_Display {
        text-align: center !important;
        margin: 0 !important;
    }
    .equation-box mjx-container {
        display: block !important;
        margin: 0 auto !important;
        text-align: center !important;
    }

    .student-note-container img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
    }
    .student-note-container pre {
        background: var(--card-alt-bg);
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
    }
    .student-note-container blockquote {
        border-left: 4px solid var(--accent);
        padding-left: 1rem;
        margin: 1rem 0;
        color: var(--text-muted);
    }
    .student-note-container table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }
    .student-note-container table th, .student-note-container table td {
        border: 1px solid var(--card-alt-bg);
        padding: 0.5rem;
    }
    .student-note-container table th {
        background: var(--accent);
        color: #1e293b;
    }

    .MathJax_Display {
        text-align: inherit;
    }

    .locked-section-wrapper {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.8s ease, opacity 0.8s ease, margin 0.8s ease;
        margin: 0;
    }
    .locked-section-wrapper.unlocked {
        max-height: 3000px;
        opacity: 1;
        margin: 2rem 0;
    }
    .locked-placeholder {
        background: #f0f4f8;
        padding: 1rem;
        border-radius: 1rem;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        box-shadow: inset 0 0 0 1px var(--border);
        margin: 1.5rem 0;
    }
    .locked-placeholder strong {
        color: var(--error);
    }
    .locked-placeholder .lock-icon {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .locked-section-wrapper .real-content {
        display: none;
    }
    .locked-section-wrapper.unlocked .real-content {
        display: block;
    }
    .locked-section-wrapper.unlocked .locked-placeholder {
        display: none;
    }

    .submit-status {
        font-size: 0.9rem;
        margin-top: 0.5rem;
        color: var(--success);
    }
</style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; flex-wrap:wrap;">
        <h2><?=htmlspecialchars($note['title'])?></h2>
        <a href="library.php" class="btn-back">← Back</a>
    </div>
    <div class="student-note-container">
        <div id="main-container">
            <?=$note['content']?>
        </div>
    </div>

    <?php
    $quiz = $conn->query("SELECT id FROM quizzes WHERE note_id = $note_id LIMIT 1")->fetch_assoc();
    if ($quiz && is_content_unlocked('quiz', $quiz['id'], $uid)):
        $attempt = $conn->query("SELECT id, status FROM quiz_attempts WHERE user_id = $uid AND quiz_id = {$quiz['id']} LIMIT 1")->fetch_assoc();
        $quiz_link = ($attempt && $attempt['status'] == 'submitted') ? "quiz_results.php?quiz_id={$quiz['id']}" : "take_quiz.php?quiz_id={$quiz['id']}";
        $button_text = ($attempt && $attempt['status'] == 'submitted') ? "View Quiz Results" : "Take Quiz";
    ?>
        <div class="card" style="margin: 2rem 0; background: #f0f7ff; border-left: 5px solid var(--accent);">
            <h3>📌 Test Your Understanding</h3>
            <p>Take the quiz to check if you truly understand this topic. You can take it anytime.</p>
            <a href="<?= $quiz_link ?>" class="btn"><?= $button_text ?></a>
        </div>
    <?php endif; ?>

    <h2>📝 Exercises</h2>
    <?php if (isset($error)) echo "<div class='error'>$error</div>"; 
          if (isset($success)) echo "<div class='success'>$success</div>";
          if ($msg) echo "<div class='success'>$msg</div>"; ?>

    <?php
    $exercises = $conn->query("SELECT e.*, a.answer_text, a.answer_file_path, a.marks_awarded, a.feedback, a.status, a.promised_at 
        FROM note_exercises e 
        LEFT JOIN exercise_attempts a ON e.id = a.exercise_id AND a.user_id = $uid
        WHERE e.note_id = $note_id ORDER BY e.sort_order");
    ?>

    <?php while($ex = $exercises->fetch_assoc()): ?>
    <div class="exercise" style="background:var(--card-bg); padding:1.5rem; border-radius:1rem; margin-bottom:1.5rem; box-shadow:var(--card-shadow);">
        <strong>Exercise <?=$ex['sort_order']?></strong> (<?=$ex['points']?> pts)<br>
        <?=nl2br(htmlspecialchars($ex['question']))?>
        
        <?php if ($ex['status'] == 'marked'): ?>
            <div class="student-answer" style="background:var(--card-alt-bg); padding:1rem; border-radius:0.5rem; margin-top:0.5rem;">
                <strong>Your answer:</strong><br>
                <?=nl2br(htmlspecialchars($ex['answer_text']))?>
                <?php if ($ex['answer_file_path']): ?>
                    <br><a href="download.php?type=exercise&file=<?=urlencode(basename($ex['answer_file_path']))?>" target="_blank">View uploaded file</a>
                <?php endif; ?>
                <br><strong>Marked:</strong> <?=$ex['marks_awarded']?>/<?=$ex['points']?> points
                <br><strong>Feedback:</strong> <?=htmlspecialchars($ex['feedback'])?>
            </div>
        <?php elseif ($ex['status'] == 'paper_pending'): ?>
            <div class="warning" style="margin-top:0.5rem;">
                ✅ You promised to submit this exercise on paper. Deadline: <?=date('Y-m-d H:i:s', strtotime($ex['promised_at'].' +24 hours'))?><br>
                Please bring your written answer to the admin.
            </div>
        <?php elseif (!empty($ex['answer_text']) || !empty($ex['answer_file_path'])): ?>
            <div class="student-answer" style="background:var(--card-alt-bg); padding:1rem; border-radius:0.5rem; margin-top:0.5rem;">
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
                    <div class="form-group"><label>Your answer (text)</label><textarea name="answer_text" rows="2"><?=htmlspecialchars($ex['answer_text'] ?? '')?></textarea></div>
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
</div>
<?php include_once 'includes/footer.php'; ?>
<script>
    const currentNoteId = <?php echo $note_id; ?>;

    function unlockSection(sectionId) {
        const wrapper = document.getElementById(sectionId);
        if (wrapper) {
            wrapper.classList.add('unlocked');
        }
    }

    function submitExercise(event, exerciseKey, nextSectionId) {
        event.preventDefault();
        const form = event.target;
        const textarea = form.querySelector('textarea');
        const feedbackDiv = document.getElementById(exerciseKey + '-feedback');

        if (!textarea.value.trim() || textarea.value.trim().length < 5) {
            if (feedbackDiv) {
                feedbackDiv.innerHTML = '❌ Please write a full working for all questions.';
                feedbackDiv.style.color = '#ef4444';
            }
            return;
        }

        if (feedbackDiv) {
            feedbackDiv.innerHTML = '⏳ Submitting...';
            feedbackDiv.style.color = '#f59e0b';
        }

        const formData = new FormData(form);
        formData.append('submit_digital', '1');

        fetch('student_view_note.php?id=' + currentNoteId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('Digital answer submitted!') || data.includes('success')) {
                feedbackDiv.innerHTML = '✅ Exercise submitted successfully! You may proceed.';
                feedbackDiv.style.color = '#22c55e';
                unlockSection(nextSectionId);
                if (window.MathJax) {
                    MathJax.typesetPromise();
                }
            } else {
                feedbackDiv.innerHTML = '❌ Submission failed. Please try again.';
                feedbackDiv.style.color = '#ef4444';
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            feedbackDiv.innerHTML = '❌ Network error. Please check your connection and try again.';
            feedbackDiv.style.color = '#ef4444';
        });
    }

    // ---------- AUTO-UNLOCK ON LOAD ----------
    document.addEventListener('DOMContentLoaded', function() {
        const exerciseStatus = <?php echo json_encode($exercise_attempts); ?>;

        const wrappers = document.querySelectorAll('.locked-section-wrapper');
        wrappers.forEach(wrapper => {
            const form = wrapper.querySelector('form[data-exercise-key]');
            if (form) {
                const exerciseIdInput = form.querySelector('input[name="exercise_id"]');
                if (exerciseIdInput) {
                    const exerciseId = parseInt(exerciseIdInput.value);
                    // Check if this exercise has already been submitted/marked
                    if (exerciseStatus[exerciseId] && 
                       (exerciseStatus[exerciseId].status === 'marked' || 
                        exerciseStatus[exerciseId].status === 'paper_pending')) {
                        unlockSection(wrapper.id);
                    }
                }
            }
        });

        // Force MathJax to render any equations in the newly unlocked sections
        if (window.MathJax) {
            MathJax.typesetPromise().catch(() => {});
        }
    });

    mermaid.initialize({startOnLoad:true});
</script>
</body></html>s