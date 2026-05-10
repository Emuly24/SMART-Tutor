<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine admin hash
if (function_exists('getAdminHash')) {
    $admin_hash = getAdminHash();
} elseif (defined('ADMIN_HASH')) {
    $admin_hash = ADMIN_HASH;
} else {
    $admin_hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
}

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

// Delete student
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id = $user_id");
    header("Location: admin_students_list.php");
    exit;
}

$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
if ($filter == 'suspended') {
    $sql = "SELECT id, fullname, class_level, status, suspension_end, approved 
            FROM users WHERE status = 'suspended' ORDER BY class_level";
} elseif ($filter == 'pending') {
    // Students who have submitted an application and it's pending
    $sql = "SELECT u.id, u.fullname, u.class_level, u.status, u.suspension_end, u.approved 
            FROM users u 
            JOIN applications a ON u.id = a.user_id 
            WHERE a.status = 'pending' 
            ORDER BY u.class_level";
} elseif ($filter == 'signedup') {
    // Students who registered but never applied (no application record)
    $sql = "SELECT u.id, u.fullname, u.class_level, u.status, u.suspension_end, u.approved 
            FROM users u 
            LEFT JOIN applications a ON u.id = a.user_id 
            WHERE a.id IS NULL AND u.approved = 0 
            ORDER BY u.class_level";
} else { // 'all' – active (approved + status active)
    $sql = "SELECT id, fullname, class_level, status, suspension_end, approved 
            FROM users WHERE approved = 1 AND status = 'active' ORDER BY class_level";
}
$students = $conn->query($sql);
?>
<!DOCTYPE html>
<html><head><title>Student List</title><link rel="stylesheet" href="style.css"></head>
<body class="admin-page">
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <!-- Pill‑style filter buttons (4 options) -->
        <div class="filter-links" style="margin: 1rem 0; display: flex; gap: 0.8rem; flex-wrap: wrap;">
            <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">✅ All Active</a>
            <a href="?filter=suspended" class="filter-btn <?= $filter == 'suspended' ? 'active' : '' ?>">⏸️ Suspended</a>
            <a href="?filter=pending" class="filter-btn <?= $filter == 'pending' ? 'active' : '' ?>">⏳ Pending Approval</a>
            <a href="?filter=signedup" class="filter-btn <?= $filter == 'signedup' ? 'active' : '' ?>">📝 Signed Up (Not Applied)</a>
        </div>

        <div class="grid">
        <?php if ($students->num_rows == 0): ?>
            <div class="card"><p>No students found in this category.</p></div>
        <?php else: ?>
            <?php while($s = $students->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($s['fullname']) ?></h3>
                    <p>
                        Class: <?= $s['class_level'] ?><br>
                        Status: 
                        <?php
                            if ($s['status'] === 'active') {
                                echo '<span class="status-badge status-active">Active</span>';
                            } elseif ($s['status'] === 'suspended') {
                                echo '<span class="status-badge status-suspended">Suspended</span>';
                                if ($s['suspension_end']) echo " until " . $s['suspension_end'];
                            } elseif (!$s['approved']) {
                                // For signed‑up users, they are not approved; show "Not Applied" or "Pending Approval"
                                if ($filter == 'signedup') {
                                    echo '<span class="status-badge status-pending">Not Applied</span>';
                                } elseif ($filter == 'pending') {
                                    echo '<span class="status-badge status-pending">Pending Approval</span>';
                                } else {
                                    echo '<span class="status-badge status-pending">Pending</span>';
                                }
                            } else {
                                echo htmlspecialchars($s['status']);
                            }
                        ?>
                    </p>
                    <div class="card-buttons">
                        <a href="admin_view_student.php?id=<?= $s['id'] ?>">View Details</a>
                        <a href="admin_discipline.php?user_id=<?= $s['id'] ?>">Discipline</a>
                        <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Permanently delete this student and all their data? This cannot be undone.')" style="background:#e74c3c; color:white;">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>