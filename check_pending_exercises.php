<?php
require_once 'config.php';
$conn = getDB();

// 1. Send warning after 24h (but before 36h)
$pending = $conn->query("SELECT a.id, a.user_id, a.promised_at, e.note_id, u.fullname, n.title 
    FROM exercise_attempts a 
    JOIN note_exercises e ON a.exercise_id=e.id 
    JOIN notes n ON e.note_id=n.id 
    JOIN users u ON a.user_id=u.id 
    WHERE a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND a.reminder_sent=0 AND a.warning_sent=0");
while ($r = $pending->fetch_assoc()) {
    // Send warning (log or email)
    $msg = "Dear {$r['fullname']}, you promised to submit the exercise for \"{$r['title']}\" on paper. Please submit within 12 hours or your account will be suspended.";
    // Store message
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $conn->query("UPDATE exercise_attempts SET reminder_sent=1 WHERE id={$r['id']}");
}

// 2. After 36h, suspend and send final warning
$overdue = $conn->query("SELECT a.id, a.user_id, a.promised_at, e.note_id, u.fullname, n.title 
    FROM exercise_attempts a 
    JOIN note_exercises e ON a.exercise_id=e.id 
    JOIN notes n ON e.note_id=n.id 
    JOIN users u ON a.user_id=u.id 
    WHERE a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 36 HOUR) AND a.suspended_for_exercise=0");
while ($r = $overdue->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, your account has been suspended for not submitting the exercise \"{$r['title']}\". Please contact admin.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $conn->query("UPDATE users SET status='suspended', suspension_end=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id={$r['user_id']}");
    $conn->query("UPDATE exercise_attempts SET warning_sent=1, suspended_for_exercise=1 WHERE id={$r['id']}");
}
echo "Done.";
?>