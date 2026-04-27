<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$exam_id = (int)$_GET['exam_id'];
$exam = $conn->query("SELECT * FROM exams WHERE id=$exam_id")->fetch_assoc();
if (!$exam) die("Exam not found.");
if (!is_content_unlocked('exam', $exam_id, $uid)) {
    die("<!DOCTYPE html><html><head><title>Exam Locked</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Exam Locked</h1><a href='exams.php'>Exams</a><a href='logout.php' class='logout'>Logout</a></div><div class='error'>This exam is not yet available for your group. Please wait until the admin unlocks it.</div><a href='exams.php'>← Back to Exams</a></div></body></html>");
}

$sub = $conn->query("SELECT * FROM exam_submissions WHERE exam_id=$exam_id AND user_id=$uid")->fetch_assoc();
if ($sub && $sub['status'] == 'submitted') die("Already submitted. <a href='exam_results.php?exam_id=$exam_id'>View results</a>");
if (!$sub) $conn->query("INSERT INTO exam_submissions (exam_id, user_id) VALUES ($exam_id, $uid)");
$sub = $conn->query("SELECT start_time FROM exam_submissions WHERE exam_id=$exam_id AND user_id=$uid")->fetch_assoc();
$start = new DateTime($sub['start_time']);
$end = (clone $start)->modify("+{$exam['duration_minutes']} minutes");
if (new DateTime() > $end) {
    $conn->query("UPDATE exam_submissions SET status='submitted', end_time=NOW() WHERE exam_id=$exam_id AND user_id=$uid");
    die("Time's up. Submitted. <a href='exam_results.php?exam_id=$exam_id'>View results</a>");
}
$remaining = $end->getTimestamp() - time();
$questions = $conn->query("SELECT * FROM exam_questions WHERE exam_id=$exam_id ORDER BY sort_order");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    foreach ($_POST['answers'] as $qid => $text) {
        $text = trim($text);
        $file_path = null;
        if (isset($_FILES['answer_files']) && isset($_FILES['answer_files']['name'][$qid]) && $_FILES['answer_files']['error'][$qid] == UPLOAD_ERR_OK) {
            $dir = 'uploads/exam_answers/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['answer_files']['name'][$qid], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg','jpeg','png','pdf'])) {
                $dest = $dir . "user_{$uid}_exam_{$exam_id}_q{$qid}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['answer_files']['tmp_name'][$qid], $dest)) $file_path = $dest;
            }
        }
        $check = $conn->query("SELECT id FROM exam_answers WHERE exam_id=$exam_id AND question_id=$qid AND user_id=$uid");
        if ($check->num_rows) {
            $conn->query("UPDATE exam_answers SET answer_text='$text', answer_file_path='$file_path' WHERE exam_id=$exam_id AND question_id=$qid AND user_id=$uid");
        } else {
            $conn->query("INSERT INTO exam_answers (exam_id, question_id, user_id, answer_text, answer_file_path) VALUES ($exam_id, $qid, $uid, '$text', '$file_path')");
        }
    }
    $conn->query("UPDATE exam_submissions SET status='submitted', end_time=NOW() WHERE exam_id=$exam_id AND user_id=$uid");
    echo "<script>alert('Exam submitted'); window.location='exams.php';</script>";
    exit;
}
$saved = [];
$res = $conn->query("SELECT question_id, answer_text FROM exam_answers WHERE exam_id=$exam_id AND user_id=$uid");
while ($r = $res->fetch_assoc()) $saved[$r['question_id']] = $r['answer_text'];
?>
<!DOCTYPE html><html><head><title><?=htmlspecialchars($exam['title'])?></title><link rel="stylesheet" href="style.css"><script>let remaining=<?=$remaining?>; function timer(){if(remaining<=0){document.getElementById('timer').innerHTML="Submitting..."; document.getElementById('examForm').submit();} let mins=Math.floor(remaining/60); let secs=remaining%60; document.getElementById('timer').innerHTML=`Time left: ${mins}m ${secs}s`; remaining--; setTimeout(timer,1000);} window.onload=timer;</script></head><body><div class="container"><div class="header"><h1><?=htmlspecialchars($exam['title'])?></h1><div id="timer" style="background:#e74c3c;color:white;padding:5px 10px;border-radius:20px;"></div></div><form id="examForm" method="post" enctype="multipart/form-data"><?php $qno=1; while($q=$questions->fetch_assoc()):?><div class="card"><b><?=$qno?>. <?=nl2br(htmlspecialchars($q['question_text']))?></b> (<?=$q['points']?> pts)<br><?php if($q['question_type']!='multiple_choice'):?><textarea name="answers[<?=$q['id']?>]" rows="4" class="form-group"><?=htmlspecialchars($saved[$q['id']]??'')?></textarea><br>OR upload file: <input type="file" name="answer_files[<?=$q['id']?>]" accept=".jpg,.png,.pdf"><?php else: $opts=json_decode($q['options'],true); foreach($opts as $opt):?><label><input type="radio" name="answers[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>" <?=(($saved[$q['id']]??'')==$opt)?'checked':''?>> <?=$opt?></label><br><?php endforeach; endif;?></div><?php $qno++; endwhile;?><button type="submit" name="submit_exam" class="btn">Submit Exam</button></form><div class="footer"><a href="exams.php">Cancel</a></div></div></body></html>