<?php
// Ensure getAdminHash() exists (fallback in case config.php doesn't have it)
if (!function_exists('getAdminHash')) {
    function getAdminHash() {
        static $hash = null;
        if ($hash !== null) return $hash;
        // Try to include config.php for database connection
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/config.php';
        }
        $conn = getDB();
        $result = $conn->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'admin_hash'");
        if ($result && $row = $result->fetch_assoc()) {
            $hash = $row['setting_value'];
        } else {
            // Default hash for 'smarttutor@2026'
            $hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
        }
        return $hash;
    }
}

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
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>