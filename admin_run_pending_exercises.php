<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

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
// 2. ASSIGNMENT REMINDERS
// ============================================
$due_soon = $conn->query("
    SELECT a.id as assignment_id, a.title, a.due_date, u.id as user_id, u.fullname
    FROM assignments a
    CROSS JOIN users u
    WHERE u.approved = 1 AND u.status != 'suspended'
      AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
      AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.user_id = u.id)
      AND NOT EXISTS (SELECT 1 FROM admin_messages m WHERE m.user_id = u.id AND m.message LIKE CONCAT('%', a.title, '%') AND m.sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR))
");
while ($r = $due_soon->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, the assignment \"{$r['title']}\" is due within 24 hours (due {$r['due_date']}). Please submit it soon.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $output[] = "Assignment reminder (due soon) sent to {$r['fullname']} for '{$r['title']}'";
}

$overdue_assignments = $conn->query("
    SELECT a.id as assignment_id, a.title, a.due_date, u.id as user_id, u.fullname
    FROM assignments a
    CROSS JOIN users u
    WHERE u.approved = 1 AND u.status != 'suspended'
      AND a.due_date < NOW()
      AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.user_id = u.id)
      AND NOT EXISTS (SELECT 1 FROM admin_messages m WHERE m.user_id = u.id AND m.message LIKE CONCAT('%', a.title, '%') AND m.sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR))
");
while ($r = $overdue_assignments->fetch_assoc()) {
    $msg = "Dear {$r['fullname']}, the assignment \"{$r['title']}\" was due on {$r['due_date']} and we have not received your submission. Please submit as soon as possible.";
    $conn->query("INSERT INTO admin_messages (user_id, message, sent_at) VALUES ({$r['user_id']}, '$msg', NOW())");
    $output[] = "Overdue assignment reminder sent to {$r['fullname']} for '{$r['title']}'";
}

// ============================================
// DISPLAY RESULTS
// ============================================
?>
<!DOCTYPE html>
<html><head>
    <title>Manual Run – Full Checks</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php include_once 'includes/header.php'; ?>
        <div class="card">
            <h2>⏰ Manual Run – Exercises &amp; Assignments</h2>
            <div style="background:var(--card-alt-bg); padding:1rem; border-radius:0.5rem; margin:1rem 0;">
                <?php if (empty($output)): ?>
                    <p><strong>✅ No pending actions required.</strong></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($output as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-buttons">
                <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>