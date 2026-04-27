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
    <div class="login-container">
        <h2>Welcome Back</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Phone Number or Email</label>
                <input type="text" name="login" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p style="text-align:center; margin-top:1rem;">
            Don't have an account? <a href="signup.php">Sign up here</a>
        </p>
        <p style="text-align:center; margin-top:0.5rem;">
            <a href="forgot_password.php">Forgot password?</a>
        </p>
    </div>
</body>
</html>