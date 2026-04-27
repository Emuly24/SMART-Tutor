<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $conn->query("SELECT * FROM quizzes WHERE id=$quiz_id")->fetch_assoc();
if (!$quiz) die("Quiz not found.");
if (!is_content_unlocked('quiz', $quiz_id, $uid)) {
    die("<!DOCTYPE html><html><head><title>Quiz Locked</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Quiz Locked</h1><a href='dashboard.php'>Dashboard</a><a href='logout.php' class='logout'>Logout</a></div><div class='error'>This quiz is not yet available for your group. Please wait until the admin unlocks it.</div><a href='dashboard.php'>← Back to Dashboard</a></div></body></html>");
}
$note = $conn->query("SELECT title FROM notes WHERE id={$quiz['note_id']}")->fetch_assoc();

// Check or create attempt
$attempt = $conn->query("SELECT * FROM quiz_attempts WHERE user_id=$uid AND quiz_id=$quiz_id")->fetch_assoc();
if (!$attempt) {
    $conn->query("INSERT INTO quiz_attempts (user_id, quiz_id) VALUES ($uid, $quiz_id)");
    $attempt = $conn->query("SELECT * FROM quiz_attempts WHERE user_id=$uid AND quiz_id=$quiz_id")->fetch_assoc();
}
if ($attempt['status'] == 'submitted') {
    die("You have already submitted this quiz. <a href='quiz_results.php?quiz_id=$quiz_id'>View results</a>");
}
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id ORDER BY sort_order");
$time_limit = $quiz['time_limit'] * 60;
$start = strtotime($attempt['started_at']);
$now = time();
$remaining = $start + $time_limit - $now;
if ($remaining <= 0) {
    echo "<script>alert('Time is up! Submitting...'); window.location='submit_quiz.php?quiz_id=$quiz_id';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Take Quiz</title><link rel="stylesheet" href="style.css"><script>let remaining=<?=$remaining?>; function timer(){if(remaining<=0){document.getElementById('timer').innerHTML="Submitting..."; window.location='submit_quiz.php?quiz_id=<?=$quiz_id?>';} let mins=Math.floor(remaining/60); let secs=remaining%60; document.getElementById('timer').innerHTML=`Time left: ${mins}m ${secs}s`; remaining--; setTimeout(timer,1000);} window.onload=timer;</script></head><body><div class="container"><div class="header"><h1>📝 <?=htmlspecialchars($quiz['title'])?></h1><div id="timer" style="background:#e74c3c;color:white;padding:5px 10px;border-radius:20px;"></div></div>
<form method="post" action="submit_quiz.php">
<input type="hidden" name="quiz_id" value="<?=$quiz_id?>">
<?php while($q=$questions->fetch_assoc()): ?>
<div class="card"><strong><?=htmlspecialchars($q['question_text'])?></strong> (<?=$q['points']?> pts)<br>
<?php if($q['question_type']=='multiple_choice'): $opts=json_decode($q['options'],true); foreach($opts as $opt):?>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>"> <?=htmlspecialchars($opt)?></label><br>
<?php endforeach; ?>
<?php elseif($q['question_type']=='true_false'): ?>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="True"> True</label><br>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="False"> False</label>
<?php else: ?>
<textarea name="answer[<?=$q['id']?>]" rows="2"></textarea>
<?php endif; ?></div>
<?php endwhile; ?>
<button type="submit">Submit Quiz</button>
</form></div></body></html>