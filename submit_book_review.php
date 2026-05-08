<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$uid = $_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];
$rating = (int)$_POST['rating'];
$review = trim($_POST['review']);

if ($rating < 1 || $rating > 5) die(json_encode(['success'=>false,'message'=>'Invalid rating']));
$conn = getDB();
$stmt = $conn->prepare("INSERT INTO book_reviews (user_id, book_id, rating, review) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating=?, review=?");
$stmt->bind_param("iiissi", $uid, $book_id, $rating, $review, $rating, $review);
if ($stmt->execute()) echo json_encode(['success'=>true,'message'=>'Thank you for your review!']);
else echo json_encode(['success'=>false,'message'=>'Database error']);
?>