<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
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

// Collect overall summary counts
$summary = [
    'active' => $conn->query("SELECT COUNT(*) FROM users WHERE status='active' AND approved=1")->fetch_row()[0],
    'suspended' => $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended' AND approved=1")->fetch_row()[0],
    'dismissed' => $conn->query("SELECT COUNT(*) FROM users WHERE status='dismissed' AND approved=1")->fetch_row()[0],
    'pending' => $conn->query("SELECT COUNT(*) FROM users WHERE status='pending' AND approved=1")->fetch_row()[0]
];

$classes = ['Form 3', 'Form 4'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Overview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>

<div class="class-overview">

    <!-- Status Legend -->
    <div class="status-legend">
        <div class="status-legend-item">
            <span class="status-badge status-active" tabindex="0">Active</span> Active Students
            <div class="tooltip">Active = currently enrolled and attending</div>
        </div>
        <div class="status-legend-item">
            <span class="status-badge status-suspended" tabindex="0">Suspended</span> Suspended Students
            <div class="tooltip">Suspended = temporarily restricted from classes</div>
        </div>
        <div class="status-legend-item">
            <span class="status-badge status-dismissed" tabindex="0">Dismissed</span> Dismissed Students
            <div class="tooltip">Dismissed = permanently removed from program</div>
        </div>
        <div class="status-legend-item">
            <span class="status-badge status-pending" tabindex="0">Pending</span> Pending Approval
            <div class="tooltip">Pending = awaiting admin approval</div>
        </div>
    </div>

    <!-- Summary Bar -->
    <div class="summary-bar">
        <span class="status-badge status-active">Active: <?= $summary['active'] ?></span>
        <span class="status-badge status-suspended">Suspended: <?= $summary['suspended'] ?></span>
        <span class="status-badge status-dismissed">Dismissed: <?= $summary['dismissed'] ?></span>
        <span class="status-badge status-pending">Pending: <?= $summary['pending'] ?></span>
    </div>

    <?php
    foreach ($classes as $c) {
        $cnt = $conn->query("SELECT COUNT(*) FROM users WHERE class_level='$c' AND approved=1 AND status!='dismissed'")->fetch_row()[0];
        // Add class-specific heading class
        $headingClass = strtolower(str_replace(' ', '', $c)); // "Form 3" -> "form3"
        echo "<h2 class='$headingClass'>$c: $cnt / 5 active</h2>";
        echo "<ul class='student-list'>";
        $students = $conn->query("SELECT fullname,status FROM users WHERE class_level='$c' AND approved=1 AND status!='dismissed'");
        while ($s = $students->fetch_assoc()) {
            $statusClass = '';
            switch ($s['status']) {
                case 'active': $statusClass = 'status-active'; break;
                case 'suspended': $statusClass = 'status-suspended'; break;
                case 'dismissed': $statusClass = 'status-dismissed'; break;
                default: $statusClass = 'status-pending'; break;
            }
            echo "<li>{$s['fullname']} <span class='status-badge $statusClass'>{$s['status']}</span></li>";
        }
        echo "</ul>";
    }
    ?>
    
</div>

<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    // Ensure badges and legend items are keyboard accessible
    document.querySelectorAll('.status-badge').forEach(badge => {
      badge.setAttribute('tabindex', '0');
    });
    document.querySelectorAll('.status-legend-item').forEach(item => {
      item.setAttribute('tabindex', '0');
    });
  });
</script>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>
