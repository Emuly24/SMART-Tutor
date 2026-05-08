<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    if (empty($subject) || empty($message)) {
        $error = "Please fill both subject and message.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_messages (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $uid, $subject, $message);
        if ($stmt->execute()) {
            $success = "Your message has been sent to the admin. Thank you for your feedback.";
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Contact Admin</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
<div class="container">
    
    
    <p>Use this form to report issues, suggest improvements, or ask questions. The admin will respond as soon as possible.</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <form method="post">
            <div class="form-group"><label>Subject *</label><input type="text" name="subject" required></div>
            <div class="form-group"><label>Message *</label><textarea name="message" rows="5" required></textarea></div>
            <button type="submit" class="btn">Send Message</button>
        </form>
    </div>
    <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>