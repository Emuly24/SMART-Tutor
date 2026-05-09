<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

// === CHANGE START: Show options instead of redirect ===
if (isset($_SESSION['user_id'])) {
    // Handle account deletion request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
        $uid = $_SESSION['user_id'];
        $conn = getDB();
        $conn->query("DELETE FROM users WHERE id = $uid");
        session_destroy();
        header("Location: signup.php?msg=deleted");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html><head><title>Already Signed Up</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="container">
        <div class="card">
            <h2>You already have an account</h2>
            <p>You are currently logged in as <strong><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></strong>.</p>
            <p>Would you like to delete this account and start over? (This action is permanent and cannot be undone.)</p>
            <p>If this is your friend using your phone, please log out and let them sign up.</p>
            <div class="card-buttons">
                <form method="post" style="display:inline;">
                    <button type="submit" name="delete_account" class="btn-danger" onclick="return confirm('Delete your account permanently? All your data will be lost.')">Delete My Account</button>
                </form>
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-secondary">Logout</a>
            </div>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}
// === CHANGE END ===

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $school = trim($_POST['school']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($fullname) || empty($phone) || empty($school) || empty($password)) {
        $error = "Full name, phone, school, and password are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 5) {
        $error = "Password must be at least 5 characters.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        $conn = getDB();
        $check = $conn->query("SELECT id FROM users WHERE phone = '$phone'");
        if ($check->num_rows) {
            $error = "Phone number already registered. Please login or use a different number.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (fullname, phone, email, school, password, approved) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssss", $fullname, $phone, $email, $school, $hashed);
            if ($stmt->execute()) {
                $success = "Account created successfully! You can now login and complete your application.";
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="signup-page">
    <?php include_once 'includes/header.php'; ?>

    <?php include_once 'includes/progress_tracker.php'; ?>

<div class="signup-container">
    

    <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?> <a href="login.php">Login now</a>
        </div>
    <?php endif; ?>

    <?php if(!$success): ?>
        <form method="post">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="fullname" required placeholder="e.g., Blessings Emulyn">
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" required placeholder="e.g., +265 999 123 456">
            </div>
            <div class="form-group">
                <label>Email (optional)</label>
                <input type="email" name="email" placeholder="e.g., blessings@example.com">
            </div>
            <div class="form-group">
                <label>Current School *</label>
                <input type="text" name="school" required placeholder="e.g., Ntcheu Secondary School">
            </div>
            <div class="form-group">
                <label>Password * (min 5 chars)</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Sign Up</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    <?php endif; ?>
</div>
<div class="footer"><a href="index.php" class="btn-back">← Back</a></div>


<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>
