<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $conn->query("SELECT * FROM quizzes WHERE id=$quiz_id")->fetch_assoc();
if (!$quiz) die("Quiz not found.");
if (!is_content_unlocked('quiz', $quiz_id, $uid)) {
    die("<!DOCTYPE html><html><head><title>Quiz Locked</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Quiz Locked</h1><a href='dashboard.php'>Dashboard</a><a href='logout.php' class='logout'>Logout</a></div><div class='error'>This quiz is not yet available for your group.</div><a href='dashboard.php'>← Back</a></div></body></html>");
}
$attempt = $conn->query("SELECT * FROM quiz_attempts WHERE user_id=$uid AND quiz_id=$quiz_id")->fetch_assoc();
if (!$attempt) die("No attempt found.");
$answers = $conn->query("SELECT qq.question_text, qq.question_type, qa.user_answer, qa.points_awarded, qq.points, qa.is_correct 
    FROM quiz_answers qa 
    JOIN quiz_questions qq ON qa.question_id=qq.id 
    WHERE qa.attempt_id={$attempt['id']}");
$total_points = 0;
$earned = 0;
while($a=$answers->fetch_assoc()){ $total_points+=$a['points']; $earned+=$a['points_awarded']; }
$percentage = $total_points ? round($earned/$total_points*100) : 0;
$passed = $percentage >= $quiz['passing_percentage'];
?>
<!DOCTYPE html><html><head><title>Quiz Results</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1><?=htmlspecialchars($quiz['title'])?> Results</h1><a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="card"><h3>Your Score: <?=$earned?>/<?=$total_points?> (<?=$percentage?>%)</h3><p><?=$passed ? "✅ Passed" : "❌ Failed"?></p></div>
<h2>Detailed Answers</h2>
<div class="content-grid">
<?php $answers->data_seek(0); while($a=$answers->fetch_assoc()): ?>
<div class="card"><strong><?=nl2br(htmlspecialchars($a['question_text']))?></strong><br>Your answer: <?=nl2br(htmlspecialchars($a['user_answer']))?><br>Marks: <?=$a['points_awarded']?>/<?=$a['points']?></div>
<?php endwhile; ?>
</div>
<a href="view_note.php?id=<?=$quiz['note_id']?>">Back to Note</a>
</div></body></html>