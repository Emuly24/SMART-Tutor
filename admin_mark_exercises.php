<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

if (function_exists('getAdminHash')) {
    $admin_hash = getAdminHash();
} elseif (defined('ADMIN_HASH')) {
    $admin_hash = ADMIN_HASH;
} else {
    $admin_hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $attempt_id = (int)$_POST['attempt_id'];
    $marks = (int)$_POST['marks'];
    $feedback = $_POST['feedback'];
    $paper_file = null;
    if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/exercises/';
        $ext = pathinfo($_FILES['paper_file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','pdf'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "paper_{$attempt_id}_".time().".$ext";
            if (move_uploaded_file($_FILES['paper_file']['tmp_name'], $dir.$filename)) {
                $paper_file = $dir.$filename;
            }
        }
    }
    $file_sql = $paper_file ? ", answer_file_path = '$paper_file'" : "";
    $conn->query("UPDATE exercise_attempts SET marks_awarded=$marks, feedback='$feedback', status='marked' $file_sql WHERE id=$attempt_id");
    header("Location: admin_mark_exercises.php");
    exit;
}

$notes = $conn->query("SELECT DISTINCT n.id, n.title FROM notes n JOIN note_exercises e ON n.id=e.note_id ORDER BY n.title");
?>
<!DOCTYPE html><html><head><title>Mark Exercises</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Mark Exercises</h1>
        <div class="content-grid">
            <?php if($notes->num_rows == 0): ?>
                <div class="card"><p>No notes with exercises found. Please create notes with exercises first.</p></div>
            <?php else: ?>
                <?php while($n = $notes->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?=htmlspecialchars($n['title'])?></h3>
                        <a href="?note_id=<?=$n['id']?>" class="btn">View Submissions</a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['note_id'])): 
            $note_id = (int)$_GET['note_id'];
            $students = $conn->query("SELECT DISTINCT u.id, u.fullname FROM exercise_attempts a JOIN users u ON a.user_id=u.id JOIN note_exercises e ON a.exercise_id=e.id WHERE e.note_id=$note_id ORDER BY u.fullname");
        ?>
            <h2>Students with pending or completed exercises</h2>
            <?php if($students->num_rows == 0): ?>
                <div class="card"><p>No students have attempted exercises for this note.</p></div>
            <?php else: ?>
                <?php while($s = $students->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?=htmlspecialchars($s['fullname'])?></h3>
                        <?php
                        $attempts = $conn->query("SELECT a.*, e.question, e.points, e.id as exercise_id, n.title as note_title 
                            FROM exercise_attempts a 
                            JOIN note_exercises e ON a.exercise_id=e.id 
                            JOIN notes n ON e.note_id=n.id 
                            WHERE a.user_id={$s['id']} AND e.note_id=$note_id ORDER BY e.sort_order");
                        while($a = $attempts->fetch_assoc()): ?>
                            <div class="marking-block">
                                <strong>Exercise:</strong> <?=nl2br(htmlspecialchars($a['question']))?><br>
                                <strong>Status:</strong> <?=$a['status']?><br>
                                <?php if ($a['status'] == 'paper_pending'): ?>
                                    <div class="warning">Promise made at: <?=$a['promised_at']?>. Deadline: <?=date('Y-m-d H:i:s', strtotime($a['promised_at'].' +24 hours'))?></div>
                                <?php endif; ?>
                                <strong>Student's answer:</strong><br>
                                <?=nl2br(htmlspecialchars($a['answer_text']))?>
                                <?php if($a['answer_file_path']) echo "<br><a href='admin_download.php?type=exercise&file=".urlencode(basename($a['answer_file_path']))."' target='_blank'>View uploaded file</a>"; ?>
                                
                                <?php if($a['marks_awarded'] !== null): ?>
                                    <p><strong>Marked:</strong> <?=$a['marks_awarded']?>/<?=$a['points']?><br>
                                    <strong>Feedback:</strong> <?=htmlspecialchars($a['feedback'])?></p>
                                <?php else: ?>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="attempt_id" value="<?=$a['id']?>">
                                        <?php if ($a['status'] == 'paper_pending'): ?>
                                            <div class="form-group"><label>Upload scanned paper answer (image/PDF):</label><input type="file" name="paper_file" accept=".jpg,.jpeg,.png,.pdf"></div>
                                        <?php endif; ?>
                                        <div class="form-group"><label>Marks (max <?=$a['points']?>):</label><input type="number" name="marks" min="0" max="<?=$a['points']?>" required></div>
                                        <div class="form-group"><label>Feedback:</label><textarea name="feedback" rows="2"></textarea></div>
                                        <button type="submit" name="save_marks" class="btn">Save Marks</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        <?php endif; ?>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>