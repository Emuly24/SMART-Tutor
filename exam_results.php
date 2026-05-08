<?php
require_once 'check_remember_me.php';

require_once 'config.php'; require_once 'check_access.php'; $conn=getDB(); $uid=$_SESSION['user_id']; $exam_id=(int)$_GET['exam_id']; $exam=$conn->query("SELECT title FROM exams WHERE id=$exam_id")->fetch_assoc(); if(!$exam) die("Invalid exam"); $answers=$conn->query("SELECT q.question_text, a.answer_text, a.answer_file_path, a.marks_awarded, q.points, a.feedback FROM exam_questions q LEFT JOIN exam_answers a ON q.id=a.question_id AND a.exam_id=$exam_id AND a.user_id=$uid WHERE q.exam_id=$exam_id ORDER BY q.sort_order"); ?>
<!DOCTYPE html><html><head><title><?=htmlspecialchars($exam['title'])?> Results</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><div class="content-grid"><?php while($a=$answers->fetch_assoc()):?><div class="card"><strong>Q:</strong> <?=nl2br(htmlspecialchars($a['question_text']))?><br><strong>Your answer:</strong> <?=nl2br(htmlspecialchars($a['answer_text']))?><?php if($a['answer_file_path']) echo "<br><a href='download.php?type=exam&file=" . urlencode(basename($a['answer_file_path'])) . "' target='_blank'>View file</a>"; ?><br><strong>Marks:</strong> <?=($a['marks_awarded']!==null)?$a['marks_awarded'].'/'.$a['points']:'Pending'?><br><?php if($a['feedback']) echo "<strong>Feedback:</strong> ".htmlspecialchars($a['feedback']);?></div><?php endwhile;?></div><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
<?php include_once 'includes/testimonial_prompt.php'; ?>
</body></html>