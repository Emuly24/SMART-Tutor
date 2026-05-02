<?php
require_once 'config.php';
session_start();
$admin_hash = getAdminHash();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $admin_hash)) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['role'] = 'admin';
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'Invalid admin password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="login-container" style="max-width: 400px;">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</div>
</body>
</html>