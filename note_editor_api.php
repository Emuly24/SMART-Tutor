<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'upload_image') {
    // CKEditor image upload
    $uploadDir = 'uploads/note_images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
        $file = $_FILES['upload'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['error' => 'Invalid image type']);
            exit;
        }
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destination = $uploadDir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo json_encode(['url' => $destination]);
        } else {
            echo json_encode(['error' => 'Upload failed']);
        }
    } else {
        echo json_encode(['error' => 'No file']);
    }
} elseif ($action === 'upload_attachment') {
    // Generic file attachment
    $uploadDir = 'uploads/note_attachments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','txt','jpg','jpeg','png','zip','mp4','ppt','pptx'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['error' => 'Invalid file type']);
            exit;
        }
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destination = $uploadDir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo json_encode(['url' => $destination]);
        } else {
            echo json_encode(['error' => 'Upload failed']);
        }
    } else {
        echo json_encode(['error' => 'No file']);
    }
} elseif ($action === 'save_research_note') {
    $data = json_decode(file_get_contents('php://input'), true);
    $note = $conn->real_escape_string($data['note'] ?? '');
    if ($note) {
        $conn->query("INSERT INTO admin_research_notes (note_text) VALUES ('$note')");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} elseif ($action === 'load_research_notes') {
    $notes = $conn->query("SELECT id, note_text, created_at FROM admin_research_notes ORDER BY created_at DESC");
    $result = [];
    while ($row = $notes->fetch_assoc()) {
        $result[] = $row;
    }
    echo json_encode($result);
} elseif ($action === 'delete_research_note') {
    $id = (int)$_GET['id'] ?? 0;
    if ($id) $conn->query("DELETE FROM admin_research_notes WHERE id=$id");
    echo json_encode(['success' => true]);
} elseif ($action === 'auto_save_draft') {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = $conn->real_escape_string($data['title'] ?? '');
    $subject = $conn->real_escape_string($data['subject'] ?? '');
    $class = $conn->real_escape_string($data['class_level'] ?? '');
    $content = $conn->real_escape_string($data['content'] ?? '');
    $conn->query("DELETE FROM note_drafts");
    $conn->query("INSERT INTO note_drafts (title, subject, class_level, content) VALUES ('$title', '$subject', '$class', '$content')");
    echo json_encode(['success' => true]);
} elseif ($action === 'load_draft') {
    $draft = $conn->query("SELECT * FROM note_drafts LIMIT 1")->fetch_assoc();
    echo json_encode($draft ?: []);
    } elseif ($action === 'upload_media') {
    $uploadDir = 'uploads/media/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['mp3','wav','ogg','mp4','webm','mov'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['error' => 'Invalid media type']);
            exit;
        }
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $dest = $uploadDir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['url' => $dest]);
        } else {
            echo json_encode(['error' => 'Upload failed']);
        }
    } else {
        echo json_encode(['error' => 'No file']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>