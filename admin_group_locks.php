<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
$msg = '';
$error = '';

// Get parameters from URL
$content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'note';
$content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;

// If no content ID, try to get it from POST
if ($content_id === 0 && isset($_POST['content_id'])) {
    $content_id = (int)$_POST['content_id'];
}

// Get the note details (if content_type is 'note')
$note = null;
if ($content_type === 'note' && $content_id > 0) {
    $note = $conn->query("SELECT * FROM notes WHERE id = $content_id")->fetch_assoc();
    if (!$note) {
        $error = "Note not found.";
    }
}

// Handle lock submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_locks'])) {
    $lock_type = $_POST['lock_type']; // 'group', 'route', 'class'
    $target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $route = isset($_POST['route']) ? $conn->real_escape_string($_POST['route']) : '';
    $class_level = isset($_POST['class_level']) ? $conn->real_escape_string($_POST['class_level']) : '';
    $content_type = isset($_POST['content_type']) ? $conn->real_escape_string($_POST['content_type']) : 'note';
    $content_id = (int)$_POST['content_id'];
    
    if ($content_id <= 0) {
        $error = "Invalid content ID.";
    } else {
        // Get all groups of the appropriate class
        $class_filter = '';
        if ($lock_type === 'group' || $lock_type === 'route') {
            if ($lock_type === 'group' && $target_id > 0) {
                $group_info = $conn->query("SELECT class_level FROM groups WHERE id = $target_id")->fetch_assoc();
                if ($group_info) {
                    $class_filter = "class_level = '{$group_info['class_level']}'";
                }
            } elseif ($lock_type === 'route' && !empty($route)) {
                if ($note) {
                    $class_filter = "class_level = '{$note['class_level']}'";
                } else {
                    $error = "Cannot determine class level for this route.";
                }
            }
        } elseif ($lock_type === 'class' && !empty($class_level)) {
            $class_filter = "class_level = '$class_level'";
        }
        
        if (empty($class_filter)) {
            $error = "Could not determine which groups to lock/unlock.";
        } else {
            // First, lock for ALL groups of the target class
            $all_groups = $conn->query("SELECT id FROM groups WHERE $class_filter");
            while ($g = $all_groups->fetch_assoc()) {
                $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                              VALUES ({$g['id']}, '$content_type', $content_id, 1)
                              ON DUPLICATE KEY UPDATE is_locked = 1");
            }
            
            // Then unlock based on the selected option
            if ($lock_type === 'group' && $target_id > 0) {
                $conn->query("UPDATE group_content_locks SET is_locked = 0 WHERE group_id = $target_id AND content_type = '$content_type' AND content_id = $content_id");
                $msg = "Note unlocked for the selected group.";
            } elseif ($lock_type === 'route' && !empty($route)) {
                $sub_query = "SELECT id FROM groups WHERE route = '$route'";
                if (!empty($class_filter)) {
                    $sub_query .= " AND " . $class_filter;
                }
                $conn->query("UPDATE group_content_locks SET is_locked = 0 WHERE group_id IN ($sub_query) AND content_type = '$content_type' AND content_id = $content_id");
                $msg = "Note unlocked for the entire $route route.";
            } elseif ($lock_type === 'class' && !empty($class_level)) {
                $conn->query("UPDATE group_content_locks SET is_locked = 0 WHERE group_id IN (SELECT id FROM groups WHERE class_level = '$class_level') AND content_type = '$content_type' AND content_id = $content_id");
                $msg = "Note unlocked for the entire $class_level class.";
            }
            
            // Optional: Send notification to all unlocked groups
            if (empty($error)) {
                $unlocked_groups = $conn->query("
                    SELECT g.id FROM groups g
                    JOIN group_content_locks l ON g.id = l.group_id
                    WHERE l.content_type = '$content_type' AND l.content_id = $content_id AND l.is_locked = 0
                ");
                $notification_sent = 0;
                while ($ug = $unlocked_groups->fetch_assoc()) {
                    $members = $conn->query("SELECT user_id FROM group_members WHERE group_id = {$ug['id']}");
                    
                    $stmt = $conn->prepare("INSERT INTO admin_messages (user_id, message) VALUES (?, ?)");
                    while ($m = $members->fetch_assoc()) {
                        $msg_text = "📘 A new note has been unlocked for your group. Check it out!";
                        if ($note) {
                            $msg_text = "📘 A new note '{$note['title']}' has been unlocked for your group. Check it out!";
                        }
                        $stmt->bind_param("is", $m['user_id'], $msg_text);
                        $stmt->execute();
                        $notification_sent++;
                    }
                    $stmt->close();
                }
                if ($notification_sent > 0) {
                    $msg .= " Notifications sent to $notification_sent student(s).";
                }
            }
        }
    }
}

