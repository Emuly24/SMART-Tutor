<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
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

$_SESSION['class_level'] = $user['class_level'];
$_SESSION['approved'] = $user['approved'];
$_SESSION['consent_signed'] = $user['consent_signed'];
$_SESSION['status'] = $user['status'];

$current = basename($_SERVER['SCRIPT_NAME']);
$allowed_public = ['index.php', 'signup.php', 'login.php', 'logout.php'];

// --- NOT APPROVED ---
if (!$user['approved']) {
    $allowed = array_merge($allowed_public, ['apply.php', 'profile.php', 'notifications.php']);
    if (!in_array($current, $allowed)) {
        header("Location: apply.php");
        exit;
    }
    return;
}

// --- APPROVED BUT CONSENT NOT SIGNED ---
if (!$user['consent_signed']) {
    $allowed = array_merge($allowed_public, ['consent.php', 'profile.php', 'notifications.php']);
    if (!in_array($current, $allowed)) {
        header("Location: consent.php");
        exit;
    }
    return;
}

// --- SUSPENDED / DISMISSED ---
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) {
        die("<!DOCTYPE html><html><head><title>Suspended</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Account Suspended</h1></div><div class='error'>You are suspended until $end. Contact admin.</div><a href='logout.php'>Logout</a></div></body></html>");
    } else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
    }
}
if ($user['status'] == 'dismissed') {
    die("<!DOCTYPE html><html><head><title>Dismissed</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Access Denied</h1></div><div class='error'>You have been dismissed.</div><a href='logout.php'>Logout</a></div></body></html>");
}

// Fully approved and consented – allow all pages
?>