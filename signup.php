<?php
ob_start();
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $first_name = '';
    $fullname = $_SESSION['fullname'] ?? '';
    if (!empty($fullname)) {
        $name_parts = explode(' ', trim($fullname));
        $first_name = $name_parts[0] ?? '';
    }
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
            <h2>Welcome back, <?= htmlspecialchars($first_name) ?>!</h2>
            <p>We wish you a joyful and meaningful use of SMART Circle.</p>
            <div class="card-buttons" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
    </body></html>
    <?php
    exit;
}

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
    <title>Sign Up - SMART Circle</title>
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
                    <input type="text" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required placeholder="e.g., Blessings Emulyn">
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required placeholder="e.g., +265 999 123 456">
                </div>
                <div class="form-group">
                    <label>Email (optional)</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="e.g., blessings@example.com">
                </div>
                <div class="form-group">
                    <label>Current School *</label>
                    <input type="text" name="school" value="<?= htmlspecialchars($_POST['school'] ?? '') ?>" required placeholder="e.g., Ntcheu Secondary School">
                </div>
                <div class="form-group">
                    <label>Password * (min 5 characters)</label>
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
    <script>
        // Save form data to sessionStorage on input change
        document.querySelectorAll('input[name="fullname"], input[name="phone"], input[name="email"], input[name="school"]').forEach(function(input) {
            input.addEventListener('input', function() {
                sessionStorage.setItem('signup_' + this.name, this.value);
            });
        });

        // Restore from sessionStorage on page load
        window.addEventListener('load', function() {
            document.querySelectorAll('input[name="fullname"], input[name="phone"], input[name="email"], input[name="school"]').forEach(function(input) {
                const stored = sessionStorage.getItem('signup_' + input.name);
                if (stored && !input.value) {
                    input.value = stored;
                }
            });
        });

        // Clear sessionStorage after successful submission
        <?php if ($success): ?>
            sessionStorage.clear();
        <?php endif; ?>
    </script>
</body>
</html>
<?php ob_end_flush(); ?>