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

// Filters
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where = [];
$params = [];
$types = '';
if ($user_filter) {
    $where[] = "l.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}
if ($action_filter) {
    $where[] = "l.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}
if ($search) {
    $where[] = "(l.details LIKE ? OR u.fullname LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}
$where_sql = empty($where) ? '' : "WHERE " . implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Count total
$count_sql = "SELECT COUNT(*) as cnt FROM activity_log l JOIN users u ON l.user_id = u.id $where_sql";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

// Fetch logs
$sql = "SELECT l.*, u.fullname FROM activity_log l JOIN users u ON l.user_id = u.id $where_sql ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

// Fetch distinct actions for filter dropdown
$actions = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action");
// Fetch users for filter dropdown
$users = $conn->query("SELECT id, fullname FROM users WHERE approved = 1 ORDER BY fullname");
?>
<!DOCTYPE html>
<html><head><title>Admin Activity Log</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <h1>Admin Activity Log</h1>
    <form method="get" class="filter-form" style="margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
        <div class="form-group">
            <label>User:</label>
            <select name="user">
                <option value="0">All</option>
                <?php while($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['id'] ?>" <?= ($user_filter == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['fullname']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Action:</label>
            <select name="action">
                <option value="">All</option>
                <?php while($a = $actions->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($a['action']) ?>" <?= ($action_filter == $a['action']) ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Search:</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Details or student name">
        </div>
        <button type="submit" class="btn">Filter</button>
        <a href="admin_activity_log.php" class="btn-secondary">Reset</a>
    </form>

    <?php if ($total_rows == 0): ?>
        <div class="card"><p>No activity logs found.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Date & Time</th><th>Student</th><th>Action</th><th>Details</th><th>IP Address</th></tr>
            </thead>
            <tbody>
            <?php while($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars($log['fullname']) ?> (ID <?= $log['user_id'] ?>)</td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= nl2br(htmlspecialchars($log['details'])) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <div class="pagination" style="margin: 1rem 0; text-align: center;">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" class="btn-secondary">◀ Previous</a>
            <?php endif; ?>
            <span>Page <?= $page ?> of <?= $total_pages ?></span>
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" class="btn-secondary">Next ▶</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>