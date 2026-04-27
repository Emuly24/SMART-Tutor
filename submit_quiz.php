<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$quiz_id = (int)$_POST['quiz_id'];
$answers = $_POST['answer'] ?? [];

// Get attempt
$attempt = $conn->query("SELECT * FROM quiz_attempts WHERE user_id=$uid AND quiz_id=$quiz_id AND status='in_progress'")->fetch_assoc();
if (!$attempt) die("No active attempt.");
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id");
$total_points = 0;
$earned = 0;
while ($q = $questions->fetch_assoc()) {
    $user_ans = trim($answers[$q['id']] ?? '');
    $correct = false;
    $points_earned = 0;
    if ($q['question_type'] != 'short_answer') {
        // Auto‑grade
        if (strcasecmp(trim($user_ans), trim($q['correct_answer'])) == 0) {
            $correct = true;
            $points_earned = $q['points'];
        }
        $earned += $points_earned;
        $conn->query("INSERT INTO quiz_answers (attempt_id, question_id, user_answer, is_correct, points_awarded) VALUES ({$attempt['id']}, {$q['id']}, '$user_ans', " . ($correct?1:0) . ", $points_earned)");
    } else {
        // Short answer – store, mark later by admin
        $conn->query("INSERT INTO quiz_answers (attempt_id, question_id, user_answer, is_correct, points_awarded) VALUES ({$attempt['id']}, {$q['id']}, '$user_ans', 0, 0)");
    }
    $total_points += $q['points'];
}
// Update attempt with score (only objective points so far) and mark as submitted
$conn->query("UPDATE quiz_attempts SET score=$earned, status='submitted', completed_at=NOW() WHERE id={$attempt['id']}");
echo "<script>alert('Quiz submitted! Results: $earned / $total_points (objective only). Short answers will be marked by admin.'); window.location='quiz_results.php?quiz_id=$quiz_id';</script>";
?>