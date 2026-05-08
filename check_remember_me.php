<?php
require_once 'check_remember_me.php';

// This file should be included at the beginning of all protected pages (before session_start is needed, but after session_start)
if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in, do nothing
if (isset($_SESSION['user_id'])) return;

// Check for remember me cookie
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $conn = getDB();
    $stmt = $conn->prepare("SELECT user_id, expires_at FROM remember_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['expires_at']) > time()) {
            // Token is valid – log the user in
            $user_id = $row['user_id'];
            $user = $conn->query("SELECT id, fullname, approved, consent_signed, status, suspension_end FROM users WHERE id = $user_id")->fetch_assoc();
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'student';
                unset($_SESSION['admin_logged']);
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['consent_signed'] = $user['consent_signed'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['suspension_end'] = $user['suspension_end'];
                // Refresh the cookie expiry
                $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $conn->query("UPDATE remember_tokens SET expires_at = '$new_expires' WHERE token = '$token'");
                setcookie('remember_me', $token, time() + 86400 * 30, '/', '', false, true);
            }
        } else {
            // Token expired – delete it
            $conn->query("DELETE FROM remember_tokens WHERE token = '$token'");
            setcookie('remember_me', '', time() - 3600, '/');
        }
    }
}
?>