<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = getDB();
$uid = $_SESSION['user_id'];

$sql = "SELECT status, admin_notes FROM applications WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$app = $result->fetch_assoc();

$status = $app ? $app['status'] : 'none';
$rejection_reason = $app ? $app['admin_notes'] : '';

if ($status === 'approved') {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Application Pending";
$main_icon = "fa-hourglass-half";
$main_color_class = "border-top-accent";
$status_section = 'pending';

if ($status === 'rejected') {
    $page_title = "Application Not Approved";
    $main_icon = "fa-times-circle";
    $main_color_class = "border-top-error";
    $status_section = 'rejected';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $page_title ?> – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* === New classes to support pending.php (no inline styles) === */
        .pending-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .pending-card {
            padding: 2.5rem;
        }
        .pending-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        .border-top-accent {
            border-top: 5px solid var(--accent);
        }
        .border-top-error {
            border-top: 5px solid var(--error);
        }
        .pending-list {
            list-style: none;
            padding: 0;
            display: grid;
            gap: 0.8rem;
        }
        .pending-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }
        .pending-check {
            background: var(--accent);
            color: #1e293b;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        .rejection-box {
            background: #fef2f2;
            border-left: 5px solid var(--error);
            padding: 1.2rem;
            border-radius: 0.8rem;
            margin: 1.5rem 0;
        }
        .rejection-box h4 {
            color: var(--error);
            margin-bottom: 0.5rem;
        }
        .pending-footnote {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: center;
            border-top: 1px solid var(--card-alt-bg);
            padding-top: 1rem;
        }
        .no-app-box {
            background: var(--info);
            color: white;
            padding: 1.2rem;
            border-radius: 1rem;
            margin: 1.5rem 0;
        }
        .text-center {
            text-align: center;
        }
        .text-center .pending-icon {
            margin-left: auto;
            margin-right: auto;
        }
        .info-box {
            background: var(--card-alt-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            margin: 1.5rem 0;
        }
        .info-box h4 {
            margin-bottom: 1rem;
        }
        .mt-1 { margin-top: 1rem; }
        .mt-2 { margin-top: 1.5rem; }
    </style>
</head>
<body class="pending-page">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>

    <div class="container pending-container">
        <div class="card pending-card <?= $main_color_class ?>">
            
            <!-- Main Icon & Heading -->
            <div class="text-center">
                <i class="fas <?= $main_icon ?> pending-icon" style="color: var(--accent);"></i>
                <h2 style="color: var(--accent);"><?= $page_title ?></h2>
            </div>

            <?php if ($status === 'pending'): ?>
                <!-- PENDING STATE -->
                <p class="lead">Thank you for submitting your application. Your details have been received and are currently under review by the SMART Circle admin team.</p>
                
                <div class="info-box">
                    <h4><i class="fas fa-arrow-right"></i> What happens next:</h4>
                    <ul class="pending-list">
                        <li>
                            <span class="pending-check">✓</span>
                            <span>Your application will be carefully checked for completeness and eligibility.</span>
                        </li>
                        <li>
                            <span class="pending-check">✓</span>
                            <span>Approval may take some time depending on the number of applications being processed.</span>
                        </li>
                        <li>
                            <span class="pending-check">✓</span>
                            <span>Once approved, you will receive a notification in your profile and be taken to the consent agreement.</span>
                        </li>
                    </ul>
                </div>
                <p class="pending-footnote">
                    <i class="fas fa-clock"></i> Please be patient — this step ensures that only disciplined and committed students join SMART Circle groups.
                </p>

            <?php elseif ($status === 'rejected'): ?>
                <!-- REJECTED STATE -->
                <div class="rejection-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Your application was not approved</h4>
                    <p>
                        <?= htmlspecialchars($rejection_reason ?: 'No specific reason was provided.') ?>
                    </p>
                </div>
                <div class="text-center mt-2">
                    <p>You are welcome to <strong>re-apply</strong> if you believe there was an error or if your circumstances have changed.</p>
                    <a href="apply.php" class="btn btn-secondary mt-1">Re-apply Now</a>
                </div>
            
            <?php else: ?>
                <!-- NO APPLICATION STATE -->
                <div class="no-app-box">
                    <p><i class="fas fa-info-circle"></i> You have not submitted an application yet.</p>
                </div>
                <div class="text-center">
                    <a href="apply.php" class="btn btn-primary">Complete Your Application</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="footer">
        <a href="index.php" class="btn-back">← Back to Home</a>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>