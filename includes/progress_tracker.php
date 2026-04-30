<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="progress-tracker">
  <a href="index.php" class="progress-step <?= $current=='index.php' ? 'active' : '' ?>" title="Go to Home">
    <i class="fas fa-home"></i> Home
  </a>
  <a href="signup.php" class="progress-step <?= $current=='signup.php' ? 'active' : '' ?>" title="Create your account">
    <i class="fas fa-user-plus"></i> Signup
  </a>
  <a href="login.php" class="progress-step <?= $current=='login.php' ? 'active' : '' ?>" title="Login to continue">
    <i class="fas fa-sign-in-alt"></i> Login
  </a>
  <a href="apply.php" class="progress-step <?= $current=='apply.php' ? 'active' : '' ?>" title="Fill in your application">
    <i class="fas fa-file-alt"></i> Application
  </a>
  <a href="pending.php" class="progress-step <?= $current=='pending.php' ? 'active' : '' ?>" title="Awaiting admin review">
    <i class="fas fa-hourglass-half"></i> Pending Approval
  </a>
  <a href="admin_approve.php" class="progress-step <?= $current=='admin_approve.php' ? 'active' : '' ?>" title="Admin approval stage">
    <i class="fas fa-user-check"></i> Admin Approval
  </a>
  <a href="notifications.php" class="progress-step <?= $current=='notifications.php' ? 'active' : '' ?>" title="Check your notifications">
    <i class="fas fa-bell"></i> Notification
  </a>
  <a href="consent.php" class="progress-step <?= $current=='consent.php' ? 'active' : '' ?>" title="Sign the consent agreement">
    <i class="fas fa-file-signature"></i> Consent
  </a>
  <a href="dashboard.php" class="progress-step <?= $current=='dashboard.php' ? 'active' : '' ?>" title="Access your full dashboard">
    <i class="fas fa-tachometer-alt"></i> Dashboard
  </a>
</div>

<!-- Progress indicator bar -->
<div class="progress-indicator">
  <div class="progress-indicator-fill"></div>
</div>