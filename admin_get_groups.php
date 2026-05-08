<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
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
$class = $_GET['class'] ?? '';
$route = $_GET['route'] ?? '';
if (!$class || !$route) {
    echo json_encode([]);
    exit;
}

$groups = $conn->query("
    SELECT 
        g.id, 
        g.group_number,
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS current_members,
        (SELECT COUNT(*) FROM attendance a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'on_time' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) AS on_time_count,
        (SELECT COUNT(*) FROM attendance a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'late' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) AS late_count,
        (SELECT COUNT(DISTINCT a.user_id) FROM exercise_attempts a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'paper_pending'
        ) AS pending_exercises,
        (SELECT AVG(qa.points_awarded) FROM quiz_answers qa 
            JOIN quiz_attempts qat ON qa.attempt_id = qat.id 
            JOIN group_members gm ON qat.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND qat.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) AS avg_quiz_score
    FROM groups g 
    WHERE g.class_level = '$class' AND g.route = '$route' 
    ORDER BY g.group_number
");

$result = [];
while ($g = $groups->fetch_assoc()) {
    $total_days = $g['on_time_count'] + $g['late_count'];
    $attendance_rate = $total_days > 0 ? round(($g['on_time_count'] / $total_days) * 100, 1) : 0;
    $g['attendance_rate'] = $attendance_rate;
    $g['avg_quiz_score'] = round($g['avg_quiz_score'] ?? 0, 1);
    $result[] = $g;
}
header('Content-Type: application/json');
echo json_encode($result);
?>