<?php
require_once 'check_remember_me.php';

// run_cron_jobs.php – Automated cron job handler (GitHub Actions)
require_once 'config.php';

// --- Security: Verify Bearer Token ---
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);
$valid_token = '5f4dcc3b5aa765d61d8327deb882cf99';
if ($token !== $valid_token) {
    http_response_code(401);
    die('Unauthorized');
}
// ------------------------------------------

$conn = getDB();
$output = [];

// --- Same logic as admin_run_pending_exercises.php ---
$pending = $conn->query("SELECT a.id, a.user_id, a.promised_at, e.note_id, u.fullname, n.title 
    FROM exercise_attempts a 
    JOIN note_exercises e ON a.exercise_id=e.id 
    JOIN notes n ON e.note_id=n.id 
    JOIN users u ON a.user_id=u.id 
    WHERE a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND a.reminder_sent=0 AND a.warning_sent=0");
while ($r = $pending->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, you promised to submit the exercise for \"{$r['title']}\" on paper. Please submit within 12 hours or your account will be suspended.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $conn->query("UPDATE exercise_attempts SET reminder_sent=1 WHERE id={$r['id']}");
    $output[] = "Reminder sent to {$r['fullname']}";
}

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
    $output[] = "Suspended {$r['fullname']}";
}
// Send reminder for books due tomorrow
$overdue = $conn->query("SELECT DISTINCT u.id, u.fullname, u.email FROM borrowed_books b JOIN users u ON b.user_id = u.id WHERE b.due_date = CURDATE() + INTERVAL 1 DAY AND b.returned_at IS NULL");
while ($r = $overdue->fetch_assoc()) {
    $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$r['id']}, 'Reminder: Your borrowed book is due tomorrow. Please return it on time.')");
}
// --- Overdue borrowed books ---
$overdue_books = $conn->query("SELECT b.user_id, u.fullname, b.book_title, b.due_date 
    FROM borrowed_books b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.returned_at IS NULL AND b.due_date < CURDATE()");
while ($book = $overdue_books->fetch_assoc()) {
    $msg = "Reminder: The book \"{$book['book_title']}\" is overdue (due {$book['due_date']}). Please return it as soon as possible.";
    $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$book['user_id']}, '$msg')");
    $output[] = "Overdue reminder sent to {$book['fullname']} for '{$book['book_title']}'.";
}

$log = date('Y-m-d H:i:s') . " - " . (empty($output) ? "No pending actions." : implode(", ", $output));
file_put_contents(__DIR__ . '/cron_log.txt', $log . PHP_EOL, FILE_APPEND);

echo "OK";
?>