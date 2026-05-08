<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
if (!$subject) die("No subject selected.");

$score = null;
$submitted = false;
$questions_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    $submitted = true;
    $total = 0;
    $correct = 0;
    foreach ($_POST['answers'] as $qid => $ans) {
        $q = $conn->query("SELECT correct_answer, explanation FROM self_quizzes WHERE id = " . (int)$qid)->fetch_assoc();
        if ($q) {
            $total++;
            $is_correct = ($ans == $q['correct_answer']);
            if ($is_correct) $correct++;
            $questions_data[$qid] = [
                'correct' => $is_correct,
                'explanation' => $q['explanation']
            ];
        }
    }
    $score = "$correct / $total";
    // Store attempt
    $conn->query("INSERT INTO self_quiz_attempts (user_id, subject, score, total_questions) VALUES ($uid, '$subject', $correct, $total)");
}

// Fetch random questions
$questions = $conn->query("SELECT * FROM self_quizzes WHERE subject = '$subject' ORDER BY RAND() LIMIT 5");
if ($questions->num_rows == 0) die("No questions available for this subject.");
?>
<!DOCTYPE html>
<html><head><title>Self‑Assessment Quiz – <?= htmlspecialchars($subject) ?></title>
<link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <h1>Self‑Assessment: <?= htmlspecialchars($subject) ?></h1>
    <?php if ($submitted): ?>
        <div class="success">You scored <?= $score ?></div>
        <?php
        $questions->data_seek(0);
        while($q = $questions->fetch_assoc()):
            $qid = $q['id'];
            $user_ans = $_POST['answers'][$qid] ?? '';
            $is_correct = isset($questions_data[$qid]) && $questions_data[$qid]['correct'];
            $explanation = $questions_data[$qid]['explanation'] ?? '';
        ?>
            <div class="card <?= $is_correct ? 'success' : 'error' ?>" style="border-left: 5px solid <?= $is_correct ? 'green' : 'red' ?>;">
                <p><strong><?= htmlspecialchars($q['question']) ?></strong></p>
                <p>Your answer: <strong><?= htmlspecialchars($user_ans) ?></strong> <?= $is_correct ? '✅' : '❌' ?></p>
                <p>Correct answer: <strong><?= htmlspecialchars($q['correct_answer']) ?></strong></p>
                <p><em>Explanation:</em> <?= nl2br(htmlspecialchars($explanation ?: $q['explanation'])) ?></p>
            </div>
        <?php endwhile; ?>
        <a href="self_quiz.php?subject=<?= urlencode($subject) ?>" class="btn">Take Again</a>
    <?php else: ?>
        <form method="post">
            <?php while($q = $questions->fetch_assoc()): ?>
                <div class="card">
                    <p><strong><?= htmlspecialchars($q['question']) ?></strong></p>
                    <?php foreach (['A','B','C','D'] as $opt): 
                        $opt_val = $q['option_'.strtolower($opt)];
                        if ($opt_val): ?>
                            <label><input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>" required> <?= htmlspecialchars($opt_val) ?></label><br>
                        <?php endif; endforeach; ?>
                </div>
            <?php endwhile; ?>
            <button type="submit" class="btn">Submit Answers</button>
        </form>
    <?php endif; ?>
    <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
</div>
</body></html>