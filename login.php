<?php
require_once 'config.php';
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];
    if (empty($login) || empty($pass)) {
        $error = "Enter phone/email and password.";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end FROM users WHERE phone = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'student';
            unset($_SESSION['admin_logged']);
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['approved'] = $user['approved'];
            $_SESSION['consent_signed'] = $user['consent_signed'];
            $_SESSION['status'] = $user['status'];
            $_SESSION['suspension_end'] = $user['suspension_end'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
</head>
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
            <button type="submit" class="btn btn-login">Login</button>
        </form>
        <div class="login-links">
            <a href="signup.php">Don’t have an account? Sign up here</a>
            <!-- <a href="forgot_password.php">Forgot password?</a> -->
        </div>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</body>
</html>