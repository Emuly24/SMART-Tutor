<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Helper function to uppercase acronyms inside parentheses (e.g., (must) → (MUST))
function formatUniversityName($uni) {
    return preg_replace_callback('/\(([^)]+)\)/', function($matches) {
        return '(' . strtoupper($matches[1]) . ')';
    }, $uni);
}

$role = $_SESSION['role'] ?? (isset($_SESSION['admin_logged']) ? 'admin' : (isset($_SESSION['user_id']) ? 'student' : 'public'));
$fullname = '';
$tagline = '';

if ($role == 'student' && isset($_SESSION['user_id'])) {
    $conn = getDB();
    $uid = $_SESSION['user_id'];
    $student = $conn->query("SELECT u.fullname, a.ambition, a.university 
        FROM users u 
        LEFT JOIN applications a ON u.id = a.user_id 
        WHERE u.id = $uid")->fetch_assoc();
    if ($student) {
        $fullname = htmlspecialchars($student['fullname']);
        if (!empty($student['ambition']) || !empty($student['university'])) {
            $prefixes = [
                '✨ Aspiring', '🚀 Future', '🌟 Goal: become', '🎯 Aiming to be',
                '💡 Working towards', '📚 Pursuing', '🏆 Dreaming of', '⭐️ On a path to'
            ];
            $randomPrefix = $prefixes[array_rand($prefixes)];
            
            $career = !empty($student['ambition']) ? ucwords(strtolower($student['ambition'])) : '';
            $uni = !empty($student['university']) ? formatUniversityName(ucwords(strtolower($student['university']))) : '';
            
            if ($career && $uni) {
                $tagline = "$randomPrefix $career at $uni";
            } elseif ($career) {
                $tagline = "$randomPrefix $career";
            } elseif ($uni) {
                $uniPrefixes = ['🎓 Future student at', '🌟 Aiming for', '📚 Working towards', '🏫 Goal: study at'];
                $randomUniPrefix = $uniPrefixes[array_rand($uniPrefixes)];
                $tagline = "$randomUniPrefix $uni";
            }
        }
    }
} elseif ($role == 'admin') {
    $fullname = 'Admin';
    $tagline = 'SMART Tutor Manager';
}

// Determine page title
$current_file = basename($_SERVER['SCRIPT_NAME'], '.php');
$page_titles = [
    'index' => 'Home',
    'dashboard' => 'Dashboard',
    'admin_dashboard' => 'Admin Dashboard',
    'subjects' => 'Subjects',
    'library' => 'Books',
    'exams' => 'Exams',
    'assignments' => 'Assignments',
    'attendance' => 'Attendance',
    'profile' => 'My Profile',
    'results' => 'Results',
    'take_exam' => 'Take Exam',
    'take_quiz' => 'Take Quiz',
    'quiz_results' => 'Quiz Results',
    'exam_results' => 'Exam Results',
    'view_note' => 'Note',
    'request_topic' => 'Request Topic',
    'covered_topics' => 'Covered Topics',
    'student_report' => 'Submit Report',
    'student_message' => 'Contact Admin',
    'notifications' => 'Notifications',
    'pending_exercises' => 'Pending Exercises',
    'consent' => 'Consent Form',
    'apply' => 'Application',
    'signup' => 'Sign Up',
    'login' => 'Login',
    'admin_approve' => 'Approve Applications',
    'admin_students_list' => 'Student List',
    'admin_discipline' => 'Discipline',
    'admin_attendance' => 'Mark Attendance',
    'admin_reports' => 'Student Reports',
    'admin_upload_book' => 'Upload Book',
    'admin_create_exam' => 'Create Exam',
    'admin_exams_list' => 'Manage Exams',
    'admin_mark_exams' => 'Mark Exams',
    'admin_create_assignment' => 'Create Assignment',
    'admin_assignments_list' => 'Manage Assignments',
    'admin_mark_assignments' => 'Mark Assignments',
    'admin_topic_requests' => 'Topic Requests',
    'admin_topics_covered' => 'Covered Topics',
    'admin_delete_covered_topics' => 'Delete Covered Topics',
    'admin_export_covered_form' => 'Export CSV',
    'admin_attendance_report' => 'Attendance Report',
    'admin_discipline_log' => 'Discipline Log',
    'admin_class_overview' => 'Class Overview',
    'admin_backup' => 'Backup DB',
    'admin_settings' => 'Settings',
    'admin_notifications_center' => 'Notifications Center',
    'admin_feedback' => 'Student Feedback',
    'admin_note_editor' => 'Write Note',
    'admin_notes_list' => 'Manage Notes',
    'admin_edit_note' => 'Edit Note',
    'admin_edit_assignment' => 'Edit Assignment',
    'admin_add_questions' => 'Add Exam Questions',
    'admin_run_pending_exercises' => 'Run Pending Checks'
];
$page_title = $page_titles[$current_file] ?? ucfirst(str_replace('_', ' ', $current_file));
?>
<nav class="top-nav">
    <div class="hamburger">
        <input type="checkbox" id="menu-toggle">
        <label for="menu-toggle" class="menu-icon">☰</label>
        <ul class="menu">
            <?php if ($role == 'admin'): ?>
                <li><a href="admin_attendance_report.php">📈 Attendance Report</a></li>
                <li><a href="admin_discipline_log.php">📜 Discipline Log</a></li>
                <li><a href="admin_class_overview.php">🏫 Class Overview</a></li>
                <li><a href="admin_backup.php">💾 Backup Database</a></li>
                <li><a href="admin_settings.php">⚙️ Settings</a></li>
                <li><a href="admin_notifications_center.php">🔔 Notifications Center</a></li>
                <li><a href="admin_feedback.php">💬 Student Feedback</a></li>
                <li><a href="admin_delete_covered_topics.php">🗑️ Delete Covered Topics</a></li>
                <li><a href="admin_export_covered_form.php">📎 Export Covered Topics</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            <?php else: ?>
                <li><a href="profile.php">👤 My Profile</a></li>
                <li><a href="notifications.php">🔔 Notifications</a></li>
                <li><a href="student_message.php">📬 Contact Admin</a></li>
                <li><a href="student_report.php">⚠️ Submit a Report</a></li>
                <li><a href="request_topic.php">💡 Request Topic</a></li>
                <li><a href="covered_topics.php">📜 Covered Topics</a></li>
                <li><a href="my_group.php">👥 My Group</a></li>   
                <li><a href="logout.php">🚪 Logout</a></li>
            <?php endif; ?> 
        </ul>
        <div class="menu-overlay"></div>
    </div>

    <div class="nav-title">
        <i class="fas fa-graduation-cap"></i> SMART Tutor
    </div>

    <div class="page-title"><?= htmlspecialchars($page_title) ?></div>

    <div class="nav-right">
        <?php if ($role != 'public'): ?>
            <div class="user-info-stacked">
                <div class="user-name-stacked"><?= htmlspecialchars($fullname) ?></div>
                <?php if ($tagline): ?>
                    <div class="user-tagline-stacked"><?= htmlspecialchars($tagline) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <button id="theme-toggle" class="theme-btn" aria-label="Toggle theme">🌙</button>
        <a href="<?= ($role == 'admin') ? 'admin_dashboard.php' : 'index.php' ?>" class="btn-home">🏠 Home</a>
        <?php if ($role != 'public'): ?>
            <a href="logout.php" class="btn-logout">🚪 Logout</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Toast container -->
<div id="toast" class="toast"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme toggle
        const themeBtn = document.getElementById('theme-toggle');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark-theme');
            if (themeBtn) themeBtn.textContent = '☀️';
        }
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                body.classList.toggle('dark-theme');
                const isDark = body.classList.contains('dark-theme');
                themeBtn.textContent = isDark ? '☀️' : '🌙';
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
        }

        // Hamburger close on outside click and overlay
        const menuToggle = document.getElementById('menu-toggle');
        const overlay = document.querySelector('.menu-overlay');
        if (menuToggle && overlay) {
            overlay.addEventListener('click', function() {
                menuToggle.checked = false;
            });
            document.addEventListener('click', function(event) {
                const hamburger = document.querySelector('.hamburger');
                if (menuToggle.checked && hamburger && !hamburger.contains(event.target)) {
                    menuToggle.checked = false;
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && menuToggle.checked) {
                    menuToggle.checked = false;
                }
            });
        }
    });

    // Toast notification system
    function showToast(message, type = 'success') {
        let existingToast = document.getElementById('dynamic-toast');
        if (existingToast) existingToast.remove();
        const toast = document.createElement('div');
        toast.id = 'dynamic-toast';
        toast.className = 'toast toast-' + type;
        toast.innerHTML = `<span style="display: flex; align-items: center; gap: 0.5rem;">
            ${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
            ${message}
        </span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>