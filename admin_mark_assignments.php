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
}
$conn = getDB();
$aid = (int)($_GET['assignment_id'] ?? 0);
$uid = (int)($_GET['user_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $sub_id = (int)$_POST['submission_id'];
    $marks = (int)$_POST['marks'];
    $fb = $_POST['feedback'];
    $conn->query("UPDATE assignment_submissions SET marks=$marks, feedback='$fb', marked_by_admin=1 WHERE id=$sub_id");
    header("Location: admin_mark_assignments.php?assignment_id=$aid&user_id=$uid");
    exit;
}
if ($aid && $uid) {
    $sub = $conn->query("SELECT s.*, u.fullname, a.title FROM assignment_submissions s JOIN users u ON s.user_id=u.id JOIN assignments a ON s.assignment_id=a.id WHERE s.assignment_id=$aid AND s.user_id=$uid")->fetch_assoc();
    if (!$sub) die("Submission not found.");
    ?><html><head><title>Mark Assignment</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_mark_assignments</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>Marking: <?=htmlspecialchars($sub['title'])?> for <?=htmlspecialchars($sub['fullname'])?></h1><p><strong>Submission:</strong><br><?=nl2br(htmlspecialchars($sub['submission_text']))?><?php if($sub['file_path']) echo "<br><a href='admin_download.php?type=assignment&file=" . urlencode(basename($sub['file_path'])) . "' target='_blank'>View file</a>";?></p><form method="post"><input type="hidden" name="submission_id" value="<?=$sub['id']?>"><label>Marks (out of ?)</label><input type="number" name="marks" value="<?=$sub['marks']?>"><label>Feedback</label><textarea name="feedback"><?=htmlspecialchars($sub['feedback'])?></textarea><button type="submit" name="save_marks">Save</button></form><a href="admin_mark_assignments.php?assignment_id=<?=$aid?>">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html><?php exit;
}
if ($aid) {
    $subs = $conn->query("SELECT s.user_id, u.fullname, s.marks FROM assignment_submissions s JOIN users u ON s.user_id=u.id WHERE s.assignment_id=$aid");
    $title = $conn->query("SELECT title FROM assignments WHERE id=$aid")->fetch_assoc()['title'];
    ?><html><head><title>Submissions</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_mark_assignments</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>Submissions for <?=htmlspecialchars($title)?></h1><?php while($s=$subs->fetch_assoc()):?><div><a href="admin_mark_assignments.php?assignment_id=<?=$aid?>&user_id=<?=$s['user_id']?>"><?=htmlspecialchars($s['fullname'])?></a> - Marks: <?=$s['marks']??'Not marked'?></div><?php endwhile;?><a href="admin_assignments_list.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html><?php exit;
}
$assignments = $conn->query("SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id=a.id) as subcnt FROM assignments a ORDER BY a.due_date DESC");
?><html><head><title>Mark Assignments</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_mark_assignments</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>Select Assignment</h1><?php while($a=$assignments->fetch_assoc()):?><div><strong><?=htmlspecialchars($a['title'])?></strong> (Due <?=$a['due_date']?>) - Submissions: <?=$a['subcnt']?> <a href="admin_mark_assignments.php?assignment_id=<?=$a['id']?>">Mark</a></div><?php endwhile;?><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>