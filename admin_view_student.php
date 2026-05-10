<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
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
$student_id = (int)$_GET['id'];
if (!$student_id) die("No student ID provided.");

$student = $conn->query("SELECT * FROM users WHERE id=$student_id")->fetch_assoc();
if (!$student) die("Student not found.");

$application = $conn->query("SELECT * FROM applications WHERE user_id=$student_id")->fetch_assoc();
$group = $conn->query("SELECT g.group_number, g.class_level 
    FROM group_members gm 
    JOIN groups g ON gm.group_id = g.id 
    WHERE gm.user_id=$student_id")->fetch_assoc();

$attendance = $conn->query("SELECT date, status FROM attendance WHERE user_id=$student_id ORDER BY date DESC LIMIT 30");
$discipline = $conn->query("SELECT * FROM discipline_log WHERE user_id=$student_id ORDER BY created_at DESC");
$has_profile_pic = isset($student['profile_pic']) && !empty($student['profile_pic']) && file_exists($student['profile_pic']);
?>
<!DOCTYPE html>
<html><head><title>Student Details</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <div class="profile-header">
        <?php if ($has_profile_pic): ?>
            <img src="<?= htmlspecialchars($student['profile_pic']) ?>" class="profile-pic">
        <?php else: ?>
            <i class="fas fa-user-circle" style="font-size: 80px; color: var(--accent);"></i>
        <?php endif; ?>
        <div class="profile-name">
            <h2><?= htmlspecialchars($student['fullname']) ?></h2>
            <p>Student ID: <?= $student['id'] ?></p>
        </div>
    </div>
    <div class="info-grid">
        <div class="info-card"><h3>Personal Information</h3>
            <p><strong>Full name:</strong> <?= htmlspecialchars($student['fullname']) ?></p>
            <p><strong>Gender:</strong> <?= $student['gender'] ?></p>
            <p><strong>Date of birth:</strong> <?= $student['dob'] ?></p>
            <p><strong>Class:</strong> <?= $student['class_level'] ?></p>
            <p><strong>School:</strong> <?= htmlspecialchars($student['school']) ?></p>
            <p><strong>Phone:</strong> <?= $student['phone'] ?></p>
            <p><strong>Parent phone:</strong> <?= $student['parent_phone'] ?></p>
            <p><strong>Email:</strong> <?= $student['email'] ?></p>
            <p><strong>Joined:</strong> <?= $student['created_at'] ?></p>
            <p><strong>Status:</strong> 
                <?php
                $status_class = '';
                switch($student['status']) {
                    case 'active': $status_class = 'status-active'; break;
                    case 'suspended': $status_class = 'status-suspended'; break;
                    case 'dismissed': $status_class = 'status-dismissed'; break;
                    default: $status_class = 'status-pending';
                }
                ?>
                <span class="status-badge <?= $status_class ?>"><?= ucfirst($student['status']) ?></span>
                <?php if ($student['suspension_end']): ?>
                    <br><small>Until: <?= $student['suspension_end'] ?></small>
                <?php endif; ?>
            </p>
        </div>
        <div class="info-card"><h3>Application Details</h3>
            <?php if ($application): ?>
                <p><strong>Ambition:</strong> <?= htmlspecialchars($application['ambition']) ?></p>
                <p><strong>Career reason:</strong> <?= nl2br(htmlspecialchars($application['career_reason'])) ?></p>
                <p><strong>Target university:</strong> <?= htmlspecialchars($application['university']) ?></p>
                <p><strong>Why join:</strong> <?= nl2br(htmlspecialchars($application['why_join'])) ?></p>
                <p><strong>Subjects need help:</strong> <?= htmlspecialchars($application['subject_assist']) ?></p>
                <p><strong>Target points:</strong> <?= $application['target_points'] ?></p>
                <p><strong>Submitted:</strong> <?= $application['submitted_at'] ?></p>
            <?php else: ?>
                <p>No application found.</p>
            <?php endif; ?>
        </div>
        <div class="info-card"><h3>Group</h3>
            <?php if ($group): ?>
                <p><?= $group['class_level'] ?> – Group <?= $group['group_number'] ?></p>
            <?php else: ?>
                <p>Not assigned.</p>
            <?php endif; ?>
        </div>
        <div class="info-card"><h3>Attendance (last 30 days)</h3>
            <?php if ($attendance->num_rows == 0): ?>
                <p>No records.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php while($r = $attendance->fetch_assoc()): ?>
                        <tr><td><?= $r['date'] ?></td><td><?= ucfirst($r['status']) ?></td></tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="info-card"><h3>Discipline Log</h3>
            <?php if ($discipline->num_rows == 0): ?>
                <p>No discipline actions.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Action</th><th>Reason</th><th>Suspension end</th></tr></thead>
                    <tbody>
                    <?php while($d = $discipline->fetch_assoc()): ?>
                        <tr><td><?= $d['created_at'] ?></td><td><?= ucfirst($d['action']) ?></td><td><?= htmlspecialchars($d['reason']) ?></td><td><?= $d['suspension_end'] ?? '-' ?></td></tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer"><a href="admin_students_list.php" class="btn-back">← Back to Student List</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>