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
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$classes = ['Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $desc = $_POST['description'];
    $dur = (int)$_POST['duration_minutes'];
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;

    $conn->query("INSERT INTO exams (title, subject, class_level, description, duration_minutes) VALUES ('$title','$subject','$class','$desc',$dur)");
    $exam_id = $conn->insert_id;

    if ($group_id) {
        $all_groups = $conn->query("SELECT id FROM groups WHERE class_level = '$class'");
        while ($g = $all_groups->fetch_assoc()) {
            $lock = $g['id'] == $group_id ? 0 : 1;
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$g['id']}, 'exam', $exam_id, $lock)
                          ON DUPLICATE KEY UPDATE is_locked = $lock");
        }
        $msg = "Exam created and unlocked for the selected group.";
    } else {
        $msg = "Exam created. Use the lock manager to control group access.";
    }
    echo "<script>alert('$msg'); window.location='admin_add_questions.php?exam_id=$exam_id';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Create Exam</title><link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>📝 Create New Exam</h2>
            <form method="post">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Subject</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Class</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls ?>"><?= $cls ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editor"></textarea></div>
                <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="60"></div>

                <div class="group-selector" style="margin-top: 1rem;">
                    <h4>🎯 Assign to specific group (optional)</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select id="classFilter" style="min-width: 120px;">
                            <option value="">-- Class --</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?= $cls ?>"><?= $cls ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="routeFilter" style="min-width: 120px;">
                            <option value="">-- Route --</option>
                            <option value="sciences">Sciences</option>
                            <option value="humanities">Humanities</option>
                        </select>
                        <select name="group_id" id="groupSelect" style="min-width: 150px;">
                            <option value="">-- Any group (use locks later) --</option>
                        </select>
                    </div>
                    <small class="help-text">If you select a group, this exam will be instantly unlocked for that group and locked for others.</small>
                </div>

                <button type="submit" class="btn">Create & Add Questions</button>
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

        
        function loadGroups() {
            const classVal = document.getElementById('classFilter').value;
            const routeVal = document.getElementById('routeFilter').value;
            const groupSelect = document.getElementById('groupSelect');
            if (!classVal || !routeVal) {
                groupSelect.innerHTML = '<option value="">-- Select class and route first --</option>';
                return;
            }
            fetch(`admin_get_groups.php?class=${encodeURIComponent(classVal)}&route=${encodeURIComponent(routeVal)}`)
                .then(res => res.json())
                .then(data => {
                    groupSelect.innerHTML = '<option value="">-- Any group (use locks later) --</option>';
                    data.forEach(group => {
                        groupSelect.innerHTML += `<option value="${group.id}">Group ${group.group_number} (${group.current_members}/5 members)</option>`;
                    });
                })
                .catch(err => console.error(err));
        }
        document.getElementById('classFilter').addEventListener('change', loadGroups);
        document.getElementById('routeFilter').addEventListener('change', loadGroups);
    </script>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>