<?php
// check_remember_me.php – Handles automatic login via "Remember Me" cookie

require_once 'config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user already logged in via session, do nothing
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_logged'])) {
    return;
}

// Check if remember me cookie exists
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    $conn = getDB();
    if (!$conn) {
        return; // Silently fail if DB connection fails
    }
    
    // Look up the token
    $stmt = $conn->prepare("
        SELECT t.user_id, u.fullname, u.approved, u.consent_signed, u.status, u.suspension_end
        FROM remember_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ? AND t.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        // User found – log them in
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = 'student';
        $_SESSION['fullname'] = $user['fullname']; // ✅ fixes first name display
        $_SESSION['approved'] = $user['approved'];
        $_SESSION['consent_signed'] = $user['consent_signed'];
        $_SESSION['status'] = $user['status'];
        $_SESSION['suspension_end'] = $user['suspension_end'];
        unset($_SESSION['admin_logged']);
        
        // Extend the cookie expiry (this keeps the user logged in for another 30 days)
        $new_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $conn->query("DELETE FROM remember_tokens WHERE user_id = {$user['user_id']}");
        $stmt2 = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $user['user_id'], $new_token, $expires);
        $stmt2->execute();
        
        // Set the cookie with a 30-day expiry, secure and httpOnly
        setcookie('remember_me', $new_token, time() + 86400 * 30, '/', '', false, true);
    } else {
        // Token invalid or expired – delete the cookie
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
    }
}
?>