<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) die("Access denied");
$conn = getDB();
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $conn->query("SELECT * FROM quizzes WHERE id=$quiz_id")->fetch_assoc();
if (!$quiz) die("Quiz not found.");
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id ORDER BY sort_order");
?>
<!DOCTYPE html><html><head><title>Edit Questions</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container">
<?php while($q=$questions->fetch_assoc()): ?>
<div class="card"><strong>Q<?=$q['sort_order']?>:</strong> <?=nl2br(htmlspecialchars($q['question_text']))?> (<?=$q['points']?> pts)<br><a href="?delete_question=<?=$q['id']?>" onclick="return confirm('Delete?')">Delete</a></div>
<?php endwhile; ?>
<p><a href="admin_manage_quiz.php?note_id=<?=$quiz['note_id']?>">Back to Quiz Manager</a></p></div><div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>