<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$exam_id = (int)$_GET['exam_id'];
$exam = $conn->query("SELECT title FROM exams WHERE id=$exam_id")->fetch_assoc();
if (!$exam) die("Invalid exam");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtext = $_POST['question_text'];
    $type = $_POST['question_type'];
    $points = (int)$_POST['points'];
    $order = (int)$_POST['sort_order'];
    $options = null;
    $correct = null;
    if ($type == 'multiple_choice') {
        $opts = explode("\n", trim($_POST['options_raw']));
        $options = json_encode(array_filter(array_map('trim', $opts)));
        $correct = trim($_POST['correct_answer']);
    }
    $stmt = $conn->prepare("INSERT INTO exam_questions (exam_id,question_text,question_type,options,correct_answer,points,sort_order) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssii", $exam_id, $qtext, $type, $options, $correct, $points, $order);
    $stmt->execute();
    echo "<script>alert('Question added');</script>";
}
$questions = $conn->query("SELECT * FROM exam_questions WHERE exam_id=$exam_id ORDER BY sort_order");
?>
<!DOCTYPE html><html><head><title>Add Questions</title><script>function toggleOptions(){var t=document.getElementById('qtype').value; document.getElementById('options_div').style.display=(t=='multiple_choice')?'block':'none';}</script>    <link rel="stylesheet" href="style.css">
</head><body>
    <?php include_once 'includes/header.php'; ?>

    

<div class="container">

<div class="content-grid">
<h2>Existing Questions</h2><?php while($q=$questions->fetch_assoc()):?><div><strong>Q<?=$q['sort_order']?>:</strong> <?=nl2br(htmlspecialchars($q['question_text']))?> (<?=$q['points']?> pts)</div><?php endwhile;?><h2>Add New Question</h2><form method="post"><label>Question text</label><textarea name="question_text" rows="3" required></textarea><label>Type</label><select name="question_type" id="qtype" onchange="toggleOptions()"><option value="essay">Essay</option><option value="short_answer">Short Answer</option><option value="multiple_choice">Multiple Choice</option></select><div id="options_div" style="display:none;"><label>Options (one per line)</label><textarea name="options_raw" rows="4"></textarea><label>Correct answer (exact match)</label><input type="text" name="correct_answer"></div><label>Points</label><input type="number" name="points" value="5"><label>Sort order</label><input type="number" name="sort_order" value="1"><button type="submit">Add Question</button></form><p><a href="admin_exams_list.php">Back</a></p>
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

</body></html>