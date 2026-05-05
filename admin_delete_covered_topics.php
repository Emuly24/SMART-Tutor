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
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = $_POST['class_level'];
    $older = $_POST['older_than'];
    $where = [];
    $params = [];
    $types = "";
    if ($class && $class != 'all') { $where[] = "class_level=?"; $params[] = $class; $types .= "s"; }
    if ($older) { $where[] = "covered_date < ?"; $params[] = $older; $types .= "s"; }
    if (empty($where)) $msg = "Select a filter.";
    else {
        $sql = "DELETE FROM topics_covered WHERE " . implode(" AND ", $where);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $msg = "Deleted " . $stmt->affected_rows . " record(s).";
    }
}
?>
<!DOCTYPE html><html><head><title>Delete Covered Topics</title>    <link rel="stylesheet" href="style.css">
</head><body>
    <?php include_once 'includes/header.php'; ?>

    

<div class="admin-page">
    <h2>Batch Delete Covered Topics</h2>
    <form method="post">
        <div class="form-group">
            <label for="class">Class:</label>
            <select id="class" name="class">
                <option>All</option>
            </select>
        </div>

        <div class="form-group">
            <label for="delete_date">Delete older than date:</label>
            <input type="date" id="delete_date" name="delete_date" placeholder="dd/mm/yyyy">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-delete">Delete</button>
        </div>
    </form>
</div>

</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>