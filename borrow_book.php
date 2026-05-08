<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$book_id = (int)$_GET['id'];

// Check if already borrowed
$borrowed = $conn->query("SELECT id FROM borrowed_books WHERE user_id = $uid AND book_id = $book_id AND returned_at IS NULL");
if ($borrowed->num_rows > 0) {
    $_SESSION['error'] = "You have already borrowed this book.";
    header("Location: library.php");
    exit;
}

// Get book details
$book = $conn->query("SELECT title, subject, file_path FROM books WHERE id = $book_id")->fetch_assoc();
if (!$book) die("Book not found.");

$due_date = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("INSERT INTO borrowed_books (user_id, book_id, book_title, subject, file_path, due_date) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissss", $uid, $book_id, $book['title'], $book['subject'], $book['file_path'], $due_date);
if ($stmt->execute()) {
    $_SESSION['success'] = "Book borrowed! You can read it online until $due_date.";
} else {
    $_SESSION['error'] = "Failed to borrow. Please try again.";
}
header("Location: library.php");
exit;
?>