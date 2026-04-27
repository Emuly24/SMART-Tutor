<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

// Check if approved – if not, show application link and exit
$userStatus = $conn->query("SELECT approved, fullname, class_level, status FROM users WHERE id=$uid")->fetch_assoc();
if (!$userStatus['approved']) {
    ?>
    <!DOCTYPE html><html><head><title>Pending Approval</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"></head>
    <body><div class="container"><div class="header"><h1>Application Required</h1><a href="logout.php" class="logout">Logout</a></div>
    <div class="content-grid"><div class="card"><h3>Complete Your Application</h3><p>Your account is not yet approved. Please fill in the application form to join the group.</p><a href="apply.php" class="btn">Apply Now</a></div></div></div></body></html>
    <?php
    exit;
}

$user = $userStatus; // now approved

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

// Exercise progress
$total_exercises = $conn->query("SELECT COUNT(*) FROM note_exercises e JOIN notes n ON e.note_id=n.id WHERE n.class_level='{$user['class_level']}'")->fetch_row()[0];
$done_exercises = $conn->query("SELECT COUNT(DISTINCT a.exercise_id) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=$uid AND n.class_level='{$user['class_level']}' AND a.status='marked'")->fetch_row()[0];
$exercise_progress = $total_exercises ? round($done_exercises / $total_exercises * 100) : 0;

// Quiz progress
$total_quizzes = $conn->query("SELECT COUNT(*) FROM quizzes q JOIN notes n ON q.note_id=n.id WHERE n.class_level='{$user['class_level']}'")->fetch_row()[0];
$done_quizzes = $conn->query("SELECT COUNT(*) FROM quiz_attempts a JOIN quizzes q ON a.quiz_id=q.id JOIN notes n ON q.note_id=n.id WHERE a.user_id=$uid AND n.class_level='{$user['class_level']}' AND (a.status='submitted' OR a.status='marked')")->fetch_row()[0];
$quiz_progress = $total_quizzes ? round($done_quizzes / $total_quizzes * 100) : 0;

// Pending paper exercises
$paper_pending = $conn->query("SELECT COUNT(*) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=$uid AND a.status='paper_pending'");
$paper_count = $paper_pending->fetch_row()[0];
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
    <div class="header">
        <h1><i class="fas fa-graduation-cap"></i> SMART Tutor Dashboard</h1>
        <div><a href="dashboard.php">Home</a><a href="logout.php" class="logout">Logout</a></div>
    </div>
    <div class="profile-info">
        <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['fullname']) ?></span>
        <span><i class="fas fa-chalkboard"></i> Class: <?= htmlspecialchars($user['class_level']) ?></span>
        <span><i class="fas fa-shield-alt"></i> Status: <?= ucfirst($user['status']) ?></span>
    </div>

    <?php if ($group): ?>
    <div class="card" style="margin: 0 20px 20px 20px;">
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

    <!-- Exercise Progress Bar -->
    <div class="progress-bar" style="margin: 0 20px 20px 20px;">
        <strong>📚 Exercise Progress:</strong> <?= $done_exercises ?>/<?= $total_exercises ?> completed (<?= $exercise_progress ?>%)<br>
        <div style="background:#ddd; border-radius:10px; overflow:hidden; margin-top:5px;">
            <div style="width:<?= $exercise_progress ?>%; background:#27ae60; height:20px;"></div>
        </div>
    </div>

    <!-- Quiz Progress Bar -->
    <div class="progress-bar" style="margin: 0 20px 20px 20px;">
        <strong>📊 Quiz Progress:</strong> <?= $done_quizzes ?>/<?= $total_quizzes ?> completed (<?= $quiz_progress ?>%)<br>
        <div style="background:#ddd; border-radius:10px; overflow:hidden; margin-top:5px;">
            <div class="progress-fill" style="width:<?= $quiz_progress ?>%; background:#2c7a7b; height:20px;"></div>
        </div>
    </div>

    <?php if ($paper_count > 0): ?>
        <div class="warning" style="margin: 0 20px 20px 20px;">
            ⚠️ You have <?= $paper_count ?> exercise(s) that you promised to submit on paper. 
            <a href="pending_exercises.php" style="color:#e67e22; font-weight:bold;">View pending</a>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <div class="card"><i class="fas fa-book"></i><h3>Books (PDF)</h3><a href="library.php">View Books</a></div>
        <div class="card"><i class="fas fa-folder-open"></i><h3>Subjects</h3><a href="subjects.php">Browse Subjects</a></div>
        <div class="card"><i class="fas fa-pen-alt"></i><h3>Exams</h3><a href="exams.php">Take Exams</a></div>
        <div class="card"><i class="fas fa-chart-line"></i><h3>Results</h3><a href="results.php">Check Results</a></div>
        <div class="card"><i class="fas fa-tasks"></i><h3>Assignments</h3><a href="assignments.php">Submit</a></div>
        <div class="card"><i class="fas fa-calendar-check"></i><h3>Attendance</h3><a href="attendance.php">View</a></div>
        <div class="card"><i class="fas fa-user-edit"></i><h3>Profile</h3><a href="profile.php">Edit</a></div>
        <div class="card"><i class="fas fa-lightbulb"></i><h3>Request Topic</h3><a href="request_topic.php">Suggest</a></div>
        <div class="card"><i class="fas fa-history"></i><h3>Covered Topics</h3><a href="covered_topics.php">View</a></div>
        <div class="card"><i class="fas fa-exclamation-triangle"></i><h3>Submit a Report</h3><a href="student_report.php">Report Now</a></div>
    </div>

    <?php include_once 'includes/vision_mission.php'; ?>
    <div class="footer">SMART Tutor – Discipline & Integrity</div>
</div>
</body>
</html>