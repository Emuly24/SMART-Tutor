<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

// Check if approved – if not, show application link and exit
$userStatus = $conn->query("SELECT approved, fullname, class_level, status FROM users WHERE id=$uid")->fetch_assoc();
if (!$userStatus['approved']) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Pending Approval</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="container">
        <div class="content-grid">
            <div class="card">
                <h3>Complete Your Application</h3>
                <p>Your account is not yet approved. Please fill in the application form to join the group.</p>
                <div class="card-buttons"><a href="apply.php" class="btn">Apply Now</a></div>
            </div>
        </div>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <?php include_once 'includes/testimonial_prompt.php'; ?>
</body>
    </html>
    <?php
    exit;
}

$user = $userStatus;

// Group info
$group = $conn->query("SELECT g.id as group_id, g.group_number, g.class_level 
    FROM group_members gm 
    JOIN groups g ON gm.group_id = g.id 
    WHERE gm.user_id = $uid")->fetch_assoc();

$fellow_members = [];
if ($group) {
    $fellow = $conn->query("SELECT u.fullname, u.phone 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = {$group['group_id']} AND u.id != $uid");
    while ($f = $fellow->fetch_assoc()) $fellow_members[] = $f;
}

// Stats
$total_exercises = $conn->query("SELECT COUNT(*) FROM note_exercises e JOIN notes n ON e.note_id=n.id WHERE n.class_level='{$user['class_level']}'")->fetch_row()[0];
$done_exercises = $conn->query("SELECT COUNT(DISTINCT a.exercise_id) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=$uid AND n.class_level='{$user['class_level']}' AND a.status='marked'")->fetch_row()[0];
$total_quizzes = $conn->query("SELECT COUNT(*) FROM quizzes q JOIN notes n ON q.note_id=n.id WHERE n.class_level='{$user['class_level']}'")->fetch_row()[0];
$done_quizzes = $conn->query("SELECT COUNT(*) FROM quiz_attempts a JOIN quizzes q ON a.quiz_id=q.id JOIN notes n ON q.note_id=n.id WHERE a.user_id=$uid AND n.class_level='{$user['class_level']}' AND (a.status='submitted' OR a.status='marked')")->fetch_row()[0];
$att_stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late FROM attendance WHERE user_id=$uid AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc();
$att_total = $att_stats['total'];
$att_present = $att_stats['present'];
$att_late = $att_stats['late'];
$attendance_rate = $att_total ? round((($att_present + $att_late) / $att_total) * 100) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>

    <!-- Subjects Horizontal Row -->
    <div class="subjects-horizontal">
        <h3><i class="fas fa-book-open"></i> Your Subjects</h3>
        <div class="subjects-scroll">
            <?php
            $class = $_SESSION['class_level'];
            $subjects = $conn->query("SELECT DISTINCT n.subject 
                FROM notes n 
                WHERE n.class_level='$class' 
                AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                            WHERE gcl.content_type='note' AND gcl.content_id=n.id 
                            AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                            AND gcl.is_locked = 0)
                ORDER BY n.subject");
            if ($subjects->num_rows > 0): ?>
                <div class="subject-pills">
                    <?php while($sub = $subjects->fetch_assoc()): ?>
                        <a href="subject.php?subject=<?= urlencode($sub['subject']) ?>" class="subject-pill">
                            <i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($sub['subject']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No subjects unlocked yet. Please wait for admin to unlock content.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-info">
        <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['fullname']) ?></span>
        <span><i class="fas fa-chalkboard"></i> Class: <?= htmlspecialchars($user['class_level']) ?></span>
        <span><i class="fas fa-shield-alt"></i> Status: <?= ucfirst($user['status']) ?></span>
    </div>

    <?php if ($group): ?>
    <div class="card group-card">
        <h3><i class="fas fa-users"></i> My Group: <?= htmlspecialchars($group['class_level']) ?> – Group <?= $group['group_number'] ?></h3>
        <p><strong>Fellow members:</strong></p>
        <ul>
        <?php foreach ($fellow_members as $f): ?>
            <li><?= htmlspecialchars($f['fullname']) ?> (<?= htmlspecialchars($f['phone']) ?>)</li>
        <?php endforeach; ?>
        <?php if (empty($fellow_members)) echo "<li>You are the first member of this group.</li>"; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-dumbbell"></i>
            <div class="stat-number"><?= $done_exercises ?>/<?= $total_exercises ?></div>
            <div class="stat-label">Exercises Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-question-circle"></i>
            <div class="stat-number"><?= $done_quizzes ?>/<?= $total_quizzes ?></div>
            <div class="stat-label">Quizzes Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar-alt"></i>
            <div class="stat-number"><?= $attendance_rate ?>%</div>
            <div class="stat-label">Attendance (30d)</div>
        </div>
    </div>

    <div class="progress-bar">
        <strong>📚 Exercise Progress:</strong> <?= $done_exercises ?>/<?= $total_exercises ?> completed (<?= $total_exercises ? round(($done_exercises/$total_exercises)*100) : 0 ?>%)<br>
        <div class="progress-fill" style="width:<?= $total_exercises ? round(($done_exercises/$total_exercises)*100) : 0 ?>%"></div>
    </div>
    <div class="progress-bar">
        <strong>📊 Quiz Progress:</strong> <?= $done_quizzes ?>/<?= $total_quizzes ?> completed (<?= $total_quizzes ? round(($done_quizzes/$total_quizzes)*100) : 0 ?>%)<br>
        <div class="progress-fill" style="width:<?= $total_quizzes ? round(($done_quizzes/$total_quizzes)*100) : 0 ?>%"></div>
    </div>

    <?php
    $paper_pending = $conn->query("SELECT COUNT(*) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=$uid AND a.status='paper_pending'");
    $paper_count = $paper_pending->fetch_row()[0];
    if ($paper_count > 0): ?>
        <div class="warning">
            ⚠️ You have <?= $paper_count ?> exercise(s) that you promised to submit on paper. 
            <a href="pending_exercises.php">View pending</a>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <div class="card"><i class="fas fa-book"></i><h3>Books (PDF)</h3><div class="card-buttons"><a href="library.php">View Books</a></div></div>
        <div class="card"><i class="fas fa-folder-open"></i><h3>Subjects</h3><div class="card-buttons"><a href="subjects.php">Browse Subjects</a></div></div>
        <div class="card"><i class="fas fa-pen-alt"></i><h3>Exams</h3><div class="card-buttons"><a href="exams.php">Take Exams</a></div></div>
        <div class="card"><i class="fas fa-chart-line"></i><h3>Results</h3><div class="card-buttons"><a href="results.php">Check Results</a></div></div>
        <div class="card"><i class="fas fa-tasks"></i><h3>Assignments</h3><div class="card-buttons"><a href="assignments.php">Submit</a></div></div>
        <div class="card"><i class="fas fa-calendar-check"></i><h3>Attendance</h3><div class="card-buttons"><a href="attendance.php">View</a></div></div>
    </div>

    <?php include_once 'includes/vision_mission.php'; ?>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>