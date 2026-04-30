<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
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
    $new_lock = $current ? !$current['is_locked'] : 0; // if no record, create unlocked? Actually default should be locked. We'll set lock to 1 if toggling.
    // Actually toggle: if exists, flip; if not, insert locked=0? Let's simplify: always insert or update to opposite.
    if ($current) {
        $new_lock = $current['is_locked'] ? 0 : 1;
        $conn->query("UPDATE group_content_locks SET is_locked=$new_lock WHERE group_id=$group_id AND content_type='$type' AND content_id=$id");
    } else {
        // default is locked (1), so toggling means unlock (0)
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
    <h4>Notes</h4>
    <ul>
    <?php
    $notes->data_seek(0);
    while($n = $notes->fetch_assoc()) {
        if ($n['class_level'] != $g['class_level']) continue;
        $locked = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id={$g['id']} AND content_type='note' AND content_id={$n['id']}")->fetch_assoc();
        $is_locked = $locked ? $locked['is_locked'] : 1; // default locked
        $status = $is_locked ? '🔒 Locked' : '🔓 Unlocked';
        $toggle_link = "admin_group_locks.php?toggle=1&type=note&id={$n['id']}&group_id={$g['id']}";
        echo "<li><strong>{$n['title']}</strong> – $status </li>";
    }
    ?>
    </ul>
    <h4>Exams</h4>
    <ul>
    <?php
    $exams->data_seek(0);
    while($e = $exams->fetch_assoc()) {
        if ($e['class_level'] != $g['class_level']) continue;
        $locked = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id={$g['id']} AND content_type='exam' AND content_id={$e['id']}")->fetch_assoc();
        $is_locked = $locked ? $locked['is_locked'] : 1;
        $status = $is_locked ? '🔒 Locked' : '🔓 Unlocked';
        $toggle_link = "admin_group_locks.php?toggle=1&type=exam&id={$e['id']}&group_id={$g['id']}";
        echo "<li><strong>{$e['title']}</strong> – $status </li>";
    }
    ?>
    </ul>
    <h4>Assignments</h4>
    <ul><?php
    $assignments->data_seek(0);
    while($a = $assignments->fetch_assoc()) {
        if ($a['class_level'] != $g['class_level']) continue;
        $locked = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id={$g['id']} AND content_type='assignment' AND content_id={$a['id']}")->fetch_assoc();
        $is_locked = $locked ? $locked['is_locked'] : 1;
        $status = $is_locked ? '🔒 Locked' : '🔓 Unlocked';
        $toggle_link = "admin_group_locks.php?toggle=1&type=assignment&id={$a['id']}&group_id={$g['id']}";
        echo "<li><strong>{$a['title']}</strong> – $status </li>";
    }
    ?></ul>
    <h4>Quizzes</h4>
    <ul><?php
    $quizzes->data_seek(0);
    while($q = $quizzes->fetch_assoc()) {
        // Get note class for this quiz
        $note_class = $conn->query("SELECT class_level FROM notes WHERE id={$q['note_id']}")->fetch_assoc()['class_level'];
        if ($note_class != $g['class_level']) continue;
        $locked = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id={$g['id']} AND content_type='quiz' AND content_id={$q['id']}")->fetch_assoc();
        $is_locked = $locked ? $locked['is_locked'] : 1;
        $status = $is_locked ? '🔒 Locked' : '🔓 Unlocked';
        $toggle_link = "admin_group_locks.php?toggle=1&type=quiz&id={$q['id']}&group_id={$g['id']}";
        echo "<li><strong>{$q['title']}</strong> – $status </li>";
    }
    ?></ul>
    <div class="card-buttons"><a href='$toggle_link'>Toggle</a><a href='$toggle_link'>Toggle</a><a href='$toggle_link'>Toggle</a><a href='$toggle_link'>Toggle</a></div></div>
<?php endwhile; ?>
</div>
</div><div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

</body></html>