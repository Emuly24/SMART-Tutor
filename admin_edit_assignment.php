<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$id = (int)$_GET['id'];
$assign = $conn->query("SELECT * FROM assignments WHERE id=$id")->fetch_assoc();
if (!$assign) die("Assignment not found");
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$classes = ['Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $subj = $_POST['subject'];
    $class = $_POST['class_level'];
    $due = $_POST['due_date'];
    $attach = $assign['attachment_file_path'];
    if (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == 1) {
        if ($attach && file_exists($attach)) unlink($attach);
        $attach = null;
    }
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','png','pdf','doc','txt'])) {
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['attachment']['name']);
            $dest = $dir . $name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                if ($attach && file_exists($attach)) unlink($attach);
                $attach = $dest;
            }
        }
    }
    $conn->query("UPDATE assignments SET title='$title', description='$desc', attachment_file_path='$attach', subject='$subj', class_level='$class', due_date='$due' WHERE id=$id");
    echo "<script>alert('Assignment updated'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Assignment</title><link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>✏️ Edit Assignment</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($assign['title']) ?>" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editor"><?= htmlspecialchars($assign['description']) ?></textarea></div>
                
                <?php if($assign['attachment_file_path']): ?>
                    <p>Current attachment: <a href="admin_download.php?type=assignment&file=<?= urlencode(basename($assign['attachment_file_path'])) ?>" target="_blank">View</a>
                    <label><input type="checkbox" name="remove_attachment" value="1"> Remove</label></p>
                <?php endif; ?>
                <div class="form-group"><label>Replace/Add Attachment</label><input type="file" name="attachment" accept=".jpg,.png,.pdf,.doc,.txt"></div>
                
                <div class="form-group"><label>Subject</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>" <?= ($assign['subject'] == $sub) ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Class</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls ?>" <?= ($assign['class_level'] == $cls) ? 'selected' : '' ?>><?= $cls ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?= $assign['due_date'] ?>" required></div>

                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    <script>
        tinymce.init({
            selector: '#editor',
            height: 300,
            menubar: false,
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
            toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | charmap | code',
            content_style: 'body { font-family: Inter, sans-serif; }'
        });
    </script>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>