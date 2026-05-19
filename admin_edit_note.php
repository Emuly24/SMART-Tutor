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
$note = $conn->query("SELECT * FROM notes WHERE id=$id")->fetch_assoc();
if (!$note) die("Note not found");
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$classes = ['Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $conn->query("UPDATE notes SET title='$title', subject='$subject', class_level='$class', content='$content' WHERE id=$id");
    echo "<script>alert('Note updated'); window.location='admin_notes_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Note</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
<style>
    .ck-editor__editable { min-height: 600px; width: 100% !important; }
    .ck-editor { width: 100% !important; }
    .ck-editor__editable p { text-align: justify; }
    /* Sticky toolbar wrapper */
    .sticky-toolbar-wrapper {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        padding: 10px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
</style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div style="padding: 2rem;">
            <h2>✏️ Edit Note</h2>
            <form method="post">
                <div class="form-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($note['title']) ?>" required></div>
                <div class="form-group"><label>Subject</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>" <?= ($note['subject'] == $sub) ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Class</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls ?>" <?= ($note['class_level'] == $cls) ? 'selected' : '' ?>><?= $cls ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Content</label>
                    <textarea name="content" id="editor"><?= htmlspecialchars($note['content']) ?></textarea>
                </div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
    <script>
        tinymce.init({
            selector: '#editor',
            height: 600,
            menubar: true,
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
            toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | charmap | code',
            toolbar_sticky: true,
            content_style: 'body { font-family: Inter, sans-serif; }',
            valid_elements: '*[*]',
            extended_valid_elements: 'script[type|src|async],style[type]',
            images_upload_url: 'note_editor_api.php?action=upload_image',
            automatic_uploads: true,
            image_advtab: true,
            image_dimensions: true,
            image_caption: true,
            init_instance_callback: function(editor) {
                // Load existing content properly
                const existingContent = <?= json_encode($note['content']) ?>;
                editor.setContent(existingContent);
            }
        });
    </script>
</body></html>