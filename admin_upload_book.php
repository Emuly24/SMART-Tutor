<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();

$msg = '';

// Core subjects only (what we assist with)
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $dir = 'uploads/books/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $file = $_FILES['pdf_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext != 'pdf') {
        $msg = "Only PDF files are allowed.";
    } else {
        // Generate title from file name (remove extension)
        $title = pathinfo($file['name'], PATHINFO_FILENAME);
        $title = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $title); // sanitize
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $dest = $dir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $conn->prepare("INSERT INTO books (title, subject, class_level, file_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $subject, $class, $dest);
            if ($stmt->execute()) {
                $msg = "Book uploaded successfully! Title: " . htmlspecialchars($title);
            } else {
                $msg = "Database error: " . $conn->error;
            }
        } else {
            $msg = "Failed to move uploaded file.";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Upload Book</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2>Upload a Book (PDF)</h2>
            <?php if ($msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <option value="Form 3">Form 3</option>
                        <option value="Form 4">Form 4</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>PDF File *</label>
                    <input type="file" name="pdf_file" accept="application/pdf" required>
                    <small class="help-text">The book title will be taken from the file name.</small>
                </div>
                <button type="submit" class="btn">Upload Book</button>
            </form>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>