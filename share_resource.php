<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type']; // FIXED: select name is "type"
    $external_url = trim($_POST['external_url']);
    $file_paths = [];
    
    if (empty($subject) || empty($title)) {
        $error = "Subject and title are required.";
    } else {
        // Handle file uploads (multiple)
        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $uploadDir = 'uploads/student_resources/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
            foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['files']['name'][$index], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $error = "Invalid file type: {$_FILES['files']['name'][$index]}";
                    break 2;
                }
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['files']['name'][$index]);
                $dest = $uploadDir . $safeName;
                if (move_uploaded_file($tmpName, $dest)) {
                    $file_paths[] = $dest;
                } else {
                    $error = "Failed to upload {$_FILES['files']['name'][$index]}";
                    break 2;
                }
            }
        }
        // Handle external URL (if provided and no file upload)
        if (empty($file_paths) && !empty($external_url)) {
            $external_url = filter_var($external_url, FILTER_SANITIZE_URL);
            if (!filter_var($external_url, FILTER_VALIDATE_URL)) {
                $error = "Invalid external URL.";
            }
        }
        // If neither files nor URL
        if (empty($file_paths) && empty($external_url)) {
            $error = "Please upload at least one file or provide an external link.";
        }
        
        if (empty($error)) {
            $file_paths_json = !empty($file_paths) ? json_encode($file_paths) : null;
            $stmt = $conn->prepare("INSERT INTO student_resources (user_id, subject, title, description, file_paths, external_url, resource_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $uid, $subject, $title, $description, $file_paths_json, $external_url, $type);
            if ($stmt->execute()) {
                if (function_exists('log_activity')) {
                    log_activity($uid, "share_resource", "Title: $title, Type: $type");
                }
                $success = "Resource submitted for review. Thank you!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Share a Resource – SMART Circle</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div class="card">
        <h2>📚 Share a Learning Resource</h2>
        <p>Do you have a useful book, past paper, or study note that you think could benefit other students? Share it here. The admin will review it and, if suitable, add it to the <strong>library</strong> for all students to read online.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Subject *</label>
                <select name="subject" required>
                    <option value="">-- Select subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub ?>"><?= $sub ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required placeholder="e.g., Physics Form 4 Textbook">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" rows="3" placeholder="Briefly describe the resource..."></textarea>
            </div>
            <div class="form-group">
                <label>Resource Type</label>
                <select name="type">
                    <option value="book" selected>📖 Book (will become a library book)</option>
                    <option value="past_paper">📝 Past Paper</option>
                    <option value="notes">📑 Notes</option>
                    <option value="other">🔗 Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Upload files (multiple)</label>
                <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                <small>Allowed: PDF, Word, PPT, images, text files</small>
            </div>
            <div class="form-group">
                <label>OR external link (URL)</label>
                <input type="url" name="external_url" placeholder="https://...">
            </div>
            <button type="submit" class="btn">Submit Resource</button>
        </form>
    </div>
    <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>