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

// Fetch existing attempts
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

<!-- ===== MathJax CONFIG ===== -->
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

    /* ---- AUTO-LOCKING SYSTEM CSS ---- */
    .exercise-block {
        position: relative;
        margin: 2rem 0;
        padding: 1.5rem;
        border-radius: 1rem;
        transition: all 0.5s ease;
        border: 1px solid var(--border);
    }
    .exercise-block.locked {
        opacity: 0.4;
        pointer-events: none;
        user-select: none;
        filter: blur(2px);
        position: relative;
    }
    .exercise-block.locked::before {
        content: "🔒 This section is locked. Complete the previous exercise to unlock.";
        display: block;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255,255,255,0.95);
        padding: 1rem 2rem;
        border-radius: 1rem;
        font-size: 1.1rem;
        font-weight: bold;
        color: var(--error);
        border: 2px solid var(--error);
        z-index: 10;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        width: 90%;
        max-width: 500px;
        text-align: center;
    }
    .exercise-block.unlocked {
        opacity: 1;
        pointer-events: auto;
        user-select: auto;
        filter: none;
    }
    .exercise-block.unlocked::before {
        display: none;
    }
    .exercise-block.completed {
        border-left: 5px solid var(--success);
        background: #f0fdf4;
    }
    
    /* ---- FLOATING BUTTONS ---- */
    .floating-actions {
        display: none;
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1000;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 1rem;
        flex-direction: column;
        gap: 0.8rem;
        min-width: 220px;
        transition: all 0.3s ease;
        border: 2px solid var(--accent);
    }
    .floating-actions.visible {
        display: flex;
    }
    .floating-actions .btn {
        width: 100%;
        margin: 0;
        font-size: 0.9rem;
    }
    .floating-actions .btn-secondary {
        background: var(--border);
    }
    .floating-actions .btn-paper {
        background: #f39c12;
        color: white;
    }
    .floating-actions .btn-paper:hover {
        background: #e67e22;
    }
    .floating-actions .btn-submit {
        background: var(--success);
        color: white;
    }
    .floating-actions .btn-submit:hover {
        background: #1b8a3a;
    }
    .floating-actions .text-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
    }
    .floating-actions .file-input {
        font-size: 0.8rem;
    }
    .floating-actions .feedback {
        font-size: 0.9rem;
        text-align: center;
    }
    .exercise-indicator {
        font-weight: bold;
        text-align: center;
        color: var(--accent);
        margin-bottom: 0.5rem;
    }

    @media (max-width: 600px) {
        .floating-actions {
            right: 1rem;
            bottom: 1rem;
            min-width: 160px;
            padding: 0.8rem;
        }
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

<!-- ===== FLOATING ACTION BUTTONS ===== -->
<div id="floatingActions" class="floating-actions">
    <div class="exercise-indicator" id="exerciseIndicator">📝 Exercise</div>
    <form id="digitalForm" method="post" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:0.5rem;">
        <input type="hidden" name="exercise_id" id="activeExerciseId" value="">
        <textarea name="answer_text" class="text-input" rows="2" placeholder="Type your answer here..."></textarea>
        <input type="file" name="answer_file" class="file-input" accept=".jpg,.png,.pdf,.txt">
        <button type="submit" name="submit_digital" class="btn btn-submit">💻 Submit Digital</button>
    </form>
    <form id="paperForm" method="post" style="display:flex; flex-direction:column; gap:0.5rem;">
        <input type="hidden" name="exercise_id" id="activeExerciseIdPaper" value="">
        <button type="submit" name="submit_paper" class="btn btn-paper">📄 I will submit on paper</button>
    </form>
    <div id="floatingFeedback" class="feedback"></div>
</div>

<?php include_once 'includes/footer.php'; ?>
<script>
    const currentNoteId = <?php echo $note_id; ?>;
    const exerciseAttempts = <?php echo json_encode($exercise_attempts); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('main-container');
        const floatingActions = document.getElementById('floatingActions');
        const exerciseIndicator = document.getElementById('exerciseIndicator');
        const activeExerciseIdInput = document.getElementById('activeExerciseId');
        const activeExerciseIdPaperInput = document.getElementById('activeExerciseIdPaper');
        const digitalForm = document.getElementById('digitalForm');
        const paperForm = document.getElementById('paperForm');
        const floatingFeedback = document.getElementById('floatingFeedback');

        // ===== 1. AUTO-DETECT EXERCISES =====
        // Look for headings that contain "Exercise" and extract the form after them
        const exerciseNodes = [];
        const headings = container.querySelectorAll('h3, h4');
        headings.forEach(heading => {
            const text = heading.textContent.trim();
            if (text.toLowerCase().startsWith('exercise')) {
                // Find the form associated with this exercise
                let form = heading.nextElementSibling;
                while (form && !form.matches('form')) {
                    form = form.nextElementSibling;
                }
                if (form) {
                    const idInput = form.querySelector('input[name="exercise_id"]');
                    if (idInput) {
                        const id = parseInt(idInput.value);
                        exerciseNodes.push({
                            id: id,
                            heading: heading,
                            form: form,
                            section: heading.parentElement // Wrap the whole block
                        });
                    }
                }
            }
        });

        // Fallback: look for data-exercise-key forms
        if (exerciseNodes.length === 0) {
            const forms = container.querySelectorAll('form[data-exercise-key]');
            forms.forEach(form => {
                const idInput = form.querySelector('input[name="exercise_id"]');
                if (idInput) {
                    exerciseNodes.push({
                        id: parseInt(idInput.value),
                        heading: null,
                        form: form,
                        section: form.closest('div') || form.parentElement
                    });
                }
            });
        }

        // If still no exercises, hide floating buttons
        if (exerciseNodes.length === 0) {
            floatingActions.style.display = 'none';
            return;
        }

        // ===== 2. WRAP AND LOCK SECTIONS =====
        // Group content into exercise blocks
        let currentBlock = document.createElement('div');
        currentBlock.className = 'exercise-block';
        let currentId = null;
        let currentIndex = 0;

        // We'll traverse the container and split at exercise boundaries
        const children = Array.from(container.childNodes);
        const blocks = [];
        let tempBlock = document.createElement('div');
        tempBlock.className = 'exercise-block';
        let foundFirstExercise = false;

        children.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE && node.textContent.trim().toLowerCase().startsWith('exercise')) {
                // Check if this node is an exercise heading
                const isExerciseHeading = exerciseNodes.some(ex => ex.heading === node);
                if (isExerciseHeading) {
                    if (foundFirstExercise) {
                        // Save the current block
                        blocks.push(tempBlock);
                        tempBlock = document.createElement('div');
                        tempBlock.className = 'exercise-block';
                    }
                    foundFirstExercise = true;
                    tempBlock.appendChild(node.cloneNode(true));
                    return;
                }
            }
            if (foundFirstExercise) {
                tempBlock.appendChild(node.cloneNode(true));
            }
        });
        if (foundFirstExercise && tempBlock.children.length > 0) {
            blocks.push(tempBlock);
        }

        // If we found blocks, replace the container content
        if (blocks.length > 0) {
            container.innerHTML = '';
            blocks.forEach((block, index) => {
                // Find the exercise ID for this block
                let foundId = null;
                const forms = block.querySelectorAll('form');
                forms.forEach(form => {
                    const idInput = form.querySelector('input[name="exercise_id"]');
                    if (idInput) foundId = parseInt(idInput.value);
                });
                block.dataset.exerciseId = foundId || index;
                block.dataset.index = index;
                container.appendChild(block);
            });
        }

        // ===== 3. APPLY INITIAL LOCK STATES =====
        const exerciseBlocks = container.querySelectorAll('.exercise-block');
        let currentExerciseIndex = -1;

        // Find the first incomplete exercise
        exerciseBlocks.forEach((block, index) => {
            const id = parseInt(block.dataset.exerciseId);
            const status = exerciseAttempts[id]?.status || 'not_attempted';
            if (status === 'marked' || status === 'paper_pending') {
                block.classList.add('completed', 'unlocked');
                block.classList.remove('locked');
            } else {
                // First incomplete exercise
                if (currentExerciseIndex === -1) {
                    currentExerciseIndex = index;
                    block.classList.add('unlocked');
                    block.classList.remove('locked');
                } else {
                    block.classList.add('locked');
                    block.classList.remove('unlocked');
                }
            }
        });

        // If all completed, unlock the last one
        if (exerciseBlocks.length > 0 && currentExerciseIndex === -1) {
            exerciseBlocks[exerciseBlocks.length - 1].classList.add('unlocked');
            exerciseBlocks[exerciseBlocks.length - 1].classList.remove('locked');
            currentExerciseIndex = exerciseBlocks.length - 1;
        }

        // ===== 4. INTERSECTION OBSERVER FOR SCROLLING =====
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const block = entry.target;
                    const id = parseInt(block.dataset.exerciseId);
                    const status = exerciseAttempts[id]?.status || 'not_attempted';

                    if (status === 'marked' || status === 'paper_pending') {
                        return; // Already completed
                    }

                    // Show floating buttons
                    floatingActions.classList.add('visible');
                    activeExerciseIdInput.value = id;
                    activeExerciseIdPaperInput.value = id;
                    exerciseIndicator.textContent = `📝 Exercise ${id}`;
                    floatingFeedback.innerHTML = '';

                    // Ensure this block is unlocked
                    block.classList.remove('locked');
                    block.classList.add('unlocked');

                    // Lock all subsequent blocks
                    let lockNext = false;
                    exerciseBlocks.forEach(b => {
                        const bId = parseInt(b.dataset.exerciseId);
                        if (bId === id) {
                            lockNext = true;
                        } else if (lockNext) {
                            b.classList.add('locked');
                            b.classList.remove('unlocked');
                        }
                    });
                }
            });
        }, { threshold: 0.4 });

        exerciseBlocks.forEach(block => {
            observer.observe(block);
        });

        // ===== 5. HANDLE SUBMISSIONS =====
        digitalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const exId = parseInt(activeExerciseIdInput.value);
            const formData = new FormData(this);
            const text = formData.get('answer_text')?.trim() || '';
            const file = formData.get('answer_file');

            if (!text && (!file || file.size === 0)) {
                floatingFeedback.innerHTML = '❌ Please provide an answer (text or file).';
                floatingFeedback.style.color = '#ef4444';
                return;
            }

            floatingFeedback.innerHTML = '⏳ Submitting...';
            floatingFeedback.style.color = '#f59e0b';

            fetch('student_view_note.php?id=' + currentNoteId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Digital answer submitted!') || data.includes('success')) {
                    floatingFeedback.innerHTML = '✅ Submitted!';
                    floatingFeedback.style.color = '#22c55e';
                    exerciseAttempts[exId] = { status: 'digital_pending' };
                    // Unlock next block
                    let found = false;
                    exerciseBlocks.forEach(block => {
                        const bId = parseInt(block.dataset.exerciseId);
                        if (bId === exId) {
                            found = true;
                            block.classList.add('completed');
                            block.classList.remove('locked');
                            block.classList.add('unlocked');
                        } else if (found) {
                            block.classList.remove('locked');
                            block.classList.add('unlocked');
                            found = false;
                        }
                    });
                    setTimeout(() => {
                        floatingActions.classList.remove('visible');
                        if (window.MathJax) MathJax.typesetPromise();
                    }, 1500);
                } else {
                    floatingFeedback.innerHTML = '❌ Submission failed. Please try again.';
                    floatingFeedback.style.color = '#ef4444';
                }
            })
            .catch(error => {
                console.error(error);
                floatingFeedback.innerHTML = '❌ Network error.';
                floatingFeedback.style.color = '#ef4444';
            });
        });

        paperForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const exId = parseInt(activeExerciseIdPaperInput.value);
            const formData = new FormData(this);

            floatingFeedback.innerHTML = '⏳ Recording promise...';
            floatingFeedback.style.color = '#f59e0b';

            fetch('student_view_note.php?id=' + currentNoteId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('promised to submit') || data.includes('success')) {
                    floatingFeedback.innerHTML = '✅ Promise recorded!';
                    floatingFeedback.style.color = '#22c55e';
                    exerciseAttempts[exId] = { status: 'paper_pending' };
                    // Unlock next block
                    let found = false;
                    exerciseBlocks.forEach(block => {
                        const bId = parseInt(block.dataset.exerciseId);
                        if (bId === exId) {
                            found = true;
                            block.classList.add('completed');
                            block.classList.remove('locked');
                            block.classList.add('unlocked');
                        } else if (found) {
                            block.classList.remove('locked');
                            block.classList.add('unlocked');
                            found = false;
                        }
                    });
                    setTimeout(() => {
                        floatingActions.classList.remove('visible');
                        if (window.MathJax) MathJax.typesetPromise();
                    }, 1500);
                } else {
                    floatingFeedback.innerHTML = '❌ Promise failed. Please try again.';
                    floatingFeedback.style.color = '#ef4444';
                }
            })
            .catch(error => {
                console.error(error);
                floatingFeedback.innerHTML = '❌ Network error.';
                floatingFeedback.style.color = '#ef4444';
            });
        });
    });
</script>
</body></html>