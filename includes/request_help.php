<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question']);
    $subject = trim($_POST['subject']);
    if (empty($question)) {
        $error = "Please write your question.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_messages (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $uid, $subject, $question);
        if ($stmt->execute()) {
            $success = "Your question has been sent to the admin. You will receive a response in your notifications.";
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request Help – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <div class="apply-container" style="max-width: 700px;">
        <h2>Ask for Help</h2>
        <p>Subject: <strong><?= htmlspecialchars($subject) ?></strong></p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <p><a href="subject.php?subject=<?= urlencode($subject) ?>">← Back to Subject</a></p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
                <div class="form-group">
                    <label>Your Question *</label>
                    <textarea name="question" rows="6" required placeholder="Explain what you didn't understand, which topic/concept, and what you've tried so far..."></textarea>
                </div>
                <button type="submit">Send Question</button>
            </form>
            <p><a href="subject.php?subject=<?= urlencode($subject) ?>">← Back to Subject</a></p>
        <?php endif; ?>
    </div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>