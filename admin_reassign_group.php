<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $new_group_id = (int)$_POST['new_group_id'];
    
    // Get student's current route and class
    $student = $conn->query("SELECT class_level, route FROM users WHERE id = $student_id")->fetch_assoc();
    if (!$student) die("Student not found");
    
    // Get target group's route and class
    $target_group = $conn->query("SELECT class_level, route FROM groups WHERE id = $new_group_id")->fetch_assoc();
    if (!$target_group) die("Group not found");
    
    // Enforce same class and same route
    if ($target_group['class_level'] != $student['class_level']) {
        $msg = "Error: Student's class ({$student['class_level']}) does not match group's class ({$target_group['class_level']}).";
    } elseif ($target_group['route'] != $student['route']) {
        $msg = "Error: Cannot move a {$student['route']} student into a {$target_group['route']} group. Routes must match.";
    } else {
        // Check if student already in a group
        $existing = $conn->query("SELECT id FROM group_members WHERE user_id = $student_id")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE group_members SET group_id = $new_group_id WHERE user_id = $student_id");
        } else {
            $conn->query("INSERT INTO group_members (user_id, group_id) VALUES ($student_id, $new_group_id)");
        }
        // Ensure the student's route is correct (already should be, but keep consistency)
        $conn->query("UPDATE users SET route = '{$target_group['route']}' WHERE id = $student_id");
        $msg = "Student reassigned successfully.";
    }
}

// Fetch all approved students with current group info
$students = $conn->query("SELECT u.id, u.fullname, u.class_level, u.route, 
    (SELECT g.group_number FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = u.id) as current_group,
    (SELECT g.route FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = u.id) as current_route
    FROM users u WHERE u.approved = 1 ORDER BY u.class_level, u.route, u.fullname");

// Fetch all groups (will be filtered client‑side by class & route)
$groups = $conn->query("SELECT id, class_level, group_number, route FROM groups ORDER BY class_level, route, group_number");
?>
<!DOCTYPE html>
<html><head><title>Reassign Student Group (Route‑Safe)</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Student Group Reassignment</h1>
    <?php if ($msg): ?>
        <div class="<?= strpos($msg, 'Error') === false ? 'success' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <div class="grid">
        <?php while($s = $students->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($s['fullname']) ?> (<?= $s['class_level'] ?>)</h3>
                <p>Current route: <strong><?= ucfirst($s['route'] ?? 'Not set') ?></strong></p>
                <p>Current group: <?= $s['current_group'] ?: 'Not assigned' ?> (<?= $s['current_route'] ?: 'No route' ?>)</p>
                <form method="post">
                    <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                    <select name="new_group_id" required>
                        <option value="">-- Select new group --</option>
                        <?php
                        $groups->data_seek(0);
                        while($g = $groups->fetch_assoc()):
                            // Only show groups with the same class and the same route as the student
                            if ($g['class_level'] != $s['class_level']) continue;
                            if ($g['route'] != $s['route']) continue;
                        ?>
                            <option value="<?= $g['id'] ?>">Group <?= $g['group_number'] ?> (<?= ucfirst($g['route']) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn">Reassign</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>