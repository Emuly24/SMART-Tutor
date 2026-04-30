<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    http_response_code(403);
    exit;
}
$conn = getDB();
$type = $_GET['type'] ?? '';
if ($type === 'equations') {
    $result = $conn->query("SELECT id, title, latex, category FROM equations_library ORDER BY category, title");
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
} elseif ($type === 'diagrams') {
    $result = $conn->query("SELECT id, title, file_path, category FROM diagrams_library ORDER BY category, title");
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
} else {
    echo json_encode([]);
}
?>