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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $csv_file = $_FILES['csv_file']['tmp_name'];
    if (!is_uploaded_file($csv_file)) {
        $error = "Please upload a valid CSV file.";
    } else {
        $handle = fopen($csv_file, 'r');
        if (!$handle) {
            $error = "Could not open file.";
        } else {
            $count = 0;
            $success = 0;
            while (($data = fgetcsv($handle)) !== false) {
                if ($type == 'notes') {
                    // Expected columns: title, subject, class_level, content
                    if (count($data) < 4) continue;
                    list($title, $subject, $class, $content) = array_map('trim', $data);
                    if (empty($title) || empty($subject) || empty($class) || empty($content)) continue;
                    $stmt = $conn->prepare("INSERT INTO notes (title, subject, class_level, content) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $title, $subject, $class, $content);
                    if ($stmt->execute()) $success++;
                } elseif ($type == 'books') {
                    // Expected columns: title, subject, class_level, file_path
                    if (count($data) < 4) continue;
                    list($title, $subject, $class, $file_path) = array_map('trim', $data);
                    if (empty($title) || empty($subject) || empty($class) || empty($file_path)) continue;
                    $stmt = $conn->prepare("INSERT INTO books (title, subject, class_level, file_path) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $title, $subject, $class, $file_path);
                    if ($stmt->execute()) $success++;
                }
                $count++;
            }
            fclose($handle);
            $msg = "Processed $count rows, successfully imported $success records.";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Bulk Import</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div class="card">
        <h2>Bulk Import Notes / Books</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Import Type</label>
                <select name="type" required>
                    <option value="notes">Notes</option>
                    <option value="books">Books</option>
                </select>
            </div>
            <div class="form-group">
                <label>CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required>
                <small class="help-text">
                    For Notes: CSV columns: <code>title, subject, class_level, content</code><br>
                    For Books: CSV columns: <code>title, subject, class_level, file_path</code><br>
                    (file_path should be the relative path to the already uploaded PDF)
                </small>
            </div>
            <button type="submit" class="btn">Import</button>
        </form>
    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>