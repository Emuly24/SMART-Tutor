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
<!DOCTYPE html><html><head><title>Edit Exam</title><link rel="stylesheet" href="style.css"></head><body>
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
                            <option value="<?= htmlspecialchars($cls) ?>" <?= ($exam['class_level'] == $cls) ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?= htmlspecialchars($exam['description']) ?></textarea></div>
                <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration_minutes" value="<?= $exam['duration_minutes'] ?>"></div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>