// Fetch lock status for all groups (for display)
$groups = [];
$selected_class = isset($_GET['class_level']) ? $_GET['class_level'] : '';
$selected_route = isset($_GET['route']) ? $_GET['route'] : '';
if ($content_id > 0 && !empty($content_type)) {
    $query = "SELECT g.id, g.group_number, g.class_level, g.route, 
              COALESCE(l.is_locked, 0) as is_locked
              FROM groups g
              LEFT JOIN group_content_locks l ON g.id = l.group_id AND l.content_type = '$content_type' AND l.content_id = $content_id";
    if (!empty($selected_class) && !empty($selected_route)) {
        $query .= " WHERE g.class_level = '$selected_class' AND g.route = '$selected_route'";
    } elseif (!empty($selected_class)) {
        $query .= " WHERE g.class_level = '$selected_class'";
    } elseif (!empty($selected_route)) {
        $query .= " WHERE g.route = '$selected_route'";
    }
    $query .= " ORDER BY g.class_level, g.route, g.group_number";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
    }
}

// Fetch all classes and routes for filters
$class_levels = ['Form 3', 'Form 4'];
$routes = ['sciences', 'humanities'];

// Handle AJAX toggle lock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock') {
    header('Content-Type: application/json');
    $group_id = (int)$_POST['group_id'];
    $content_type = $conn->real_escape_string($_POST['content_type']);
    $content_id = (int)$_POST['content_id'];

    $current = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id = $group_id AND content_type = '$content_type' AND content_id = $content_id");
    if ($current->num_rows > 0) {
        $row = $current->fetch_assoc();
        $new_lock = $row['is_locked'] ? 0 : 1;
        $conn->query("UPDATE group_content_locks SET is_locked = $new_lock WHERE group_id = $group_id AND content_type = '$content_type' AND content_id = $content_id");
    } else {
        $new_lock = 1;
        $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES ($group_id, '$content_type', $content_id, $new_lock)");
    }
    echo json_encode(['success' => true, 'is_locked' => $new_lock]);
    exit;
}
?>
<!DOCTYPE html>
<html><head>
    <title>Group Content Locks</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .group-selector { margin-bottom: 1rem; padding: 1rem; background: var(--card-alt-bg); border-radius: 0.75rem; }
        .group-selector select { margin-right: 10px; margin-bottom: 5px; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #ccc; }
        .lock-manager { margin-top: 2rem; padding: 1rem; background: var(--card-alt-bg); border-radius: 0.75rem; display: none; }
        .lock-manager table { width: 100%; }
        .lock-manager td, .lock-manager th { padding: 8px; }
        .lock-toggle { cursor: pointer; background: var(--accent); color: #1e293b; border: none; padding: 4px 12px; border-radius: 20px; font-weight: 600; }
        .lock-toggle.locked { background: var(--error); color: white; }
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filters select, .filters input { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #ccc; }
        .filters button { background: var(--accent); color: #1e293b; border: none; padding: 0.5rem 1.5rem; border-radius: 0.5rem; cursor: pointer; }
        .filters button:hover { background: var(--accent-dark); }
        .lock-options { display: flex; gap: 10px; flex-wrap: wrap; margin: 1rem 0; }
        .lock-options select, .lock-options input { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #ccc; }
        .help-text { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
        .content-info { background: var(--card-bg); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem; border-left: 4px solid var(--accent); }
    </style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">

    <h1>🔒 Group Content Lock Manager</h1>
    <p>Manage which groups can view a specific note, book, or other content.</p>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($note): ?>
        <div class="content-info">
            <h3>📄 Note: <?= htmlspecialchars($note['title']) ?></h3>
            <p><strong>Subject:</strong> <?= htmlspecialchars($note['subject']) ?> | <strong>Class:</strong> <?= htmlspecialchars($note['class_level']) ?></p>
        </div>
    <?php endif; ?>

    <!-- PUBLISH FORM -->
    <div class="group-selector">
        <h4>🎯 Publish to a specific group, route, or class</h4>
        <form method="post" id="lockForm">
            <input type="hidden" name="content_type" value="<?= htmlspecialchars($content_type) ?>">
            <input type="hidden" name="content_id" value="<?= $content_id ?>">

            <div class="lock-options">
                <label>Unlock for:</label>
                <select id="lockType" name="lock_type" onchange="toggleLockOptions()">
                    <option value="group">A specific group</option>
                    <option value="route">A specific route (Sciences/Humanities)</option>
                    <option value="class">A specific class (Form 3/Form 4)</option>
                </select>
            </div>

            <div id="groupOption" style="display: none;">
                <label>Select Group</label>
                <select name="target_id" id="groupSelect">
                    <option value="">-- Select --</option>
                    <?php
                    $all_groups = $conn->query("SELECT id, class_level, group_number, route FROM groups ORDER BY class_level, route, group_number");
                    while ($g = $all_groups->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>"><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?> (<?= ucfirst($g['route']) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="routeOption" style="display: none;">
                <label>Select Route</label>
                <select name="route">
                    <option value="sciences">Sciences</option>
                    <option value="humanities">Humanities</option>
                </select>
            </div>

            <div id="classOption" style="display: none;">
                <label>Select Class</label>
                <select name="class_level">
                    <option value="Form 3">Form 3</option>
                    <option value="Form 4">Form 4</option>
                </select>
            </div>

            <button type="submit" name="apply_locks" class="btn" style="margin-top: 10px;">✅ Publish & Send</button>
        </form>
    </div>

    <!-- FILTER -->
    <div class="filters">
        <label>Class:
            <select id="classFilter">
                <option value="">All</option>
                <?php foreach ($class_levels as $cls): ?>
                    <option value="<?= $cls ?>" <?= ($selected_class == $cls) ? 'selected' : '' ?>><?= $cls ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Route:
            <select id="routeFilter">
                <option value="">All</option>
                <?php foreach ($routes as $rte): ?>
                    <option value="<?= $rte ?>" <?= ($selected_route == $rte) ? 'selected' : '' ?>><?= ucfirst($rte) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button id="loadLocksBtn" class="btn-secondary">🔍 Filter Locks</button>
    </div>

    <!-- LOCK TABLE -->
    <div class="lock-manager" style="display: <?= !empty($groups) ? 'block' : 'none' ?>;">
        <h3>🔒 Current Lock Status</h3>
        <p>Click "Lock" or "Unlock" to change access for a specific group.</p>
        <table class="data-table">
            <thead>
                <tr><th>Group</th><th>Route</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($groups)): ?>
                    <?php foreach ($groups as $g): ?>
                        <tr>
                            <td><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?></td>
                            <td><?= ucfirst($g['route']) ?></td>
                            <td id="status-<?= $g['id'] ?>"><?= $g['is_locked'] ? '🔒 Locked' : '🔓 Unlocked' ?></td>
                            <td>
                                <button class="lock-toggle <?= $g['is_locked'] ? 'locked' : '' ?>"
                                        data-group="<?= $g['id'] ?>"
                                        data-content-type="<?= $content_type ?>"
                                        data-content-id="<?= $content_id ?>">
                                    <?= $g['is_locked'] ? 'Unlock' : 'Lock' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No groups found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<script>
    function toggleLockOptions() {
        const type = document.getElementById('lockType').value;
        document.getElementById('groupOption').style.display = type === 'group' ? 'block' : 'none';
        document.getElementById('routeOption').style.display = type === 'route' ? 'block' : 'none';
        document.getElementById('classOption').style.display = type === 'class' ? 'block' : 'none';
    }
    toggleLockOptions();

    document.getElementById('loadLocksBtn').addEventListener('click', function() {
        const classFilter = document.getElementById('classFilter').value;
        const routeFilter = document.getElementById('routeFilter').value;
        const contentType = '<?= $content_type ?>';
        const contentId = <?= $content_id ?>;
        let url = `admin_group_locks.php?content_type=${contentType}&content_id=${contentId}`;
        if (classFilter) url += `&class_level=${classFilter}`;
        if (routeFilter) url += `&route=${routeFilter}`;
        window.location.href = url;
    });

    document.querySelectorAll('.lock-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const groupId = this.getAttribute('data-group');
            const contentType = this.getAttribute('data-content-type');
            const contentId = this.getAttribute('data-content-id');

            fetch('admin_group_locks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_lock&group_id=${groupId}&content_type=${contentType}&content_id=${contentId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const statusSpan = document.getElementById(`status-${groupId}`);
                    statusSpan.innerHTML = data.is_locked ? '🔒 Locked' : '🔓 Unlocked';
                    this.innerHTML = data.is_locked ? 'Unlock' : 'Lock';
                    this.classList.toggle('locked', data.is_locked);
                } else {
                    alert('Error toggling lock');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error toggling lock');
            });
        });
    });
</script>

<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>