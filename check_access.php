<?php
require_once 'check_remember_me.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in, only allow public pages
if (!isset($_SESSION['user_id'])) {
    $public_pages = ['index.php', 'signup.php', 'login.php', 'logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME']);
    if (!in_array($current, $public_pages)) {
        header("Location: login.php");
        exit;
    }
    return; // Allow access to public pages
}

// User is logged in – fetch their status
$conn = getDB();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT approved, consent_signed, status, suspension_end, class_level FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Store session variables
$_SESSION['class_level'] = $user['class_level'];
$_SESSION['approved'] = $user['approved'];
$_SESSION['consent_signed'] = $user['consent_signed'];
$_SESSION['status'] = $user['status'];

$current = basename($_SERVER['SCRIPT_NAME']);
$always_allowed = ['index.php', 'logout.php', 'profile.php', 'notifications.php', 'apply.php'];

// --- 1. NOT APPROVED (no application) → FORCED TO APPLY.PHP ALWAYS ---
if (!$user['approved']) {
    $has_application = $conn->query("SELECT id FROM applications WHERE user_id = $user_id")->num_rows > 0;
    
    if (!$has_application) {
        // Strict enforcement: only allow apply.php (plus basic pages)
        $allowed = array_merge($always_allowed, ['apply.php']);
        if (!in_array($current, $allowed)) {
            header("Location: apply.php");
            exit;
        }
    } else {
        // Has application but not approved → only pending.php and approval_status.php
        $allowed = array_merge($always_allowed, ['pending.php', 'approval_status.php']);
        if (!in_array($current, $allowed)) {
            header("Location: pending.php");
            exit;
        }
    }
    return;
}

// --- 2. APPROVED BUT CONSENT NOT SIGNED → must go to consent.php ---
if (!$user['consent_signed']) {
    $allowed = array_merge($always_allowed, ['consent.php']);
    if (!in_array($current, $allowed)) {
        header("Location: consent.php");
        exit;
    }
    return;
}

// --- 3. SUSPENDED / DISMISSED ---
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) {
        die('<!DOCTYPE html><html><head><title>Suspended</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Account Suspended</h1><p>You are suspended until ' . $end . '. Contact the admin.</p><a href="logout.php" class="btn-danger">Logout</a></div></div><a href="#" class="back-to-top" id="backToTop">↑</a></body></html>');
    } else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
        $_SESSION['status'] = 'active';
    }
}
if ($user['status'] == 'dismissed') {
    die('<!DOCTYPE html><html><head><title>Dismissed</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Access Denied</h1><p>You have been dismissed from SMART Circle.</p><a href="logout.php" class="btn-danger">Logout</a></div></div><a href="#" class="back-to-top" id="backToTop">↑</a></body></html>');
}

// --- 4. FULLY APPROVED AND CONSENT SIGNED → full access ---
// No restrictions – allow all pages
return;