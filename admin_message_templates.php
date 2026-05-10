<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$msg = '';

// --- Handle AJAX template actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = $_POST['title'];
        $category = $_POST['category'];
        $message = $_POST['message'];
        $stmt = $conn->prepare("INSERT INTO message_templates (title, category, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $category, $message);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        exit;
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $category = $_POST['category'];
        $message = $_POST['message'];
        $stmt = $conn->prepare("UPDATE message_templates SET title=?, category=?, message=? WHERE id=?");
        $stmt->bind_param("sssi", $title, $category, $message, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM message_templates WHERE id=$id");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $template = $conn->query("SELECT * FROM message_templates WHERE id=$id")->fetch_assoc();
        echo json_encode($template);
        exit;
    }

    if ($action === 'use') {
        $id = (int)$_POST['id'];
        $template = $conn->query("SELECT message FROM message_templates WHERE id=$id")->fetch_assoc();
        if ($template) {
            echo json_encode(['success' => true, 'message' => $template['message']]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// --- Normal page load: fetch all templates ---
$templates = $conn->query("SELECT * FROM message_templates ORDER BY category, title");
$categories = $conn->query("SELECT DISTINCT category FROM message_templates ORDER BY category");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Message Templates – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .template-card { background: var(--card-bg); border-radius: 1rem; padding: 1.5rem; box-shadow: var(--card-shadow); transition: 0.2s; border: 1px solid rgba(0,0,0,0.02); }
        .template-card:hover { transform: translateY(-4px); box-shadow: var(--hover-shadow); }
        .template-category { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.7rem; font-weight: 600; background: var(--accent); color: #1e293b; }
        .template-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap; }
        .template-actions button { padding: 0.3rem 0.8rem; border-radius: 2rem; border: none; font-size: 0.75rem; cursor: pointer; transition: 0.2s; }
        .template-actions .btn-use { background: var(--success); color: white; }
        .template-actions .btn-edit { background: var(--info); color: white; }
        .template-actions .btn-delete { background: var(--error); color: white; }
        .template-actions button:hover { transform: scale(1.02); filter: brightness(0.95); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: var(--card-bg); padding: 2rem; max-width: 600px; width: 90%; border-radius: 1rem; max-height: 90%; overflow-y: auto; }
        .placeholder-ref { background: var(--card-alt-bg); padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .placeholder-ref code { background: rgba(0,0,0,0.05); padding: 0.1rem 0.3rem; border-radius: 0.2rem; }
        .filter-bar { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: center; }
        .filter-bar select, .filter-bar input { padding: 0.3rem 0.8rem; border-radius: 2rem; border: 1px solid var(--card-alt-bg); }
        .filter-bar .search-box { flex: 1; min-width: 200px; }
        .stat-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.7rem; background: var(--primary-medium); color: white; }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1><i class="fas fa-pencil-alt"></i> Message Templates</h1>
        <p>Create, edit, and manage message templates for <strong>single‑student, multi‑student, group‑wide, and class‑wide</strong> communication. Use the placeholders below to personalise every message automatically.</p>

        <div class="filter-bar">
            <input type="text" id="searchTemplates" class="search-box" placeholder="Search templates...">
            <select id="filterCategory">
                <option value="">All Categories</option>
                <?php while($c = $categories->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($c['category']) ?>"><?= htmlspecialchars($c['category']) ?></option>
                <?php endwhile; ?>
            </select>
            <button id="addTemplateBtn" class="btn">+ New Template</button>
            <button id="seedTemplatesBtn" class="btn-secondary">📥 Seed Global Templates</button>
            <span class="stat-badge" id="templateCount"><?= $templates->num_rows ?> templates</span>
        </div>

        <div class="template-grid" id="templateGrid">
            <!-- Templates will be rendered here via JS -->
        </div>

        <!-- Add/Edit Modal -->
        <div id="templateModal" class="modal">
            <div class="modal-content">
                <span id="modalClose" class="close">&times;</span>
                <h3 id="modalTitle">New Template</h3>
                <form id="templateForm">
                    <input type="hidden" name="id" id="templateId" value="0">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" id="templateTitle" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="templateCategory" required>
                            <option value="Meetings">Meetings</option>
                            <option value="Academics">Academics</option>
                            <option value="Discipline">Discipline</option>
                            <option value="Events">Events</option>
                            <option value="Administrative">Administrative</option>
                            <option value="Reminders">Reminders</option>
                            <option value="Welcome">Welcome</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message (use placeholders)</label>
                        <textarea name="message" id="templateMessage" rows="6" required></textarea>
                        <small class="help-text">Available placeholders: <code>{student}</code>, <code>{class}</code>, <code>{route}</code>, <code>{group}</code>, <code>{date}</code>, <code>{time}</code>, <code>{place}</code>, <code>{note_title}</code>, <code>{hours_remaining}</code>, <code>{due_date}</code>, <code>{link}</code>, <code>{subject}</code>, <code>{assignment_title}</code>, <code>{teacher_name}</code>.</small>
                    </div>
                    <button type="submit" class="btn">Save Template</button>
                </form>
            </div>
        </div>

        <!-- Placeholder Reference -->
        <div class="placeholder-ref">
            <h4>📝 Placeholder Reference</h4>
            <p>These placeholders are automatically replaced when you send from the Notifications Center:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                <code>{student}</code> → Student full name
                <code>{class}</code> → Class (e.g., Form 3)
                <code>{route}</code> → Route (Sciences / Humanities)
                <code>{group}</code> → Group number (e.g., 1)
                <code>{date}</code> → Today's date (e.g., 09 May 2026)
                <code>{time}</code> → Current time (e.g., 10:46 AM)
                <code>{place}</code> → Meeting location (you type this)
                <code>{note_title}</code> → Title of the related note
                <code>{hours_remaining}</code> → Hours remaining for exercise submission
                <code>{due_date}</code> → Due date of assignment/library book
                <code>{link}</code> → Any link you provide
                <code>{subject}</code> → Subject (e.g., Mathematics)
                <code>{assignment_title}</code> → Title of the assignment
                <code>{teacher_name}</code> → Teacher's name (you type this)
            </div>
        </div>

        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>

    <a href="#" class="back-to-top" id="backToTop">↑</a>

    <script>
        let allTemplates = [];

        // --- Load templates ---
        function loadTemplates() {
            fetch('admin_message_templates.php?action=load')
                .then(res => res.json())
                .then(data => {
                    allTemplates = data;
                    renderTemplates(data);
                })
                .catch(err => console.error(err));
        }

        function renderTemplates(data) {
            const grid = document.getElementById('templateGrid');
            const filterCategory = document.getElementById('filterCategory').value;
            const search = document.getElementById('searchTemplates').value.toLowerCase();

            const filtered = data.filter(t => {
                const matchCat = !filterCategory || t.category === filterCategory;
                const matchSearch = t.title.toLowerCase().includes(search) || t.message.toLowerCase().includes(search);
                return matchCat && matchSearch;
            });

            grid.innerHTML = '';
            if (filtered.length === 0) {
                grid.innerHTML = '<div class="card"><p>No templates found. Try adjusting your filters or create a new template.</p></div>';
                return;
            }

            filtered.forEach(t => {
                const card = document.createElement('div');
                card.className = 'template-card';
                card.innerHTML = `
                    <div class="template-category">${escapeHtml(t.category)}</div>
                    <h4 style="margin-top:0.5rem;">${escapeHtml(t.title)}</h4>
                    <p style="font-size:0.9rem; color:var(--text-muted);">${escapeHtml(t.message.substring(0, 120))}${t.message.length > 120 ? '...' : ''}</p>
                    <div class="template-actions">
                        <button class="btn-use" onclick="useTemplate(${t.id})">📨 Use</button>
                        <button class="btn-edit" onclick="editTemplate(${t.id})">✏️ Edit</button>
                        <button class="btn-delete" onclick="deleteTemplate(${t.id})">🗑️ Delete</button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // --- Use template (redirect to Notifications Center with pre-filled message) ---
        function useTemplate(id) {
            fetch('admin_message_templates.php?action=use&id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Store in sessionStorage for the Notifications Center to pick up
                        sessionStorage.setItem('useTemplateMessage', data.message);
                        window.location.href = 'admin_notifications_center.php#tab-send';
                    } else {
                        alert('Template not found.');
                    }
                });
        }

        // --- Add / Edit template ---
        function openModal(id = 0) {
            document.getElementById('templateId').value = id;
            document.getElementById('modalTitle').textContent = id ? 'Edit Template' : 'New Template';
            document.getElementById('templateForm').reset();
            document.getElementById('templateModal').style.display = 'flex';
            if (id) {
                fetch('admin_message_templates.php?action=get&id=' + id)
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('templateTitle').value = data.title;
                        document.getElementById('templateCategory').value = data.category;
                        document.getElementById('templateMessage').value = data.message;
                    });
            }
        }

        function closeModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        document.getElementById('addTemplateBtn').addEventListener('click', () => openModal(0));
        document.getElementById('modalClose').addEventListener('click', closeModal);
        document.getElementById('templateModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });

        // --- Save template ---
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('templateId').value;
            const action = id ? 'edit' : 'add';
            const data = {
                action: action,
                id: id,
                title: document.getElementById('templateTitle').value,
                category: document.getElementById('templateCategory').value,
                message: document.getElementById('templateMessage').value
            };
            fetch('admin_message_templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal();
                    loadTemplates();
                } else {
                    alert('Error saving template.');
                }
            });
        });

        // --- Delete template ---
        function deleteTemplate(id) {
            if (!confirm('Delete this template?')) return;
            fetch('admin_message_templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) loadTemplates();
                else alert('Error deleting template.');
            });
        }

        // --- Seed global templates ---
        document.getElementById('seedTemplatesBtn').addEventListener('click', function() {
            if (!confirm('This will add 50+ global templates. Continue?')) return;
            fetch('admin_message_templates.php?action=seed')
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        loadTemplates();
                        alert('✅ ' + res.count + ' templates added.');
                    } else {
                        alert('Error seeding templates: ' + (res.error || 'Unknown'));
                    }
                });
        });

        // --- Filter/Search ---
        document.getElementById('searchTemplates').addEventListener('input', () => renderTemplates(allTemplates));
        document.getElementById('filterCategory').addEventListener('change', () => renderTemplates(allTemplates));

        // --- If coming from Notifications Center with a template to use ---
        const useMsg = sessionStorage.getItem('useTemplateMessage');
        if (useMsg) {
            sessionStorage.removeItem('useTemplateMessage');
            // The Notifications Center will pick this up when it loads
        }

        // --- Initial load ---
        loadTemplates();
    </script>

    <!-- Seed action handler (hidden) -->
    <?php
    if (isset($_GET['action']) && $_GET['action'] === 'seed') {
        $seed = [
            ['Meetings', 'Group Meeting – Reminder', '📅 Reminder: Your group meeting for {class} – Group {group} is scheduled for {date} at {time}. Location: {place}. Please be punctual.'],
            ['Meetings', 'Group Meeting – Changed Time', '⚠️ Important: The meeting time for {class} – Group {group} has changed to {time} on {date}. Please adjust your schedule. Location: {place}.'],
            ['Meetings', 'Group Meeting – Cancelled', '❌ The meeting for {class} – Group {group} scheduled for {date} at {time} has been cancelled. Please check for updates.'],
            ['Meetings', 'Class‑wide Meeting Reminder', '📅 All {class} {route} students: Your group meeting is on {date} at {time}. Location: {place}. Attendance is required.'],
            ['Meetings', 'Meeting Agenda Request', '📋 Please submit your agenda items for the upcoming {class} – Group {group} meeting by {date}.'],
            ['Academics', 'Exercise Reminder – 24h', 'Dear {student}, you promised to submit the exercise for "{note_title}" on paper. Please submit within 12 hours or your account will be suspended. You have {hours_remaining} hours remaining.'],
            ['Academics', 'Exercise Reminder – Gentle', 'Dear {student}, just a friendly reminder that the exercise for "{note_title}" was due. Please submit as soon as possible.'],
            ['Academics', 'Exercise Overdue – Suspension Warning', 'Dear {student}, your exercise for "{note_title}" is overdue. Your account will be suspended if not submitted within 24 hours.'],
            ['Academics', 'Subject Question Answered', 'Dear {student}, your question in {subject} has been answered. Please check the subject page for the full answer.'],
            ['Academics', 'New Note Available', '📘 A new note for {subject} – "{note_title}" has been posted. Please review it before your next session.'],
            ['Academics', 'Assignment Reminder – Due Soon', 'Dear {student}, the assignment "{assignment_title}" is due on {due_date}. Please submit it on time.'],
            ['Academics', 'Assignment Overdue', 'Dear {student}, the assignment "{assignment_title}" was due on {due_date} and has not been submitted. Please submit it immediately.'],
            ['Academics', 'Exam Reminder', '📝 Your {subject} exam is scheduled for {date} at {time}. Please be prepared and on time.'],
            ['Academics', 'Exam Results Published', '📊 The results for your {subject} exam have been published. Please check your dashboard.'],
            ['Academics', 'Study Group Invitation', '🎯 You are invited to join a study session on {subject} for {class} on {date} at {time}. Location: {place}.'],
            ['Academics', 'Topic Request Acknowledged', 'Thank you for requesting the topic "{topic}" in {subject}. We will do our best to cover it in an upcoming session.'],
            ['Academics', 'Topic Covered', '📚 The topic "{topic}" in {subject} has been covered. Please refer to the notes for the full explanation.'],
            ['Academics', 'Book Recommendation', '📖 I recommend reading "{book_title}" for {subject}. You can find it in the library.'],
            ['Academics', 'Session Cancellation', '⚠️ The session for {subject} on {date} at {time} has been cancelled. Please check the updated schedule.'],
            ['Discipline', 'Attendance Warning', '⏳ Dear {student}, you have missed a session. Please contact the admin to explain the reason for your absence.'],
            ['Discipline', 'Attendance Warning – Repeated', '⏳ Dear {student}, you have missed multiple sessions. Please arrange a meeting with the admin to discuss your attendance.'],
            ['Discipline', 'Lateness Notice', '⏰ Dear {student}, you have been late to class. Please ensure you arrive on time for future sessions.'],
            ['Discipline', 'Disrespect Warning', '🚫 Dear {student}, a report has been filed regarding disrespectful behaviour. Please see the admin immediately.'],
            ['Discipline', 'Dismissal Notice', '❌ Dear {student}, your account has been dismissed for repeated violations. Please contact the admin for more information.'],
            ['Discipline', 'Suspension Notice – Temporary', '⛔ Dear {student}, your account has been suspended until {date}. Please contact the admin to discuss next steps.'],
            ['Discipline', 'Good Conduct Award', '🌟 Dear {student}, you have been recognised for good conduct and discipline. Keep up the excellent work!'],
            ['Discipline', 'Report Submitted – Acknowledged', '✅ Your report has been received and is under review. Thank you for your honesty.'],
            ['Discipline', 'Report Reviewed', '📋 Your report has been reviewed. The admin will take appropriate action.'],
            ['Discipline', 'Report Resolved', '✅ Your report has been resolved. Thank you for your patience.'],
            ['Events', 'Event Announcement – General', '📢 Announcement: {event_name} will take place on {date} at {time}. Location: {place}. All students are encouraged to attend.'],
            ['Events', 'Event Reminder – Upcoming', '📅 Reminder: {event_name} is coming up on {date} at {time}. Location: {place}. See you there!'],
            ['Events', 'Event Cancelled', '❌ The event "{event_name}" scheduled for {date} has been cancelled. We apologise for any inconvenience.'],
            ['Events', 'Event Postponed', '📅 The event "{event_name}" has been postponed to a later date. Please check the updated schedule.'],
            ['Events', 'Registration Open', '📝 Registration for {event_name} is now open. Please sign up before {date}.'],
            ['Events', 'Registration Deadline Reminder', '⏳ The registration deadline for {event_name} is {date}. Please complete your registration soon.'],
            ['Administrative', 'Welcome to SMART Circle', 'Welcome, {student}, to {class} – Group {group}. We are glad to have you in the SMART Circle community.'],
            ['Administrative', 'Welcome to New Group', '🏫 Welcome, {student}, to your new group: {class} – Group {group} ({route}). Please check your dashboard for updates.'],
            ['Administrative', 'Account Approved – Next Steps', '🎉 Congratulations, {student}! Your application has been approved. Please proceed to sign the consent form before accessing the dashboard.'],
            ['Administrative', 'Consent Signed – Thank You', '✅ Thank you, {student}, for signing the consent agreement. You now have full access to the dashboard.'],
            ['Administrative', 'Route Updated', '📌 Your route has been updated to {route}. Please check the group settings for any changes.'],
            ['Administrative', 'Group Assignment Change', '🔄 You have been reassigned to {class} – Group {group} ({route}). Please check your dashboard for details.'],
            ['Administrative', 'Password Reset Request', '🔑 A password reset request has been received for your account. If you did not make this request, please contact the admin.'],
            ['Administrative', 'Profile Update', '📝 Your profile has been updated successfully. Please review the changes in your profile section.'],
            ['Reminders', 'Library Book Due Tomorrow', '📚 Reminder: The book "{book_title}" is due tomorrow ({due_date}). Please return it on time.'],
            ['Reminders', 'Library Book Overdue', '📚 Reminder: The book "{book_title}" is overdue (due {due_date}). Please return it as soon as possible.'],
            ['Reminders', 'Library Book Returned – Thank You', '✅ Thank you for returning "{book_title}". Your account has been updated.'],
            ['Reminders', 'Payment Reminder (Free Platform – no charges)', 'ℹ️ SMART Circle is a free platform. No payment is required. If you are asked for money, please report it immediately.'],
            ['Reminders', 'Feedback Request', '📝 We would love to hear about your experience with SMART Circle. Please take a moment to submit your testimonial.'],
            ['Reminders', 'Testimonial Approved', '⭐ Your testimonial has been approved and is now visible on the homepage. Thank you for sharing!'],
            ['Reminders', 'Testimonial Rejected', '⚠️ Your testimonial could not be approved. Please review the guidelines and resubmit.'],
            ['Emergency', 'Urgent Notice – All Students', '🚨 Urgent notice for all {class} students: {message}. Please check your messages urgently.'],
            ['Emergency', 'Emergency Closure', '🚨 All sessions for {date} are cancelled due to {reason}. Please stay safe and await further updates.'],
            ['Emergency', 'System Downtime Advisory', '⚠️ The platform will be offline for maintenance on {date} from {time} to {time}. Please plan accordingly.'],
            ['Emergency', 'Security Alert', '🔐 A security alert has been issued. Please change your password immediately and review your account activity.'],
        ];

        $count = 0;
        foreach ($seed as $row) {
            $cat = $row[0];
            $title = $row[1];
            $msg = $row[2];
            $check = $conn->query("SELECT id FROM message_templates WHERE title='$title' AND category='$cat'");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO message_templates (title, category, message) VALUES ('$title', '$cat', '$msg')");
                $count++;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // --- Load action ---
    if (isset($_GET['action']) && $_GET['action'] === 'load') {
        $templates = $conn->query("SELECT * FROM message_templates ORDER BY category, title");
        $data = [];
        while ($t = $templates->fetch_assoc()) {
            $data[] = $t;
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    // --- Get action (for edit) ---
    if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $t = $conn->query("SELECT * FROM message_templates WHERE id=$id")->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($t);
        exit;
    }
    ?>
</body>
</html>