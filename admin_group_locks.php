<?php
require_once 'config.php';
session_start();

$admin_hash = getAdminHash();
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

// Toggle lock status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    $group_id = (int)$_GET['group_id'];
    $current = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id=$group_id AND content_type='$type' AND content_id=$id")->fetch_assoc();
    if ($current) {
        $new_lock = $current['is_locked'] ? 0 : 1;
        $conn->query("UPDATE group_content_locks SET is_locked=$new_lock WHERE group_id=$group_id AND content_type='$type' AND content_id=$id");
    } else {
        $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES ($group_id, '$type', $id, 0)");
    }
    header("Location: admin_group_locks.php");
    exit;
}

$groups = $conn->query("SELECT * FROM groups ORDER BY class_level, group_number");
$notes = $conn->query("SELECT id, title, subject, class_level FROM notes ORDER BY class_level, title");
$exams = $conn->query("SELECT id, title, subject, class_level FROM exams ORDER BY class_level, title");
$assignments = $conn->query("SELECT id, title, subject, class_level FROM assignments ORDER BY class_level, title");
$quizzes = $conn->query("SELECT id, title, note_id FROM quizzes ORDER BY id");
?>
<!DOCTYPE html>
<html><head><title>Group Content Locks</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php while($g = $groups->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?></h3>
                    
                    <?php foreach (['note' => $notes, 'exam' => $exams, 'assignment' => $assignments, 'quiz' => $quizzes] as $type => $data): ?>
                        <h4><?= ucfirst($type) ?>s</h4>
                        <ul>
                        <?php
                        $data->data_seek(0);
                        while($item = $data->fetch_assoc()):
                            // For quizzes, get note class to filter
                            if ($type == 'quiz') {
                                $note_class = $conn->query("SELECT class_level FROM notes WHERE id={$item['note_id']}")->fetch_assoc()['class_level'];
                                if ($note_class != $g['class_level']) continue;
                            } elseif ($type != 'quiz' && $item['class_level'] != $g['class_level']) {
                                continue;
                            }
                            $locked = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id={$g['id']} AND content_type='$type' AND content_id={$item['id']}")->fetch_assoc();
                            $is_locked = $locked ? $locked['is_locked'] : 1;
                            $status = $is_locked ? '🔒 Locked' : '🔓 Unlocked';
                            $toggle_link = "admin_group_locks.php?toggle=1&type=$type&id={$item['id']}&group_id={$g['id']}";
                            echo "<li><strong>{$item['title']}</strong> – $status <a href='$toggle_link'>Toggle</a></li>";
                        endwhile;
                        ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>