<?php
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
$today = date('Y-m-d');
$msg = '';

// Get today's meeting start times per group
$meetings = [];
$meeting_res = $conn->query("SELECT group_id, start_time FROM group_meetings WHERE meeting_date = '$today'");
while ($m = $meeting_res->fetch_assoc()) {
    $meetings[$m['group_id']] = $m['start_time'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['status'] as $uid => $stat) {
        $group = $conn->query("SELECT group_id FROM group_members WHERE user_id = $uid")->fetch_assoc();
        $group_id = $group['group_id'] ?? 0;
        $start_time = $meetings[$group_id] ?? null;
        $arrival_time = null;
        
        // If status is 'late', capture arrival time
        if ($stat == 'late' && isset($_POST['arrival_time'][$uid])) {
            $arrival_time = $_POST['arrival_time'][$uid];
        }
        
        $conn->query("INSERT INTO attendance (user_id, date, status, arrival_time, marked_by_admin) 
            VALUES ($uid, '$today', '$stat', " . ($arrival_time ? "'$arrival_time'" : "NULL") . ", 1)
            ON DUPLICATE KEY UPDATE status='$stat', arrival_time = VALUES(arrival_time)");
        
        // Send notifications (as before)
        $student = $conn->query("SELECT fullname FROM users WHERE id = $uid")->fetch_assoc();
        if ($stat == 'late') {
            $msg_text = "You were late today, {$student['fullname']}. Please submit a reason from your attendance page.";
            $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ($uid, '$msg_text')");
        } elseif ($stat == 'on_time') {
            $msg_text = "Thank you for being punctual today, {$student['fullname']}!";
            $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ($uid, '$msg_text')");
        }
    }
    $msg = "Attendance saved and notifications sent.";
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
        <?php if($msg) echo "<div class='success'>$msg</div>"; ?>
        <form method="post">
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Class</th><th>Route</th><th>Group</th><th>Status</th><th>Arrival Time (if late)</th></tr>
                </thead>
                <tbody>
                <?php while($s=$students->fetch_assoc()): 
                    $group_id = $s['group_id'];
                    $start_time = $meetings[$group_id] ?? null;
                    $current = $existing[$s['id']] ?? null;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($s['fullname']) ?></td>
                        <td><?= $s['class_level'] ?></td>
                        <td><?= ucfirst($s['route']) ?></td>
                        <td><?= $group_id ? "Group $group_id" : 'None' ?></td>
                        <td>
                            <select name="status[<?= $s['id'] ?>]" required onchange="toggleArrivalTime(this, <?= $s['id'] ?>)">
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