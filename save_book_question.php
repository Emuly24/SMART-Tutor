<?php
require_once 'check_remember_me.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$uid = $_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];
$book_title = trim($_POST['book_title']);
$page_number = (int)$_POST['page_number'];
$selected_text = trim($_POST['selected_text']);
$question = trim($_POST['question']);

if (!$book_id || !$page_number || empty($selected_text) || empty($question)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$conn = getDB();
// Insert into your existing book_questions table
$stmt = $conn->prepare("INSERT INTO book_questions (user_id, book_id, book_title, page_number, selected_text, question, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iisiss", $uid, $book_id, $book_title, $page_number, $selected_text, $question);
$stmt->execute();

echo json_encode(['success' => true, 'id' => $conn->insert_id]);
$conn->close();
?>