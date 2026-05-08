<?php
require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
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
$core_subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];

// Approve resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $id = (int)$_POST['id'];
    $add_to_library = isset($_POST['add_to_library']);
    $is_essential = isset($_POST['is_essential']) ? 1 : 0;
    $subject_library = $_POST['library_subject'] ?? '';
    $class_library = $_POST['library_class'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    $resource = $conn->query("SELECT * FROM student_resources WHERE id = $id")->fetch_assoc();
    if ($resource) {
        $update_sql = "UPDATE student_resources SET status = 'approved', approved_at = NOW(), is_essential = $is_essential";
        if ($admin_notes) {
            $update_sql .= ", admin_notes = '" . $conn->real_escape_string($admin_notes) . "'";
        }
        $conn->query($update_sql . " WHERE id = $id");
        
        // Only add to library if: type is 'book', admin checked the box, and it's not essential
        if ($resource['type'] == 'book' && $add_to_library && !$is_essential) {
            $title = $resource['title'];
            $subject = $subject_library ?: $resource['subject'];
            $class = $class_library ?: 'Form 3/4';
            if ($resource['file_paths']) {
                $files = json_decode($resource['file_paths'], true);
                foreach ($files as $file) {
                    $book_title = $title . ' (' . basename($file) . ')';
                    $conn->query("INSERT INTO books (title, subject, class_level, file_path) VALUES ('$book_title', '$subject', '$class', '$file')");
                }
            } elseif ($resource['external_url']) {
                $conn->query("INSERT INTO books (title, subject, class_level, file_path) VALUES ('$title (link)', '$subject', '$class', '{$resource['external_url']}')");
            }
        }
    }
    header("Location: admin_resources.php");
    exit;
}

// Reject
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $conn->query("UPDATE student_resources SET status = 'rejected' WHERE id = $id");
    header("Location: admin_resources.php");
    exit;
}

$resources = $conn->query("SELECT r.*, u.fullname FROM student_resources r JOIN users u ON r.user_id = u.id ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Student Resources</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <h1>Student Resource Submissions</h1>
    <div class="content-grid">
        <?php while($r = $resources->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($r['title']) ?></h3>
                <p><strong>Original subject:</strong> <?= htmlspecialchars($r['subject']) ?></p>
                <p><strong>Student:</strong> <?= htmlspecialchars($r['fullname']) ?></p>
                <p><strong>Type:</strong> <?= ucfirst($r['type'] ?? 'other') ?></p>
                <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($r['description'])) ?></p>
                <?php if ($r['file_paths']): ?>
                    <p><strong>Files:</strong></p>
                    <ul>
                        <?php foreach (json_decode($r['file_paths'], true) as $file): ?>
                            <li><a href="<?= $file ?>" target="_blank"><?= basename($file) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($r['external_url']): ?>
                    <p><strong>External link:</strong> <a href="<?= htmlspecialchars($r['external_url']) ?>" target="_blank"><?= htmlspecialchars($r['external_url']) ?></a></p>
                <?php endif; ?>
                <?php if ($r['status'] == 'pending'): ?>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <div class="form-group">
                            <label><input type="checkbox" name="is_essential"> 📌 Mark as essential (internal use only, not added to library)</label>
                        </div>
                        <?php if ($r['type'] == 'book'): ?>
                            <div class="form-group">
                                <label><input type="checkbox" name="add_to_library"> ✅ Add to library (students will see this book)</label>
                            </div>
                            <div class="form-group">
                                <label>📚 Library Subject (if adding to library)</label>
                                <select name="library_subject" class="form-control">
                                    <option value="">-- Keep original subject (<?= htmlspecialchars($r['subject']) ?>) --</option>
                                    <?php foreach ($core_subjects as $sub): ?>
                                        <option value="<?= $sub ?>" <?= ($sub == $r['subject']) ? 'selected' : '' ?>><?= $sub ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>📚 Library Class (if adding to library)</label>
                                <select name="library_class" class="form-control">
                                    <option value="">-- Select class --</option>
                                    <option value="Form 3">Form 3</option>
                                    <option value="Form 4">Form 4</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="info" style="background:#f0f0f0; padding:6px; margin-bottom:10px; border-radius:5px;">
                                ℹ️ This resource type (<?= ucfirst($r['type']) ?>) will <strong>NOT</strong> be added to the library. It will be available for admin reference only (e.g., to use when writing notes).
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Admin notes (optional, will be visible to student)</label>
                            <textarea name="admin_notes" rows="2" class="form-control" placeholder="E.g., Thank you for sharing, this will help others."></textarea>
                        </div>
                        <button type="submit" name="approve" class="btn-success">Approve</button>
                        <a href="?reject=<?= $r['id'] ?>" class="btn-danger" onclick="return confirm('Reject this resource?')">Reject</a>
                    </form>
                <?php else: ?>
                    <p>Status: <strong><?= ucfirst($r['status']) ?></strong> <?php if ($r['is_essential']): ?>(Essential)<?php endif; ?></p>
                    <?php if ($r['admin_notes']): ?>
                        <p><strong>Admin notes:</strong> <?= nl2br(htmlspecialchars($r['admin_notes'])) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php if ($resources->num_rows == 0): ?>
            <div class="card"><p>No resource submissions yet.</p></div>
        <?php endif; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>