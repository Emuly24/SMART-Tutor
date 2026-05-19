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
$exam = $conn->query("SELECT * FROM exams WHERE id=$id")->fetch_assoc();
if (!$exam) die("Exam not found");
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$classes = ['Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $desc = $_POST['description'];
    $dur = (int)$_POST['duration_minutes'];
    $conn->query("UPDATE exams SET title='$title', subject='$subject', class_level='$class', description='$desc', duration_minutes=$dur WHERE id=$id");
    echo "<script>alert('Exam updated'); window.location='admin_exams_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Edit Exam</title><link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>✏️ Edit Exam</h2>
            <form method="post">
                <div class="form-group"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($exam['title']) ?>" required></div>
                <div class="form-group"><label>Subject</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>" <?= ($exam['subject'] == $sub) ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Class</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls ?>" <?= ($exam['class_level'] == $cls) ? 'selected' : '' ?>><?= $cls ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editor"><?= htmlspecialchars($exam['description']) ?></textarea></div>
                <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="<?= $exam['duration_minutes'] ?>"></div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    <script>
        // ---------- TINYMCE ----------
    tinymce.init({
    selector: '#editor',
    height: 600,
    menubar: true,
    plugins: 'anchor autolink charmap codesample emoticons image imagetools link lists media searchreplace table visualblocks wordcount code',
    toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | charmap | code | editimage',
    toolbar_sticky: true,
    menubar: 'file edit view insert format tools table',
    content_style: 'body { font-family: Inter, sans-serif; }',
    
    // Store clean HTML only
    forced_root_block: false,
    valid_elements: '*[*]',
    extended_valid_elements: 'script[type|src|async],style[type]',
    sanitize: false,
    allow_script_urls: true,
    
    images_upload_url: 'note_editor_api.php?action=upload_image',
    automatic_uploads: true,
    image_advtab: true,
    image_dimensions: true,
    image_caption: true,
    
        init_instance_callback: function(editor) {
        document.getElementById('editor').style.display = 'none';
        },
        setup: function(editor) {
            // ---------- SET CONTENT (WITH LOCALSTORAGE RESTORE) ----------
            const existingContent = <?= json_encode($existing_note['content'] ?? '') ?>;
            
            editor.on('init', function() {
                // 1. Check for a backup FIRST
                const backupJson = localStorage.getItem('my_perfect_7_hour_backup');
                let contentToSet = existingContent;

                if (backupJson) {
                    try {
                        const backupData = JSON.parse(backupJson);
                        // Check if the backup has valid content and is not empty
                        if (backupData.content && backupData.content.length > 100) {
                            const confirmRestore = confirm(
                                "⚠️ Unsaved work found in your browser from " + 
                                new Date(backupData.timestamp).toLocaleString() + 
                                ".\n\nRestore it now?"
                            );
                            if (confirmRestore) {
                                contentToSet = backupData.content;
                                // Also restore the title if it exists
                                if (backupData.title) {
                                    document.getElementById('noteTitle').value = backupData.title;
                                }
                                // Clear the backup so it doesn't ask again on the next refresh
                                localStorage.removeItem('my_perfect_7_hour_backup');
                            } else {
                                // User declined, clear the backup to stop the prompt on future refreshes
                                localStorage.removeItem('my_perfect_7_hour_backup');
                            }
                        }
                    } catch (e) {
                        console.error("Backup corrupted or invalid:", e);
                        localStorage.removeItem('my_perfect_7_hour_backup');
                    }
                }

                // 2. Set the final content
                editor.setContent(contentToSet);
            });

            // ---------- AUTO-SAVE (EVERY 30 SECONDS) ----------
            setInterval(function() {
                autoSaveToServer(editor);
            }, 30000);

            // ---------- CTRL+S ----------
            editor.addShortcut('Ctrl+S', 'Auto Save', function() {
                autoSaveToServer(editor);
            });

            // ---------- FILE MENU ----------
            editor.ui.registry.addMenuItem('customSave', {
                text: 'Save (Auto)',
                icon: 'save',
                onAction: function() {
                    autoSaveToServer(editor);
                }
            });
            editor.ui.registry.addMenuItem('customSaveAs', {
                text: 'Save As...',
                icon: 'newdocument',
                onAction: function() {
                    const form = document.getElementById('noteForm');
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'save_as';
                    hidden.value = '1';
                    form.appendChild(hidden);
                    form.submit();
                }
            });
            editor.on('init', function() {
                editor.menu.add('file', {
                    title: 'File',
                    items: 'newdocument | customSave customSaveAs | print'
                });
            });

            // ---------- MATHJAX ----------
            editor.on('init', function() {
                const content = editor.getContent();
                if (content && window.MathJax) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    MathJax.typesetPromise([tempDiv]).then(() => {
                        editor.setContent(tempDiv.innerHTML);
                    }).catch(() => {});
                }
                if (window.mermaid) mermaid.run({ nodes: document.querySelectorAll('.mermaid') });
                if (window.hljs) hljs.highlightAll();
            });
            editor.on('SetContent', function() {
                if (window.hljs) setTimeout(hljs.highlightAll, 100);
            });
        }
    });
    // ---------- IMAGE CROPPER ----------
    let cropper = null;
    let currentImageElement = null;
    const cropperModal = document.getElementById('imageCropperModal');
    const cropperImg = document.getElementById('cropperImage');
    
    function showImageCropper(src, imgElement) {
        currentImageElement = imgElement;
        cropperImg.src = src;
        cropperModal.style.display = 'flex';
        setTimeout(() => {
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropperImg, {
                aspectRatio: NaN,
                viewMode: 1,
                autoCropArea: 0.8,
            });
        }, 300);
    }
    
    document.getElementById('cropApplyBtn').onclick = function() {
        if (cropper && currentImageElement && tinymce.activeEditor) {
            const canvas = cropper.getCroppedCanvas({
                maxWidth: 800,
                maxHeight: 800,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            canvas.toBlob(function(blob) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const newSrc = e.target.result;
                    tinymce.activeEditor.dom.setAttrib(currentImageElement, 'src', newSrc);
                    cropperModal.style.display = 'none';
                    if (cropper) cropper.destroy();
                    cropper = null;
                    currentImageElement = null;
                };
                reader.readAsDataURL(blob);
            });
        }
    };
    document.getElementById('rotateLeftBtn').onclick = function() {
        if (cropper) cropper.rotate(-90);
    };
    document.getElementById('rotateRightBtn').onclick = function() {
        if (cropper) cropper.rotate(90);
    };
    document.getElementById('cropResetBtn').onclick = function() {
        if (cropper) cropper.reset();
    };
    document.getElementById('cropCancelBtn').onclick = function() {
        cropperModal.style.display = 'none';
        if (cropper) cropper.destroy();
        cropper = null;
        currentImageElement = null;
    };

    </script>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>