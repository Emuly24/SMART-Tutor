<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Application Pending – SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="pending-page">
    <?php include_once 'includes/header.php'; ?>

    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="note-container">
        <div class="card">
            <h2><i class="fas fa-hourglass-half"></i> Application Pending Approval</h2>
            <p>Thank you for submitting your application. Your details have been received and are currently under review by the SMART Tutor admin team.</p>
            <p><strong>What happens next:</strong></p>
            <ul>
                <li>Your application will be carefully checked for completeness and eligibility.</li>
                <li>Approval may take some time depending on the number of applications being processed.</li>
                <li>Once approved, you will receive a notification in your profile.</li>
                <li>You will then be asked to sign the consent agreement before gaining full access to the dashboard.</li>
            </ul>
            <p>Please be patient — this step ensures that only disciplined and committed students join SMART Tutor groups.</p>
        </div>
    </div>

    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>


<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>
