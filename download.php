<?php
// download.php - Secure file delivery for students (and optionally admin)
require_once 'config.php';
require_once 'check_access.php'; // student access control

$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($type) || empty($file)) {
    die("Invalid request.");
}

$allowed_dirs = [
    'book'       => 'uploads/books/',
    'exam'       => 'uploads/exam_answers/',
    'assignment' => 'uploads/assignments/',
    'exercise'   => 'uploads/exercises/'   // <-- added for exercises
];

if (!array_key_exists($type, $allowed_dirs)) {
    die("Invalid file type.");
}

$file_path = $allowed_dirs[$type] . $file;
$real_base = realpath($allowed_dirs[$type]);
$real_file = realpath($file_path);

if ($real_file === false || strpos($real_file, $real_base) !== 0) {
    die("Invalid file path.");
}

if (!file_exists($real_file)) {
    die("File not found.");
}

// All checks passed – serve the file
$mime = mime_content_type($real_file);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($real_file) . '"');
header('Content-Length: ' . filesize($real_file));
readfile($real_file);
exit;
?>