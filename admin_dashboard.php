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
$total_students = $conn->query("SELECT COUNT(*) FROM users WHERE approved=1 AND status!='dismissed'")->fetch_row()[0];
$pending_apps = $conn->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
$pending_exams = $conn->query("SELECT COUNT(*) FROM exam_submissions WHERE status='submitted'")->fetch_row()[0];
$pending_assign = $conn->query("SELECT COUNT(DISTINCT s.id) FROM assignment_submissions s LEFT JOIN assignments a ON s.assignment_id=a.id WHERE s.marks IS NULL")->fetch_row()[0];
$suspensions = $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended' AND suspension_end>=CURDATE()")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>

    <!-- Stats cards -->
    <div class="stats" style="display: flex; gap: 20px; flex-wrap: wrap; padding: 20px;">
        <div class="stat card" style="flex:1; min-width:150px;"><i class="fas fa-users"></i><br><strong><?= $total_students ?></strong><br>Active Students</div>
        <div class="stat card" style="flex:1; min-width:150px;"><i class="fas fa-clock"></i><br><strong><?= $pending_apps ?></strong><br>Pending Applications</div>
        <div class="stat card" style="flex:1; min-width:150px;"><i class="fas fa-file-alt"></i><br><strong><?= $pending_exams ?></strong><br>Exams Pending Marking</div>
        <div class="stat card" style="flex:1; min-width:150px;"><i class="fas fa-tasks"></i><br><strong><?= $pending_assign ?></strong><br>Assignments Pending</div>
        <div class="stat card" style="flex:1; min-width:150px;"><i class="fas fa-ban"></i><br><strong><?= $suspensions ?></strong><br>Suspended Students</div>
    </div>

    <!-- Admin action cards -->
    <div class="content-grid">
        <div class="card">
            <i class="fas fa-user-check"></i>
            <h3>Student Management</h3>
            <div class="card-buttons">
                <a href="admin_approve.php">Approve Applications</a>
                <a href="admin_students_list.php">Student List</a>
                <a href="admin_discipline.php">Discipline</a>
                <a href="admin_attendance.php">Mark Attendance</a>
                <a href="admin_reports.php">Student Reports</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-book"></i>
            <h3>Content</h3>
            <div class="card-buttons">
                <a href="admin_note_editor.php">Write Note</a>
                <a href="admin_notes_list.php">Manage Notes</a>
                <a href="admin_upload_book.php">Upload Book</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-pen-alt"></i>
            <h3>Exams</h3>
            <div class="card-buttons">
                <a href="admin_create_exam.php">Create Exam</a>
                <a href="admin_exams_list.php">Manage Exams</a>
                <a href="admin_mark_exams.php">Mark Submissions</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-tasks"></i>
            <h3>Assignments</h3>
            <div class="card-buttons">
                <a href="admin_create_assignment.php">Create Assignment</a>
                <a href="admin_assignments_list.php">Manage Assignments</a>
                <a href="admin_mark_assignments.php">Mark Submissions</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-lightbulb"></i>
            <h3>Topic Requests</h3>
            <div class="card-buttons">
                <a href="admin_topic_requests.php">View Requests</a>
                <a href="admin_topics_covered.php">Covered Topics</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-chart-line"></i>
            <h3>Reports</h3>
            <div class="card-buttons">
                <a href="admin_attendance_report.php">Attendance Report</a>
                <a href="admin_discipline_log.php">Discipline Log</a>
                <a href="admin_class_overview.php">Class Overview</a>
            </div>
        </div>
        <div class="card">
            <i class="fas fa-cogs"></i>
            <h3>System</h3>
            <div class="card-buttons">
                <a href="admin_backup.php">Backup Database</a>
                <a href="admin_settings.php">Change Password</a>
                <a href="admin_notifications_center.php">Notifications Center</a>
                <a href="admin_feedback.php">Student Feedback</a>
                <a href="admin_run_pending_exercises.php">⏰ Run Pending Exercise Checks</a>
            </div>
        </div>
    </div>

    <div class="footer">SMART Tutor – Discipline & Integrity</div>
</div>
</body>
</html>