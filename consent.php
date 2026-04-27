<?php
require_once 'config.php'; session_start(); if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit; }
$conn=getDB(); $uid=$_SESSION['user_id'];
$u=$conn->query("SELECT consent_signed FROM users WHERE id=$uid")->fetch_assoc();
if($u['consent_signed']) die("Already agreed. <a href='dashboard.php'>Dashboard</a>");
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['agree'])){
    $conn->query("UPDATE users SET consent_signed=1, consent_signed_at=NOW() WHERE id=$uid");
    echo "<script>alert('Thank you. You now have access.'); window.location='dashboard.php';</script>"; exit;
} ?>
<!DOCTYPE html><html><head><title>Consent Form</title><style>body{font-family:Arial;padding:20px;}</style>    <link rel="stylesheet" href="style.css">
</head><body><div style="max-width:800px;margin:auto;background:white;padding:20px;"><h1>📜 Group Rules</h1><p><strong>No money, no sexual favors.</strong> You must be punctual, hardworking, and respectful. Read the full rules below.</p><ul><li>Work hard, read extensively.</li><li>Be punctual for all sessions.</li><li>Respect teacher and peers.</li><li>Don't focus only on past papers.</li><li>No financial/sexual exchange – dismissal.</li></ul><h2>Punishments</h2><ul><li>Warning</li><li>Extra assignment</li><li>Suspension (content locked)</li><li>Dismissal (permanent)</li></ul><form method="post"><label><input type="checkbox" name="agree" required> I agree to abide by all rules.</label><br><button type="submit">Accept & Continue</button></form></div></body></html>