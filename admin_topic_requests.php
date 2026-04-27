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
if (isset($_GET['mark_covered'])) {
    $subject = $_GET['subject'];
    $topic = $_GET['topic'];
    $class = $_GET['class'];
    $conn->query("INSERT INTO topics_covered (subject,topic,class_level,covered_date) VALUES ('$subject','$topic','$class',CURDATE())");
    header("Location: admin_topic_requests.php");
    exit;
}
if (isset($_GET['clear_class'])) {
    $class = $_GET['clear_class'];
    $conn->query("DELETE FROM topic_requests WHERE class_level='$class'");
    header("Location: admin_topic_requests.php");
    exit;
}
$classes = ['Form 3', 'Form 4'];
?>
<!DOCTYPE html><html><head><title>Topic Requests</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_topic_requests</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>💡 Topic Requests</h1><?php foreach($classes as $c): $req=$conn->query("SELECT subject, topic, COUNT(*) as cnt FROM topic_requests WHERE class_level='$c' GROUP BY subject, topic ORDER BY cnt DESC");?><h2><?=$c?> <a href="?clear_class=<?=$c?>" onclick="return confirm('Clear all requests for <?=$c?>?')">Clear all</a></h2><?php if($req->num_rows==0) echo "<p>None.</p>"; else{?><table class="data-table" border="1"><tr><th>Subject</th><th>Topic</th><th>Requests</th><th>Action</th></tr><?php while($r=$req->fetch_assoc()):?><tr><td><?=htmlspecialchars($r['subject'])?></td><td><?=htmlspecialchars($r['topic'])?></td><td><?=$r['cnt']?></td><td><a href="?mark_covered=1&subject=<?=urlencode($r['subject'])?>&topic=<?=urlencode($r['topic'])?>&class=<?=$c?>" onclick="return confirm('Mark as covered?')">Mark Covered</a></td></tr><?php endwhile;?></table><?php } endforeach;?><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>