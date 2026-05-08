<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$uid = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
$book_id = (int)$input['book_id'];
$book_title = $input['book_title'];
$page = (int)$input['page'];
$selected_text = $input['selected_text'];
$question = $input['question'];

if (empty($book_id) || empty($question)) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}
$conn = getDB();
$stmt = $conn->prepare("INSERT INTO book_questions (user_id, book_id, book_title, page_number, selected_text, question) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisiss", $uid, $book_id, $book_title, $page, $selected_text, $question);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $conn->error]);
}
?>