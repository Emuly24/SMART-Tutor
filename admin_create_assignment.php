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
    $desc = $_POST['description'];
    $subj = $_POST['subject'];
    $class = $_POST['class_level'];
    $due = $_POST['due_date'];
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;
    $attach = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','png','pdf','doc','txt'])) {
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['attachment']['name']);
            $dest = $dir . $name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) $attach = $dest;
        }
    }
    $conn->query("INSERT INTO assignments (title, description, attachment_file_path, subject, class_level, due_date) VALUES ('$title', '$desc', '$attach', '$subj', '$class', '$due')");
    $ass_id = $conn->insert_id;
    
    if ($group_id) {
        $all_groups = $conn->query("SELECT id FROM groups WHERE class_level = '$class'");
        while ($g = $all_groups->fetch_assoc()) {
            $lock = $g['id'] == $group_id ? 0 : 1;
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$g['id']}, 'assignment', $ass_id, $lock)
                          ON DUPLICATE KEY UPDATE is_locked = $lock");
        }
        $msg = "Assignment created and unlocked for the selected group.";
    } else {
        $msg = "Assignment created. Use the lock manager to control group access.";
    }
    echo "<script>alert('$msg'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Create Assignment</title><link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>📝 Create New Assignment</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editor"></textarea></div>
                <div class="form-group"><label>Attachment (optional)</label><input type="file" name="attachment" accept=".jpg,.png,.pdf,.doc,.txt"></div>
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
                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>

                <!-- Locking Option -->
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
                    <small class="help-text">If you select a group, this assignment will be instantly unlocked for that group and locked for others.</small>
                </div>

                <button type="submit" class="btn">Create Assignment</button>
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