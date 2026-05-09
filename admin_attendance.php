<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$today = date('Y-m-d');
$msg = '';
$error_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
   foreach ($_POST['status'] as $uid => $stat) {
    if (empty($stat)) continue;
    
    $uid = (int)$uid;
    $group = $conn->query("SELECT group_id FROM group_members WHERE user_id = $uid")->fetch_assoc();
    $group_id = $group['group_id'] ?? 0;
    $arrival_time = null;
    
    if ($stat == 'late' && isset($_POST['arrival_time'][$uid])) {
        $arrival_time = $_POST['arrival_time'][$uid];
        $arrival_time = $conn->real_escape_string($arrival_time);
    }
    
    // ---- NEW: Fetch the student's class level ----
    $class_result = $conn->query("SELECT class_level FROM users WHERE id = $uid");
    $class_row = $class_result->fetch_assoc();
    $class_level = $class_row['class_level'] ?? '';
    $class_level = $conn->real_escape_string($class_level);
    // -------------------------------------------------
    
    // Get existing status for this user today (if any)
    $existing_result = $conn->query("SELECT status FROM attendance WHERE user_id = $uid AND date = '$today'");
    $existing = $existing_result->fetch_assoc();
    $existing_status = $existing['status'] ?? null;
    
    // ---- UPDATED INSERT: Includes class_level ----
    $sql = "INSERT INTO attendance (user_id, class_level, date, status, arrival_time, marked_by_admin) 
            VALUES ($uid, '$class_level', '$today', '$stat', " . ($arrival_time ? "'$arrival_time'" : "NULL") . ", 1)
            ON DUPLICATE KEY UPDATE status='$stat', arrival_time = VALUES(arrival_time)";
    // -------------------------------------------------
    
    if ($conn->query($sql)) {
        $success_count++;
        
        // --- Send notification ONLY if status has CHANGED ----
        if ($stat !== $existing_status) {
            $student = $conn->query("SELECT fullname FROM users WHERE id = $uid")->fetch_assoc();
            if ($stat == 'late') {
                $msg_text = "You were late today, {$student['fullname']}. Please submit a reason from your attendance page.";
            } elseif ($stat == 'on_time') {
                $msg_text = "Thank you for being punctual today, {$student['fullname']}!";
            } else {
                continue; // no message for absent
            }
            $msg_text = $conn->real_escape_string($msg_text);
            if (!$conn->query("INSERT INTO admin_messages (user_id, message) VALUES ($uid, '$msg_text')")) {
                $errors[] = "Warning: Attendance saved for {$student['fullname']} but message could not be sent.";
            }
        }
    } else {
        $error_count++;
        $student = $conn->query("SELECT fullname FROM users WHERE id = $uid")->fetch_assoc();
        $errors[] = "Failed to save attendance for {$student['fullname']}.";
    }
}

$students = $conn->query("SELECT u.id, u.fullname, u.class_level, u.route, 
    (SELECT group_id FROM group_members WHERE user_id = u.id) as group_id
    FROM users u WHERE u.approved=1 AND u.status!='dismissed' ORDER BY u.class_level, u.route, u.fullname");

$existing = [];
$r = $conn->query("SELECT user_id, status, arrival_time FROM attendance WHERE date='$today'");
while ($row = $r->fetch_assoc()) {
    $existing[$row['user_id']] = ['status' => $row['status'], 'arrival_time' => $row['arrival_time']];
}
?>
<!DOCTYPE html><html><head><title>Mark Attendance</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Mark Attendance – <?= date('F j, Y') ?></h1>
        <?php if($msg): ?>
            <div class="<?= strpos($msg, '⚠️') !== false ? 'warning' : 'success' ?>"><?= $msg ?></div>
        <?php endif; ?>
        <form method="post">
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Class</th><th>Route</th><th>Group</th><th>Status</th><th>Arrival Time (if late)</th></tr>
                </thead>
                <tbody>
                <?php while($s=$students->fetch_assoc()): 
                    $group_id = $s['group_id'];
                    $current = $existing[$s['id']] ?? null;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($s['fullname']) ?></td>
                        <td><?= $s['class_level'] ?></td>
                        <td><?= ucfirst($s['route']) ?></td>
                        <td><?= $group_id ? "Group $group_id" : 'None' ?></td>
                        <td>
                            <select name="status[<?= $s['id'] ?>]" onchange="toggleArrivalTime(this, <?= $s['id'] ?>)">
                                <option value="">-- Select --</option>
                                <option value="on_time" <?= ($current['status'] ?? '') == 'on_time' ? 'selected' : '' ?>>✅ On time (Before/At start)</option>
                                <option value="late" <?= ($current['status'] ?? '') == 'late' ? 'selected' : '' ?>>⏰ Late (After start)</option>
                                <option value="absent" <?= ($current['status'] ?? '') == 'absent' ? 'selected' : '' ?>>❌ Absent</option>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="arrival_time[<?= $s['id'] ?>]" value="<?= $current['arrival_time'] ?? '' ?>" style="display: <?= ($current['status'] ?? '') == 'late' ? 'block' : 'none' ?>;">
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" class="btn">Save Attendance & Send Notifications</button>
        </form>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <script>
        function toggleArrivalTime(select, userId) {
            var input = document.querySelector('input[name="arrival_time[' + userId + ']"]');
            if (select.value === 'late') {
                input.style.display = 'block';
                if (!input.value) input.value = new Date().toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
            } else {
                input.style.display = 'none';
                input.value = '';
            }
        }
        // Initialise for already selected values
        document.querySelectorAll('select[name^="status"]').forEach(select => {
            var userId = select.name.match(/\d+/)[0];
            var input = document.querySelector('input[name="arrival_time[' + userId + ']"]');
            if (select.value === 'late') input.style.display = 'block';
        });
    </script>
</body></html>