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
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'];
    $conf = $_POST['confirm_password'];
    if (strlen($new) < 5) $msg = "Password must be at least 5 characters.";
    elseif ($new !== $conf) $msg = "Passwords do not match.";
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        file_put_contents('admin_config.php', "<?php\nreturn ['hash' => '$hash'];\n");
        $msg = "Password updated. Use the new password on next login.";
    }
}
?>
<!DOCTYPE html><html><head><title>Admin Settings</title>    <link rel="stylesheet" href="style.css">
</head><body>
<div class="container">
<div class="header"><h1>admin_settings</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div class="content-grid">
<h1>🔧 Admin Settings</h1><p><?=htmlspecialchars($msg)?></p><form method="post"><label>New password (min 5 characters)</label><input type="password" name="new_password" required><label>Confirm password</label><input type="password" name="confirm_password" required><button type="submit">Update Password</button></form><a href="admin_dashboard.php">Back</a>
</div>
<div class="footer">SMART Tutor – Admin Panel</div>
</div>
</body></html>