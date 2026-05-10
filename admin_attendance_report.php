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
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-30 days'));
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$class_filter = isset($_GET['class_level']) ? $_GET['class_level'] : '';
$route_filter = isset($_GET['route']) ? $_GET['route'] : '';

// Build filter conditions
$where = "a.date BETWEEN '$start' AND '$end'";
if ($class_filter && $class_filter != 'all') {
    $where .= " AND u.class_level = '$class_filter'";
}
if ($route_filter && $route_filter != 'all') {
    $where .= " AND u.route = '$route_filter'";
}

// ---- Summary per student ----
$summary = $conn->query("SELECT u.fullname, u.class_level, u.route,
    SUM(CASE WHEN a.status = 'on_time' THEN 1 ELSE 0 END) as on_time,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
    COUNT(*) as total
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE $where
    GROUP BY u.id
    ORDER BY u.class_level, u.route, u.fullname");

// ---- Detailed records ----
$records = $conn->query("SELECT u.fullname, u.class_level, u.route, 
    a.date, a.status, a.arrival_time, a.remarks, a.late_reason
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE $where
    ORDER BY u.class_level, u.route, u.fullname, a.date DESC");
?>
<!DOCTYPE html>
<html><head><title>Attendance Report</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Attendance Report (Detailed + Summary)</h1>
        <form method="get" class="filter-form" style="margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <div class="form-group"><label>From:</label><input type="date" name="start" value="<?= $start ?>"></div>
            <div class="form-group"><label>To:</label><input type="date" name="end" value="<?= $end ?>"></div>
            <div class="form-group"><label>Class:</label>
                <select name="class_level">
                    <option value="all">All</option>
                    <option value="Form 3" <?= $class_filter == 'Form 3' ? 'selected' : '' ?>>Form 3</option>
                    <option value="Form 4" <?= $class_filter == 'Form 4' ? 'selected' : '' ?>>Form 4</option>
                </select>
            </div>
            <div class="form-group"><label>Route:</label>
                <select name="route">
                    <option value="all">All</option>
                    <option value="sciences" <?= $route_filter == 'sciences' ? 'selected' : '' ?>>Sciences</option>
                    <option value="humanities" <?= $route_filter == 'humanities' ? 'selected' : '' ?>>Humanities</option>
                </select>
            </div>
            <button type="submit" class="btn">Filter</button>
            <a href="admin_attendance_report.php" class="btn-secondary">Reset</a>
        </form>

        <!-- Summary Section -->
        <div class="card">
            <h2>Summary per Student</h2>
            <?php if ($summary->num_rows == 0): ?>
                <p>No attendance records found for the selected period.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>Student</th><th>Class</th><th>Route</th><th>✅ On Time</th><th>⏰ Late</th><th>❌ Absent</th><th>Total Days</th></tr>
                    </thead>
                    <tbody>
                    <?php while($s = $summary->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['fullname']) ?></td>
                            <td><?= $s['class_level'] ?></td>
                            <td><?= ucfirst($s['route']) ?></td>
                            <td><?= $s['on_time'] ?></td>
                            <td><?= $s['late'] ?></td>
                            <td><?= $s['absent'] ?></td>
                            <td><?= $s['total'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Detailed Records -->
        <div class="card">
            <h2>Detailed Attendance Records</h2>
            <?php if ($records->num_rows == 0): ?>
                <p>No detailed records found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th><th>Class</th><th>Route</th><th>Date</th>
                            <th>Status</th><th>Arrival Time</th><th>Admin Remarks</th><th>Student's Late Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($r = $records->fetch_assoc()): 
                        $status_display = match($r['status']) {
                            'on_time' => '✅ On time',
                            'late' => '⏰ Late',
                            'absent' => '❌ Absent',
                            default => ucfirst($r['status'])
                        };
                        $arrival = $r['arrival_time'] ? date('h:i A', strtotime($r['arrival_time'])) : '—';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= $r['class_level'] ?></td>
                            <td><?= ucfirst($r['route']) ?></td>
                            <td><?= $r['date'] ?></td>
                            <td><?= $status_display ?></td>
                            <td><?= $arrival ?></td>
                            <td><?= nl2br(htmlspecialchars($r['remarks'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($r['late_reason'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>