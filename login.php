<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === Already logged in (captures first name) ===
if (isset($_SESSION['user_id'])) {
    $first_name = '';

    // 1. Try to get fullname from session first
    $fullname = $_SESSION['fullname'] ?? '';

    if (!empty($fullname)) {
        $name_parts = explode(' ', trim($fullname));
        $first_name = $name_parts[0] ?? '';
    }

    // 2. If session failed, fall back to database query
    if (empty($first_name)) {
        $conn = getDB();
        $uid = (int)$_SESSION['user_id'];
        $result = $conn->query("SELECT fullname FROM users WHERE id = $uid");
        if ($result && $user = $result->fetch_assoc()) {
            $fullname = $user['fullname'] ?? '';
            $name_parts = explode(' ', trim($fullname));
            $first_name = $name_parts[0] ?? '';
        }
    }

    // 3. If still empty, display "User"
    if (empty($first_name)) {
        $first_name = 'User';
    }
    ?>
    <!DOCTYPE html>
    <html><head><title>Already Logged In</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="container">
        <div class="card">
            <h2>You are already logged in</h2>
            <p>You are currently logged in as <strong><?= htmlspecialchars($first_name) ?></strong>.</p>
            <p>Do you want to log out and sign in with a different account?</p>
            <div class="card-buttons" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($login) || empty($pass)) {
        $error = "Enter phone/email and password.";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];

            if (function_exists('log_activity')) {
                log_activity($user['id'], "login", "Logged in via login form");
            }

            // --- Role determination ---
            if (isset($user['role']) && $user['role'] === 'admin') {
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_logged'] = true;
                unset($_SESSION['user_id']);
            } else {
                $_SESSION['role'] = 'student';
                unset($_SESSION['admin_logged']);
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['consent_signed'] = $user['consent_signed'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['suspension_end'] = $user['suspension_end'];
            }

            // --- Remember Me (Ensure cookie is set correctly) ---
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $conn->query("DELETE FROM remember_tokens WHERE user_id = {$user['id']}");
                $stmt2 = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $user['id'], $token, $expires);
                $stmt2->execute();
                setcookie('remember_me', $token, time() + 86400 * 30, '/', '', false, true);
            }

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Login - SMART Tutor</title><link rel="stylesheet" href="style.css"></head>
<body class="login-page">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="login-container">
        <h2 class="login-title">Welcome Back</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="login">Phone Number or Email</label>
                <input type="text" id="login" name="login" required placeholder="Enter your phone or email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            <div class="form-group">
                <label class="distinct-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Remember Me</span>
                </label>
            </div>
            <button type="submit" class="btn btn-login">Login</button>
        </form>
        <div class="login-links">
            <a href="signup.php">Don’t have an account? Sign up here</a>
            <a href="forgot_password.php">Forgot password?</a>
        </div>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</body>
</html>