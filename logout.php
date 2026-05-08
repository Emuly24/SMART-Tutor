<?php
require_once 'check_remember_me.php';

session_start();

if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $conn = getDB();
    $conn->query("DELETE FROM remember_tokens WHERE token = '$token'");
    setcookie('remember_me', '', time() - 3600, '/');
}

session_destroy();
header("Location: index.php");
exit; 