<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) die("Access denied");
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    $template_id = (int)$_POST['template_id'];
    $student_ids = $_POST['student_ids'] ?? [];
    $note_id = (int)$_POST['note_id'];
    $note = $conn->query("SELECT title FROM notes WHERE id=$note_id")->fetch_assoc();
    $template = $conn->query("SELECT message FROM message_templates WHERE id=$template_id")->fetch_assoc();
    if ($template && $note) {
        foreach ($student_ids as $sid) {
            $student = $conn->query("SELECT fullname FROM users WHERE id=$sid")->fetch_assoc();
            $attempt = $conn->query("SELECT promised_at FROM exercise_attempts WHERE user_id=$sid AND exercise_id IN (SELECT id FROM note_exercises WHERE note_id=$note_id) LIMIT 1")->fetch_assoc();
            $hours = $attempt ? round((strtotime($attempt['promised_at'].' +24 hours') - time())/3600) : 24;
            $message = str_replace(['{student}', '{note_title}', '{hours_remaining}'], [$student['fullname'], $note['title'], $hours], $template['message']);
            // Store message in a new table or send email. For simplicity, we log to a table.
            $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ($sid, '$message', NOW())");
        }
        echo "<div class='success'>Reminders sent.</div>";
    }
}

$notes_with_pending = $conn->query("SELECT DISTINCT n.id, n.title FROM notes n 
    JOIN note_exercises e ON n.id=e.note_id 
    JOIN exercise_attempts a ON e.id=a.exercise_id 
    WHERE a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 23 HOUR)");
?>
<!DOCTYPE html><html><head><title>Send Reminders</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>📢 Send Reminders</h1><a href="admin_dashboard.php">Dashboard</a></div>
<form method="post">
<div class="form-group"><label>Select Note</label><select name="note_id" onchange="this.form.submit()">
<option value="">-- Select --</option><?php while($n=$notes_with_pending->fetch_assoc()):?><option value="<?=$n['id']?>" <?=(isset($_POST['note_id']) && $_POST['note_id']==$n['id'])?'selected':''?>><?=htmlspecialchars($n['title'])?></option><?php endwhile;?></select></div>
<?php if (isset($_POST['note_id']) && $_POST['note_id']): 
    $note_id = (int)$_POST['note_id'];
    $students = $conn->query("SELECT DISTINCT u.id, u.fullname FROM exercise_attempts a JOIN users u ON a.user_id=u.id JOIN note_exercises e ON a.exercise_id=e.id WHERE e.note_id=$note_id AND a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 23 HOUR)");
    $templates = $conn->query("SELECT id, title FROM message_templates");
?>
    <div class="form-group"><label>Select Template</label><select name="template_id"><?php while($t=$templates->fetch_assoc()):?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['title'])?></option><?php endwhile;?></select></div>
    <div class="form-group"><label>Select Students</label><br><?php while($s=$students->fetch_assoc()):?><label><input type="checkbox" name="student_ids[]" value="<?=$s['id']?>"> <?=htmlspecialchars($s['fullname'])?></label><br><?php endwhile;?></div>
    <button type="submit" name="send">Send Reminders</button>
<?php endif; ?>
</form></div></body></html>