<?php
require_once 'config.php';
session_start();

$admin_hash = getAdminHash();
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
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'];
    $conf = $_POST['confirm_password'];
    if (strlen($new) < 5) {
        $msg = "Password must be at least 5 characters.";
    } elseif ($new !== $conf) {
        $msg = "Passwords do not match.";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE admin_settings SET setting_value = '$new_hash' WHERE setting_key = 'admin_hash'");
        $msg = "Password updated. Use the new password on next login.";
    }
}
?>
<!DOCTYPE html>
<html><head><title>Admin Settings</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php if ($msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <form method="post">
                <label>New password (min 5 characters)</label>
                <input type="password" name="new_password" required>
                <label>Confirm password</label>
                <input type="password" name="confirm_password" required>
                <button type="submit">Update Password</button>
            </form>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
</body>
</html>