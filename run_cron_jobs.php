<?php
// run_cron_jobs.php – Automated cron job handler (GitHub Actions)
require_once 'config.php';

// --- Security: Verify Bearer Token (FastCGI compatible) ---
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);
$valid_token = '5f4dcc3b5aa765d61d8327deb882cf99';
if ($token !== $valid_token) {
    http_response_code(401);
    die('Unauthorized');
}
// ------------------------------------------

$conn = getDB();
$output = [];

// ============================================
// 1. EXERCISE REMINDERS (paper‑pending)
// ============================================
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
    $output[] = "Exercise reminder sent to {$r['fullname']}";
}

// --- Overdue exercise (36h → suspension) ---
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
    $output[] = "Suspended {$r['fullname']} for overdue exercise";
}

// ============================================
// 2. ASSIGNMENT REMINDERS (NEW)
// ============================================
// Check for assignments due within 24 hours (not yet submitted)
$due_soon = $conn->query("
    SELECT a.id as assignment_id, a.title, a.due_date, u.id as user_id, u.fullname
    FROM assignments a
    CROSS JOIN users u
    WHERE u.approved = 1 AND u.status != 'suspended'
      AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
      AND NOT EXISTS (
          SELECT 1 FROM assignment_submissions s 
          WHERE s.assignment_id = a.id AND s.user_id = u.id
      )
      AND NOT EXISTS (
          SELECT 1 FROM admin_messages m 
          WHERE m.user_id = u.id AND m.message LIKE CONCAT('%', a.title, '%')
            AND m.sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
      )
");
while ($r = $due_soon->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, the assignment \"{$r['title']}\" is due within 24 hours (due {$r['due_date']}). Please submit it soon to avoid any penalties.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $output[] = "Assignment reminder (due soon) sent to {$r['fullname']} for '{$r['title']}'";
}

// Check for overdue assignments (due date passed, not submitted)
$overdue_assignments = $conn->query("
    SELECT a.id as assignment_id, a.title, a.due_date, u.id as user_id, u.fullname
    FROM assignments a
    CROSS JOIN users u
    WHERE u.approved = 1 AND u.status != 'suspended'
      AND a.due_date < NOW()
      AND NOT EXISTS (
          SELECT 1 FROM assignment_submissions s 
          WHERE s.assignment_id = a.id AND s.user_id = u.id
      )
      AND NOT EXISTS (
          SELECT 1 FROM admin_messages m 
          WHERE m.user_id = u.id AND m.message LIKE CONCAT('%', a.title, '%')
            AND m.sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
      )
");
while ($r = $overdue_assignments->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, the assignment \"{$r['title']}\" was due on {$r['due_date']} and we have not received your submission. Please submit as soon as possible.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $output[] = "Overdue assignment reminder sent to {$r['fullname']} for '{$r['title']}'";
}

// ============================================
// 3. LOG & FINISH
// ============================================
$log = date('Y-m-d H:i:s') . " - " . (empty($output) ? "No pending actions." : implode(", ", $output));
file_put_contents(__DIR__ . '/cron_log.txt', $log . PHP_EOL, FILE_APPEND);

echo "OK";
?>