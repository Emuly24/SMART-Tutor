<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$action = $_GET['action'] ?? '';
$note_id = (int)($_GET['note_id'] ?? 0);

if ($action === 'get_locks' && $note_id) {
    // Get note details to know class and route
    $note = $conn->query("SELECT class_level FROM notes WHERE id = $note_id")->fetch_assoc();
    if (!$note) die(json_encode(['error' => 'Note not found']));
    $class = $note['class_level'];
    // Get all groups for that class
    $groups = $conn->query("SELECT id, class_level, group_number, route FROM groups WHERE class_level = '$class' ORDER BY route, group_number");
    $locks = [];
    while ($g = $groups->fetch_assoc()) {
        $lock = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id = {$g['id']} AND content_type = 'note' AND content_id = $note_id")->fetch_assoc();
        $is_locked = $lock ? $lock['is_locked'] : 1; // default locked
        $locks[] = [
            'group_id' => $g['id'],
            'group_number' => $g['group_number'],
            'route' => $g['route'],
            'is_locked' => $is_locked
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'locks' => $locks]);
    exit;
}

if ($action === 'toggle_lock' && $note_id) {
    $group_id = (int)$_GET['group_id'];
    $current = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id = $group_id AND content_type = 'note' AND content_id = $note_id")->fetch_assoc();
    if ($current) {
        $new_lock = $current['is_locked'] ? 0 : 1;
        $conn->query("UPDATE group_content_locks SET is_locked = $new_lock WHERE group_id = $group_id AND content_type = 'note' AND content_id = $note_id");
    } else {
        // Default is locked, so toggling means unlock (insert as unlocked)
        $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES ($group_id, 'note', $note_id, 0)");
        $new_lock = 0;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'is_locked' => $new_lock]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>