<?php
// Ensure session is started so we can check login status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the current file name dynamically
$current_file = basename($_SERVER['PHP_SELF']);
?>
<div class="footer">
    <!-- Dynamic Back Button: returns to the previous page -->
    <button onclick="window.history.back()" class="btn-back">← Back</button>
    
    <!-- Home Button: always goes to index.php -->
    <a href="index.php" class="btn-home">🏠 Home</a>

    <!-- ===== DYNAMIC ADDITIONAL BUTTONS ===== -->
    <?php if ($current_file == 'index.php'): ?>
        <!-- Homepage -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Logged‑in user sees Go to Dashboard -->
            <a href="dashboard.php" class="btn">Go to Dashboard</a>
        <?php else: ?>
            <!-- Public user sees Login & Sign Up -->
            <a href="login.php" class="btn">Login</a>
            <a href="signup.php" class="btn">Sign Up</a>
        <?php endif; ?>
    
    <?php elseif ($current_file == 'login.php'): ?>
        <!-- Login page: Show Sign Up -->
        <a href="signup.php" class="btn">Sign Up</a>
    
    <?php elseif ($current_file == 'signup.php'): ?>
        <!-- Sign Up page: Show Login -->
        <a href="login.php" class="btn">Login</a>

    <?php elseif ($current_file == 'apply.php'): ?>
        <!-- Application page: Show Dashboard -->
        <a href="dashboard.php" class="btn">Dashboard</a>

    <?php elseif ($current_file == 'pending.php' || $current_file == 'consent.php'): ?>
        <!-- Pending/Consent pages: Show Dashboard -->
        <a href="dashboard.php" class="btn">Dashboard</a>

    <?php endif; ?>
    <!-- ========================================= -->
    
    <br><br>
    <span style="color: var(--text-muted); font-size: 0.9rem;">SMART Circle – A digital learning community built for your future</span>
</div>