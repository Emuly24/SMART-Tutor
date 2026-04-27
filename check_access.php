<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$conn = getDB();
if ($conn->connect_error) die("DB error");
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT approved, consent_signed, status, suspension_end FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$conn->close();
if (!$user) { session_destroy(); header("Location: login.php"); exit; }
if (!$user['approved']) die("<!DOCTYPE html><html><head><title>Pending</title>    <link rel="stylesheet" href="style.css">
</head><body><h2>⏳ Application Pending</h2><p>Your application is under review.</p><a href='logout.php'>Logout</a></body></html>");
if (!$user['consent_signed']) { header("Location: consent.php"); exit; }
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) die("<!DOCTYPE html><html><head><title>Suspended</title>    <link rel="stylesheet" href="style.css">
</head><body><h2>⛔ Suspended</h2><p>Until $end. Contact admin.</p><a href='logout.php'>Logout</a></body></html>");
    else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
        $conn2->close();
    }
}
if ($user['status'] == 'dismissed') die("<!DOCTYPE html><html><head><title>Dismissed</title>    <link rel="stylesheet" href="style.css">
</head><body><h2>❌ Dismissed</h2><p>Access denied.</p><a href='logout.php'>Logout</a></body></html>");
?>