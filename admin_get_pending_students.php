<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    http_response_code(403);
    exit;
}
$conn = getDB();
$note_id = (int)$_GET['note_id'];
if (!$note_id) exit;
$students = $conn->query("SELECT DISTINCT u.id, u.fullname FROM exercise_attempts a JOIN users u ON a.user_id=u.id JOIN note_exercises e ON a.exercise_id=e.id WHERE e.note_id=$note_id AND a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 23 HOUR)");
if ($students->num_rows == 0) {
    echo "<p>No pending students for this note.</p>";
} else {
    echo "<div class='checkbox-group'>";
    while($s = $students->fetch_assoc()) {
        echo "<label><input type='checkbox' name='student_ids[]' value='{$s['id']}'> " . htmlspecialchars($s['fullname']) . "</label>";
    }
    echo "</div>";
}
?>