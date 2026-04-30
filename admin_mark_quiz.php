<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) die("Access denied");
$conn = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $ans_id = (int)$_POST['answer_id'];
    $marks = (int)$_POST['marks'];
    $conn->query("UPDATE quiz_answers SET points_awarded=$marks, is_correct=".($marks>0?1:0)." WHERE id=$ans_id");
    // Recalculate total score for the attempt
    $attempt_id = $conn->query("SELECT attempt_id FROM quiz_answers WHERE id=$ans_id")->fetch_assoc()['attempt_id'];
    $new_total = $conn->query("SELECT SUM(points_awarded) FROM quiz_answers WHERE attempt_id=$attempt_id")->fetch_row()[0];
    $conn->query("UPDATE quiz_attempts SET score=$new_total WHERE id=$attempt_id");
    header("Location: admin_mark_quiz.php?quiz_id=".$_GET['quiz_id']);
    exit;
}
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $conn->query("SELECT title FROM quizzes WHERE id=$quiz_id")->fetch_assoc();
$pending = $conn->query("SELECT a.id as attempt_id, u.fullname, qa.id as answer_id, qa.user_answer, qq.question_text, qq.points 
    FROM quiz_attempts a 
    JOIN users u ON a.user_id=u.id 
    JOIN quiz_answers qa ON a.id=qa.attempt_id 
    JOIN quiz_questions qq ON qa.question_id=qq.id 
    WHERE qq.quiz_id=$quiz_id AND qq.question_type='short_answer' AND qa.points_awarded=0");
?>
<!DOCTYPE html><html><head><title>Mark Quiz Short Answers</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container">
<?php while($row = $pending->fetch_assoc()): ?>
<div class="card"><strong><?=htmlspecialchars($row['fullname'])?></strong><br><strong>Question:</strong> <?=nl2br(htmlspecialchars($row['question_text']))?><br><strong>Student's answer:</strong> <?=nl2br(htmlspecialchars($row['user_answer']))?><br><form method="post"><input type="hidden" name="answer_id" value="<?=$row['answer_id']?>"><label>Marks (max <?=$row['points']?>):</label><input type="number" name="marks" min="0" max="<?=$row['points']?>" required><button type="submit" name="save_marks">Save</button></form></div>
<?php endwhile; ?>
</div><div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

</body></html>