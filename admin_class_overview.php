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

// Collect overall summary counts
$summary = [
    'active' => $conn->query("SELECT COUNT(*) FROM users WHERE status='active' AND approved=1")->fetch_row()[0],
    'suspended' => $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended' AND approved=1")->fetch_row()[0],
    'dismissed' => $conn->query("SELECT COUNT(*) FROM users WHERE status='dismissed' AND approved=1")->fetch_row()[0],
    'pending' => $conn->query("SELECT COUNT(*) FROM users WHERE approved=0 AND EXISTS (SELECT 1 FROM applications WHERE user_id = users.id)")->fetch_row()[0],
    'signedup_notapplied' => $conn->query("SELECT COUNT(*) FROM users WHERE approved=0 AND NOT EXISTS (SELECT 1 FROM applications WHERE user_id = users.id)")->fetch_row()[0]
];

$classes = ['Form 3', 'Form 4'];
$routes = ['sciences', 'humanities'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Overview - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .overview-buttons {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin: 1rem 0 2rem 0;
        }
        .overview-btn {
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: none;
            cursor: default;
        }
        .overview-btn.active { background: var(--success); color: white; }
        .overview-btn.suspended { background: var(--warning); color: white; }
        .overview-btn.dismissed { background: var(--error); color: white; }
        .overview-btn.pending { background: var(--info); color: white; }
        .overview-btn.signedup { background: var(--primary-medium); color: white; }
        .overview-btn:hover { transform: translateY(-2px); filter: brightness(0.95); }
        .no-members { color: var(--text-muted); font-style: italic; }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="class-overview" style="padding: 0 1.5rem;">
        
        <!-- Clean Overview Buttons -->
        <div class="overview-buttons">
            <span class="overview-btn active">✅ Active: <?= $summary['active'] ?></span>
            <span class="overview-btn suspended">⏸️ Suspended: <?= $summary['suspended'] ?></span>
            <span class="overview-btn dismissed">⛔ Dismissed: <?= $summary['dismissed'] ?></span>
            <span class="overview-btn pending">⏳ Pending Approval: <?= $summary['pending'] ?></span>
            <span class="overview-btn signedup">📝 Signed Up (Not Applied): <?= $summary['signedup_notapplied'] ?></span>
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
                        <h3 style="margin-top: 1.5rem;"><?= $route_label ?></h3>
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
                                            echo "<li><em class='no-members'>No members</em></li>";
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
</body>
</html>