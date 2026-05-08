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

// Collect overall summary counts
$summary = [
    'active' => $conn->query("SELECT COUNT(*) FROM users WHERE status='active' AND approved=1")->fetch_row()[0],
    'suspended' => $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended' AND approved=1")->fetch_row()[0],
    'dismissed' => $conn->query("SELECT COUNT(*) FROM users WHERE status='dismissed' AND approved=1")->fetch_row()[0],
    'pending' => $conn->query("SELECT COUNT(*) FROM users WHERE approved=0")->fetch_row()[0]
];

$classes = ['Form 3', 'Form 4'];
$routes = ['sciences', 'humanities'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Overview - SMART Tutor</title>
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

        <?php foreach ($classes as $class): ?>
            <div class="class-section">
                <h2><?= $class ?> Classes</h2>
                <?php foreach ($routes as $route): ?>
                    <?php
                    // Get groups of this class and route
                    $groups = $conn->query("SELECT id, group_number, 
                        (SELECT COUNT(*) FROM group_members WHERE group_id = groups.id) as member_count
                        FROM groups WHERE class_level = '$class' AND route = '$route' ORDER BY group_number");
                    if ($groups->num_rows == 0) continue;
                    $route_label = ucfirst($route);
                    ?>
                    <div class="route-section">
                        <h3><?= $route_label ?></h3>
                        <div class="groups-grid" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <?php while ($g = $groups->fetch_assoc()): ?>
                                <div class="card" style="flex:1; min-width: 200px;">
                                    <h4>Group <?= $g['group_number'] ?> (<?= $g['member_count'] ?>/5)</h4>
                                    <ul class="student-list" style="list-style: none; padding-left: 0;">
                                        <?php
                                        $members = $conn->query("SELECT u.fullname, u.status 
                                            FROM group_members gm 
                                            JOIN users u ON gm.user_id = u.id 
                                            WHERE gm.group_id = {$g['id']} AND u.status != 'dismissed'
                                            ORDER BY u.fullname");
                                        if ($members->num_rows == 0):
                                            echo "<li><em>No members</em></li>";
                                        else:
                                            while ($m = $members->fetch_assoc()):
                                                $statusClass = match($m['status']) {
                                                    'active' => 'status-active',
                                                    'suspended' => 'status-suspended',
                                                    default => 'status-pending'
                                                };
                                                echo "<li>{$m['fullname']} <span class='status-badge $statusClass'>{$m['status']}</span></li>";
                                            endwhile;
                                        endif;
                                        ?>
                                    </ul>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <script>
        document.querySelectorAll('.status-badge, .status-legend-item').forEach(el => el.setAttribute('tabindex', '0'));
    </script>
</body>
</html>