<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$user = $conn->query("SELECT approved, consent_signed, class_level, status FROM users WHERE id=$uid")->fetch_assoc();
$application = $conn->query("SELECT * FROM applications WHERE user_id=$uid")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Approval Status – SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="card">
        <h2><i class="fas fa-user-check"></i> Admin Approval Status</h2>
        <?php if ($user['approved'] == 1): ?>
            <div class="success">
                <p><strong>✅ Congratulations! Your application has been approved.</strong></p>
                <p>You may now proceed to sign the consent agreement and start your journey.</p>
                <a href="consent.php" class="btn">Sign Consent Form</a>
            </div>
        <?php elseif ($user['approved'] == 0 && isset($application['status']) && $application['status'] == 'rejected'): ?>
            <div class="error">
                <p><strong>❌ Your application has been rejected.</strong></p>
                <p>Reason: <?= htmlspecialchars($application['admin_notes'] ?? 'No specific reason provided.') ?></p>
                <p>If you believe this is a mistake, please contact the admin directly.</p>
                <a href="contact.php" class="btn">Contact Admin</a>
            </div>
        <?php else: ?>
            <div class="warning">
                <p><strong>⏳ Your application is still pending review.</strong></p>
                <p>Please check back later. You will be notified once the admin makes a decision.</p>
            </div>
        <?php endif; ?>
        <p><a href="dashboard.php" class="btn-secondary">Back to Dashboard</a></p>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>
</body>
</html>