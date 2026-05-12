<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

// Fetch user data (approved, fullname, class_level, status)
$userStatus = $conn->query("SELECT approved, fullname, class_level, status FROM users WHERE id=$uid")->fetch_assoc();

// If user is not approved, show the "Complete Your Application" page
if (!$userStatus || !$userStatus['approved']) {
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

// Safe: user is approved and $userStatus is valid
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
$att_total = $att_stats['total'] ?? 0;
$att_present = $att_stats['present'] ?? 0;
$att_late = $att_stats['late'] ?? 0;
$attendance_rate = $att_total ? round((($att_present + $att_late) / $att_total) * 100) : 0;

// Message counts for the Notification card
$msg_count = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread FROM admin_messages WHERE user_id = $uid");
$msg_row = $msg_count->fetch_assoc();
$total_msgs = $msg_row['total'] ?? 0;
$unread_msgs = $msg_row['unread'] ?? 0;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ============================================
           PREMIUM STAT CARDS
           ============================================ */
        .stats-grid {
            display: flex;
            gap: 1rem;
            flex-wrap: nowrap;
            margin: 1.5rem 0;
            justify-content: center;
        }
        .stats-grid .stat-card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 1.2rem 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.05);
            flex: 1;
            min-width: 120px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1);
            position: relative;
        }
        .stats-grid .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
            border-color: var(--accent);
        }
        .stats-grid .stat-card i {
            font-size: 1.8rem;
            color: var(--accent);
            display: block;
            margin-bottom: 0.5rem;
        }
        .stats-grid .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1.2;
        }
        .stats-grid .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stats-grid .stat-card .stat-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        /* Notification Badge */
        .notif-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--error);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            padding: 0 4px;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4);
        }
        .notif-badge.zero {
            background: var(--success);
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.4);
        }
        .stats-grid .stat-card .notif-icon-wrap {
            position: relative;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php if ($user['consent_signed'] == 1): ?>
    <div style="position: relative; margin: 0.5rem 0 0.5rem 0.5rem; display: inline-block;">
        <button onclick="toggleProgressTracker()" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
            📍 Show Progress Tracker
        </button>
    </div>
    <script>
        let trackerVisible = false;
        function toggleProgressTracker() {
            const tracker = document.querySelector('.progress-tracker');
            const indicator = document.querySelector('.progress-indicator');
            if (tracker) {
                if (trackerVisible) {
                    tracker.style.display = 'none';
                    if (indicator) indicator.style.display = 'none';
                    document.querySelector('button[onclick="toggleProgressTracker()"]').textContent = '📍 Show Progress Tracker';
                } else {
                    tracker.style.display = 'flex';
                    if (indicator) indicator.style.display = 'block';
                    document.querySelector('button[onclick="toggleProgressTracker()"]').textContent = '📍 Hide Progress Tracker';
                }
                trackerVisible = !trackerVisible;
            }
        }
        // Hide by default on page load
        document.addEventListener('DOMContentLoaded', function() {
            const tracker = document.querySelector('.progress-tracker');
            const indicator = document.querySelector('.progress-indicator');
            if (tracker) {
                tracker.style.display = 'none';
                if (indicator) indicator.style.display = 'none';
            }
        });
    </script>
<?php endif; ?>
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
        <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
        <span><i class="fas fa-chalkboard"></i> Class: <?= htmlspecialchars($user['class_level'] ?? '') ?></span>
        <span><i class="fas fa-shield-alt"></i> Status: <?= ucfirst($user['status'] ?? '') ?></span>
    </div>

    <?php if ($group): ?>
    <div class="card group-card">
        <h3><i class="fas fa-users"></i> My Group: <?= htmlspecialchars($group['class_level'] ?? '') ?> – Group <?= $group['group_number'] ?? '' ?></h3>
        <p><strong>Fellow members:</strong></p>
        <ul>
        <?php foreach ($fellow_members as $f): ?>
            <li><?= htmlspecialchars($f['fullname'] ?? '') ?> (<?= htmlspecialchars($f['phone'] ?? '') ?>)</li>
        <?php endforeach; ?>
        <?php if (empty($fellow_members)) echo "<li>You are the first member of this group.</li>"; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ===== PREMIUM STAT CARDS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-dumbbell"></i>
            <div class="stat-number"><?= $done_exercises ?>/<?= $total_exercises ?></div>
            <div class="stat-label">Exercises</div>
            <div class="stat-sub">Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-question-circle"></i>
            <div class="stat-number"><?= $done_quizzes ?>/<?= $total_quizzes ?></div>
            <div class="stat-label">Quizzes</div>
            <div class="stat-sub">Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar-alt"></i>
            <div class="stat-number"><?= $attendance_rate ?>%</div>
            <div class="stat-label">Attendance (30d)</div>
            <div class="stat-sub"><?= $att_total ?> sessions</div>
        </div>
        <div class="stat-card">
            <div class="notif-icon-wrap">
                <i class="fas fa-envelope"></i>
                <!-- Notification Badge -->
                <span class="notif-badge <?= $unread_msgs == 0 ? 'zero' : '' ?>">
                    <?= $unread_msgs ?>
                </span>
            </div>
            <div class="stat-number"><?= $total_msgs ?></div>
            <div class="stat-label">Messages</div>
            <div class="stat-sub"><?= $unread_msgs > 0 ? $unread_msgs . ' unread' : 'All read ✓' ?></div>
        </div>
    </div>

    <!-- Progress Bars -->
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

    <!-- Main Learning Cards (First Row) -->
    <div class="content-grid">
        <div class="card"><i class="fas fa-book"></i><h3>Books (PDF)</h3><div class="card-buttons"><a href="library.php">View Books</a></div></div>
        <div class="card"><i class="fas fa-folder-open"></i><h3>Subjects</h3><div class="card-buttons"><a href="subjects.php">Browse Subjects</a></div></div>
        <div class="card"><i class="fas fa-pen-alt"></i><h3>Exams</h3><div class="card-buttons"><a href="exams.php">Take Exams</a></div></div>
        <div class="card"><i class="fas fa-chart-line"></i><h3>Results</h3><div class="card-buttons"><a href="results.php">Check Results</a></div></div>
        <div class="card"><i class="fas fa-tasks"></i><h3>Assignments</h3><div class="card-buttons"><a href="assignments.php">Submit</a></div></div>
        <div class="card"><i class="fas fa-calendar-check"></i><h3>Attendance</h3><div class="card-buttons"><a href="attendance.php">View</a></div></div>
    </div>

    <!-- Additional Features Row (new cards) -->
    <div class="content-grid">
        <div class="card">
            <i class="fas fa-share-alt"></i>
            <h3>Share Resource</h3>
            <p>Upload a book, past paper, or useful note.</p>
            <div class="card-buttons"><a href="share_resource.php">📤 Share Resource</a></div>
        </div>
        <div class="card">
            <i class="fas fa-list-alt"></i>
            <h3>My Resources</h3>
            <p>Track your submitted resources.</p>
            <div class="card-buttons"><a href="my_resources.php">📋 View My Resources</a></div>
        </div>
        <div class="card">
            <i class="fas fa-brain"></i>
            <h3>Self‑Assessment Quiz</h3>
            <p>Test your knowledge with instant feedback.</p>
            <div class="card-buttons"><a href="select_subject_quiz.php">📝 Take Self‑Quiz</a></div>
        </div>
        <div class="card">
            <i class="fas fa-file-signature"></i>
            <h3>My Consent</h3>
            <p>View or download your signed agreement.</p>
            <div class="card-buttons"><a href="view_consent.php">📄 View Consent</a></div>
        </div>
        <div class="card">
            <i class="fas fa-star"></i>
            <h3>Testimonial</h3>
            <p>Share your experience with SMART Circle.</p>
            <div class="card-buttons"><a href="submit_testimonial.php">✍️ Write Testimonial</a></div>
        </div>
        <div class="card">
            <i class="fas fa-users"></i>
            <h3>My Group</h3>
            <p>See group members and meeting times.</p>
            <div class="card-buttons"><a href="my_group.php">👥 View Group</a></div>
        </div>
    </div>

    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>