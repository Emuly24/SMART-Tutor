<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$book_id = (int)$_GET['id'];

$conn->query("UPDATE borrowed_books SET returned_at = NOW() WHERE user_id = $uid AND book_id = $book_id AND returned_at IS NULL");
$_SESSION['success'] = "Book returned.";
header("Location: library.php");
exit;
?>