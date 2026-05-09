<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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

// Ensure columns exist
$conn->query("ALTER TABLE admin_messages ADD COLUMN IF NOT EXISTS recipient_type VARCHAR(20) DEFAULT 'student'");
$conn->query("ALTER TABLE admin_messages ADD COLUMN IF NOT EXISTS recipient_info TEXT DEFAULT ''");
$conn->query("ALTER TABLE admin_messages ADD COLUMN IF NOT EXISTS is_system TINYINT(1) DEFAULT 0");

$success_msg = $error_msg = '';

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $ajax = $_POST['ajax'];

    // --- Delete message ---
    if ($ajax === 'delete_message') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM admin_messages WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }

    // --- Edit message ---
    if ($ajax === 'edit_message') {
        $id = (int)$_POST['id'];
        $new_msg = trim($_POST['message']);
        $conn->query("UPDATE admin_messages SET message = '$new_msg' WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }

    // --- Send message (with duplicate check) ---
    if ($ajax === 'send_message') {
        $message_template = trim($_POST['message']);
        $recipient_type = $_POST['recipient_type'] ?? 'students';
        $user_ids = [];

        if (empty($message_template)) {
            echo json_encode(['success' => false, 'error' => 'Message is empty']);
            exit;
        }

        // Collect recipients based on type
        if ($recipient_type === 'students') {
            $user_ids = $_POST['user_ids'] ?? [];
        } elseif ($recipient_type === 'groups') {
            $group_ids = $_POST['group_ids'] ?? [];
            foreach ($group_ids as $gid) {
                $members = $conn->query("SELECT user_id FROM group_members WHERE group_id = $gid");
                while ($m = $members->fetch_assoc()) {
                    $user_ids[] = $m['user_id'];
                }
            }
        } elseif ($recipient_type === 'classes') {
            $class_levels = $_POST['class_levels'] ?? [];
            $route = $_POST['class_route'] ?? '';
            foreach ($class_levels as $class) {
                $sql = "SELECT id FROM users WHERE approved=1 AND class_level='$class'";
                if ($route) $sql .= " AND route='$route'";
                $students = $conn->query($sql);
                while ($s = $students->fetch_assoc()) {
                    $user_ids[] = $s['id'];
                }
            }
        }

        $user_ids = array_unique($user_ids);
        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'error' => 'No recipients selected']);
            exit;
        }

        $sent_count = 0;
        $skipped_count = 0;

        foreach ($user_ids as $uid) {
            $uid = (int)$uid;
            $student = $conn->query("
                SELECT u.fullname, u.gender, u.class_level, u.route,
                       (SELECT group_number FROM group_members gm 
                        JOIN groups g ON gm.group_id = g.id 
                        WHERE gm.user_id = u.id) as group_number
                FROM users u WHERE u.id = $uid
            ")->fetch_assoc();
            if (!$student) continue;

            // Generate title and first name
            $fullname_parts = explode(' ', trim($student['fullname']));
            $first_name = $fullname_parts[0];
            $surname = end($fullname_parts);
            $title = ($student['gender'] ?? 'Male') === 'Male' ? 'Mr' : 'Miss';
            $student_name_display = "$title $surname";

            $replacements = [
                '{student}' => $student_name_display,
                '{fullname}' => $student['fullname'],
                '{first_name}' => $first_name,
                '{surname}' => $surname,
                '{title}' => $title,
                '{class}' => $student['class_level'],
                '{route}' => ucfirst($student['route'] ?? 'Not set'),
                '{group}' => $student['group_number'] ?? 'No group',
                '{date}' => date('d M Y'),
                '{time}' => date('h:i A'),
            ];

            $personalised_message = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $message_template
            );
            $personalised_message = $conn->real_escape_string($personalised_message);

            // --- Duplicate check: same message sent to same user within 1 hour ---
            $dup_check = $conn->query("
                SELECT id FROM admin_messages 
                WHERE user_id = $uid AND message = '$personalised_message' 
                AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            if ($dup_check->num_rows > 0) {
                $skipped_count++;
                continue;
            }

            $conn->query("INSERT INTO admin_messages (user_id, message, recipient_type) 
                         VALUES ($uid, '$personalised_message', '$recipient_type')");
            $sent_count++;
        }

        echo json_encode([
            'success' => true,
            'sent' => $sent_count,
            'skipped' => $skipped_count,
            'total' => count($user_ids)
        ]);
        exit;
    }
}

// ==================== FETCH DATA ====================
$sent = $conn->query("
    SELECT m.id, m.message, m.sent_at, m.read_at, u.fullname, m.recipient_type 
    FROM admin_messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.sent_at DESC LIMIT 100
");

$feedback = $conn->query("SELECT m.id, m.subject, m.message, m.created_at, m.status, u.fullname 
    FROM student_messages m JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC");

$reports = $conn->query("SELECT r.id, r.report_type, r.description, r.incident_date, r.created_at, r.status, r.admin_response, u.fullname, u.class_level 
    FROM student_reports r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");

$questions = $conn->query("SELECT q.id, q.subject, q.question, q.answer, q.status, q.created_at, q.answered_at, u.fullname 
    FROM subject_questions q JOIN users u ON q.user_id = u.id ORDER BY q.created_at DESC");

$requests = $conn->query("SELECT r.id, r.subject, r.topic, r.created_at, u.fullname, u.class_level 
    FROM topic_requests r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");

$testimonials = $conn->query("SELECT t.id, t.fullname, t.class_level, t.testimonial, t.rating, t.status, t.created_at 
    FROM testimonials t ORDER BY t.created_at DESC");

$templates = $conn->query("SELECT id, title FROM message_templates ORDER BY id");
$students = $conn->query("SELECT id, fullname FROM users WHERE approved=1 ORDER BY fullname");
$groups = $conn->query("SELECT id, class_level, group_number, route FROM groups ORDER BY class_level, route, group_number");
$class_levels = ['Form 3', 'Form 4'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Notifications Center – SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .tab { display: none; }
        .tab.active { display: block; }
        .tab-header { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .tab-btn { padding: 0.6rem 1.2rem; background: var(--card-alt-bg); border: none; border-radius: 2rem; cursor: pointer; font-weight: 500; transition: 0.2s; color: var(--text-color); }
        .tab-btn.active { background: var(--accent); color: #1e293b; font-weight: 600; }
        .tab-btn:hover { transform: scale(1.02); }
        .badge { background: var(--error); color: white; border-radius: 50%; padding: 0.1rem 0.4rem; font-size: 0.7rem; vertical-align: middle; }
        
        .message-item { border: 1px solid var(--card-alt-bg); padding: 1rem; border-radius: 0.8rem; margin-bottom: 1rem; background: var(--card-bg); transition: 0.2s; }
        .message-item:hover { border-color: var(--accent); }
        .message-item.unread { border-left: 4px solid var(--info); background: rgba(37, 99, 235, 0.05); }
        .message-item.read { border-left: 4px solid var(--success); }
        
        .recipient-tag { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 1rem; font-size: 0.7rem; background: var(--card-alt-bg); color: var(--text-muted); }
        .status-badge { padding: 0.2rem 0.6rem; border-radius: 1rem; font-size: 0.7rem; }
        .status-unread { background: var(--info); color: white; }
        .status-read { background: var(--success); color: white; }
        .status-pending { background: var(--warning); color: white; }
        
        .action-btn { padding: 0.2rem 0.6rem; border-radius: 1.5rem; border: none; font-size: 0.75rem; cursor: pointer; transition: 0.2s; }
        .action-btn:hover { transform: scale(1.05); }
        .btn-sm-edit { background: var(--info); color: white; }
        .btn-sm-delete { background: var(--error); color: white; }

        .compose-subtab { display: none; margin-top: 1rem; }
        .compose-subtab.active { display: block; }
        .subtab-btn { background: transparent; border: none; padding: 0.5rem 1rem; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 500; color: var(--text-muted); }
        .subtab-btn.active { border-bottom-color: var(--accent); color: var(--accent); }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--primary-dark); color: white; padding: 0.8rem 1.2rem; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 9999; transform: translateX(120%); transition: transform 0.3s; }
        .toast.show { transform: translateX(0); }
        
        .edit-textarea { width: 100%; min-height: 60px; padding: 0.5rem; border: 1px solid var(--accent); border-radius: 0.5rem; margin: 0.5rem 0; resize: vertical; }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1><i class="fas fa-bell" style="color: var(--accent);"></i> Admin Notifications Center</h1>
        
        <?php if ($success_msg): ?>
            <div class="success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="tab-header">
            <button class="tab-btn active" data-tab="tab-outgoing">📨 Outgoing</button>
            <button class="tab-btn" data-tab="tab-feedback">💬 Feedback <span class="badge"><?= $feedback->num_rows ?></span></button>
            <button class="tab-btn" data-tab="tab-reports">📋 Reports <span class="badge"><?= $reports->num_rows ?></span></button>
            <button class="tab-btn" data-tab="tab-questions">❓ Questions <span class="badge"><?= $questions->num_rows ?></span></button>
            <button class="tab-btn" data-tab="tab-requests">💡 Topic Requests <span class="badge"><?= $requests->num_rows ?></span></button>
            <button class="tab-btn" data-tab="tab-testimonials">⭐ Testimonials <span class="badge"><?= $testimonials->num_rows ?></span></button>
            <button class="tab-btn" data-tab="tab-send">✏️ Compose</button>
        </div>

        <!-- ======= TAB: Outgoing ======= -->
        <div id="tab-outgoing" class="tab active">
            <h3>Sent Messages</h3>
            <p>All messages sent to students via this system.</p>
            <?php if ($sent->num_rows == 0): ?>
                <div class="card"><p>No messages sent yet.</p></div>
            <?php else: ?>
                <?php while($m = $sent->fetch_assoc()): ?>
                    <div class="message-item <?= $m['read_at'] ? 'read' : 'unread' ?>" id="msg-<?= $m['id'] ?>">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem;">
                            <div>
                                <strong><?= htmlspecialchars($m['fullname']) ?></strong>
                                <span class="status-badge <?= $m['read_at'] ? 'status-read' : 'status-unread' ?>"><?= $m['read_at'] ? 'Read' : 'Unread' ?></span>
                                <span class="recipient-tag"><?= ucfirst($m['recipient_type'] ?? 'student') ?></span>
                            </div>
                            <div style="display:flex; gap:0.3rem;">
                                <button class="action-btn btn-sm-edit" onclick="editMessage(<?= $m['id'] ?>)">✏️</button>
                                <button class="action-btn btn-sm-delete" onclick="deleteMessage(<?= $m['id'] ?>)">🗑️</button>
                            </div>
                        </div>
                        <div id="msg-content-<?= $m['id'] ?>"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <div id="msg-edit-<?= $m['id'] ?>" style="display:none;">
                            <textarea class="edit-textarea" id="edit-text-<?= $m['id'] ?>"><?= htmlspecialchars($m['message']) ?></textarea>
                            <button class="btn-sm-edit" onclick="saveEdit(<?= $m['id'] ?>)">Save</button>
                            <button class="btn-secondary" onclick="cancelEdit(<?= $m['id'] ?>)">Cancel</button>
                        </div>
                        <small class="text-muted">Sent: <?= $m['sent_at'] ?></small>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

         <!-- ======= TAB: Feedback (Student Messages) ======= -->
        <div id="tab-feedback" class="tab">
            <h3>Student Feedback</h3>
            <p>Messages sent by students to the admin.</p>
            <?php if ($feedback->num_rows == 0): ?>
                <p>No feedback received yet.</p>
            <?php else: ?>
                <?php while($f = $feedback->fetch_assoc()): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars($f['subject']) ?> <span class="status-badge <?= $f['status'] == 'unread' ? 'status-unread' : 'status-read' ?>"><?= $f['status'] ?></span></h4>
                        <p><strong>From:</strong> <?= htmlspecialchars($f['fullname']) ?></p>
                        <p><?= nl2br(htmlspecialchars($f['message'])) ?></p>
                        <small class="text-muted">Received: <?= $f['created_at'] ?></small>
                        <?php if ($f['status'] == 'unread'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <button type="submit" name="mark_feedback_read" class="action-btn btn-secondary">Mark as Read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ======= TAB: Reports ======= -->
        <div id="tab-reports" class="tab">
            <h3>Student Reports</h3>
            <p>Reports submitted by students.</p>
            <?php if ($reports->num_rows == 0): ?>
                <p>No reports yet.</p>
            <?php else: ?>
                <?php while($r = $reports->fetch_assoc()): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars($r['fullname']) ?> (<?= $r['class_level'] ?>) – <span class="status-badge <?= $r['status'] ?>"><?= $r['status'] ?></span></h4>
                        <p><strong>Type:</strong> <?= htmlspecialchars($r['report_type']) ?></p>
                        <p><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                        <small class="text-muted">Date: <?= $r['incident_date'] ?> | Submitted: <?= $r['created_at'] ?></small>
                        <div style="margin-top:0.5rem;">
                            <form method="post" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <select name="status" style="padding:0.3rem; border-radius:0.5rem;">
                                    <option value="pending" <?= $r['status']=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="reviewed" <?= $r['status']=='reviewed'?'selected':'' ?>>Reviewed</option>
                                    <option value="resolved" <?= $r['status']=='resolved'?'selected':'' ?>>Resolved</option>
                                </select>
                                <textarea name="admin_response" rows="2" placeholder="Admin response..." style="flex:1; min-width:150px;"><?= htmlspecialchars($r['admin_response']) ?></textarea>
                                <button type="submit" name="update_report" class="action-btn btn-success">Update</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ======= TAB: Questions ======= -->
        <div id="tab-questions" class="tab">
            <h3>Subject Questions</h3>
            <p>Questions sent by students about specific subjects.</p>
            <?php if ($questions->num_rows == 0): ?>
                <p>No questions yet.</p>
            <?php else: ?>
                <?php while($q = $questions->fetch_assoc()): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars($q['subject']) ?> – <?= htmlspecialchars($q['fullname']) ?></h4>
                        <p><strong>Question:</strong> <?= nl2br(htmlspecialchars($q['question'])) ?></p>
                        <?php if ($q['status'] == 'answered' && $q['answer']): ?>
                            <div style="background:var(--card-alt-bg); padding:0.5rem; border-radius:0.5rem;">
                                <strong>Answer:</strong> <?= nl2br(htmlspecialchars($q['answer'])) ?> <br>
                                <small class="text-muted">Answered: <?= $q['answered_at'] ?></small>
                            </div>
                        <?php else: ?>
                            <form method="post" style="margin-top:0.5rem;">
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <textarea name="answer" rows="3" placeholder="Write your answer..." style="width:100%;"></textarea>
                                <button type="submit" name="answer_question" class="action-btn btn-success">Submit Answer</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ======= TAB: Requests ======= -->
        <div id="tab-requests" class="tab">
            <h3>Topic Requests</h3>
            <p>Topics students want the group to cover.</p>
            <?php if ($requests->num_rows == 0): ?>
                <p>No topic requests.</p>
            <?php else: ?>
                <?php while($r = $requests->fetch_assoc()): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars($r['fullname']) ?> (<?= $r['class_level'] ?>)</h4>
                        <p><strong>Subject:</strong> <?= htmlspecialchars($r['subject']) ?></p>
                        <p><strong>Topic:</strong> <?= nl2br(htmlspecialchars($r['topic'])) ?></p>
                        <small class="text-muted">Requested: <?= $r['created_at'] ?></small>
                        <div style="margin-top:0.5rem; display:flex; gap:0.5rem;">
                            <form method="post">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" name="acknowledge_request" class="action-btn btn-secondary">🤝 Acknowledge</button>
                                <button type="submit" name="mark_covered" class="action-btn btn-success" onclick="return confirm('Mark this topic as covered?')">📚 Mark Covered</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ======= TAB: Testimonials ======= -->
        <div id="tab-testimonials" class="tab">
            <h3>Testimonials</h3>
            <p>Student testimonials awaiting approval or already approved.</p>
            <?php if ($testimonials->num_rows == 0): ?>
                <p>No testimonials yet.</p>
            <?php else: ?>
                <?php while($t = $testimonials->fetch_assoc()): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars($t['fullname']) ?> (<?= $t['class_level'] ?>) – <span class="status-badge <?= $t['status'] ?>"><?= $t['status'] ?></span></h4>
                        <p><?= str_repeat('⭐', $t['rating']) ?></p>
                        <p><em>"<?= nl2br(htmlspecialchars($t['testimonial'])) ?>"</em></p>
                        <small class="text-muted">Submitted: <?= $t['created_at'] ?></small>
                        <?php if ($t['status'] == 'pending'): ?>
                            <div style="margin-top:0.5rem;">
                                <a href="?action=approve&id=<?= $t['id'] ?>" class="action-btn btn-success">Approve</a>
                                <a href="?action=reject&id=<?= $t['id'] ?>" class="action-btn btn-danger" onclick="return confirm('Reject this testimonial?')">Reject</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>


        <!-- ======= TAB: Compose ======= -->
        <div id="tab-send" class="tab">
            <h3>Compose Message</h3>
            <p>Send a message to one or more students, groups, or classes. Placeholders are automatically replaced.</p>
            
            <div class="card">
                <div style="display:flex; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; border-bottom:1px solid var(--card-alt-bg); padding-bottom:0.5rem;">
                    <button class="subtab-btn active" data-subtab="subtab-students">👤 Students</button>
                    <button class="subtab-btn" data-subtab="subtab-groups">👥 Groups</button>
                    <button class="subtab-btn" data-subtab="subtab-classes">🏫 Classes</button>
                </div>

                <!-- Subtab: Students -->
                <div id="subtab-students" class="compose-subtab active">
                    <form id="sendFormStudents">
                        <input type="hidden" name="recipient_type" value="students">
                        <div class="form-group">
                            <label>Select Students</label>
                            <select name="user_ids[]" multiple id="studentSelect" style="height:150px; width:100%;">
                                <?php $students->data_seek(0); while($s = $students->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['fullname']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <small class="help-text">Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to select multiple students.</small>
                            <div id="selectedCount" style="margin-top:5px; font-weight:bold;"></div>
                        </div>
                        <div class="form-group">
                            <label>Message Template (Optional)</label>
                            <select id="templateSelect" style="width:100%;">
                                <option value="">-- Select a template --</option>
                                <?php $templates->data_seek(0); while($t = $templates->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea id="messageInput" name="message" rows="6" required placeholder="Type your message. Use {first_name}, {surname}, {title}, {student}, {fullname}, {class}, {route}, {group}, {date}, {time} to personalise."></textarea>
                        </div>
                        <button type="submit" class="btn">Send to Selected Students</button>
                    </form>
                </div>

                <!-- Subtab: Groups -->
                <div id="subtab-groups" class="compose-subtab">
                    <form id="sendFormGroups">
                        <input type="hidden" name="recipient_type" value="groups">
                        <div class="form-group">
                            <label>Select Groups</label>
                            <select name="group_ids[]" multiple id="groupSelect" style="height:150px; width:100%;">
                                <?php $groups->data_seek(0); while($g = $groups->fetch_assoc()): ?>
                                    <option value="<?= $g['id'] ?>"><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?> (<?= ucfirst($g['route']) ?>)</option>
                                <?php endwhile; ?>
                            </select>
                            <small class="help-text">Select one or more groups. All members will receive the message.</small>
                            <div id="groupSelectedCount" style="margin-top:5px; font-weight:bold;"></div>
                        </div>
                        <div class="form-group">
                            <label>Message Template (Optional)</label>
                            <select id="templateSelectGroup" style="width:100%;">
                                <option value="">-- Select a template --</option>
                                <?php $templates->data_seek(0); while($t = $templates->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea id="messageInputGroup" name="message" rows="6" required placeholder="Type your message."></textarea>
                        </div>
                        <button type="submit" class="btn">Send to Selected Groups</button>
                    </form>
                </div>

                <!-- Subtab: Classes -->
                <div id="subtab-classes" class="compose-subtab">
                    <form id="sendFormClasses">
                        <input type="hidden" name="recipient_type" value="classes">
                        <div class="form-group">
                            <label>Select Classes</label>
                            <select name="class_levels[]" multiple id="classSelect" style="height:80px; width:100%;">
                                <?php foreach ($class_levels as $class): ?>
                                    <option value="<?= $class ?>"><?= $class ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="help-text">Select one or more classes.</small>
                        </div>
                        <div class="form-group">
                            <label>Filter by Route (Optional)</label>
                            <select name="class_route" style="width:100%;">
                                <option value="">All Routes</option>
                                <option value="sciences">Sciences</option>
                                <option value="humanities">Humanities</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message Template (Optional)</label>
                            <select id="templateSelectClass" style="width:100%;">
                                <option value="">-- Select a template --</option>
                                <?php $templates->data_seek(0); while($t = $templates->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea id="messageInputClass" name="message" rows="6" required placeholder="Type your message."></textarea>
                        </div>
                        <button type="submit" class="btn">Send to Selected Classes</button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h4>📚 Manage Templates</h4>
                <p>Create, edit, or delete message templates in the dedicated template manager.</p>
                <a href="admin_message_templates.php" class="btn-secondary">Go to Template Manager →</a>
            </div>
        </div>

    </div>
    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>

    <div id="toast" class="toast"></div>

    <script>
        // --- Tab Switching ---
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabs = document.querySelectorAll('.tab');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        // --- Subtab Switching ---
        const subtabBtns = document.querySelectorAll('.subtab-btn');
        const subtabs = document.querySelectorAll('.compose-subtab');
        subtabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                subtabBtns.forEach(b => b.classList.remove('active'));
                subtabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.subtab).classList.add('active');
            });
        });

        // --- Student counter ---
        const studentSelect = document.getElementById('studentSelect');
        const selectedCount = document.getElementById('selectedCount');
        if (studentSelect) {
            studentSelect.addEventListener('change', function() {
                const count = this.selectedOptions.length;
                selectedCount.textContent = count + ' student(s) selected';
            });
        }

        // --- Group counter ---
        const groupSelect = document.getElementById('groupSelect');
        const groupSelectedCount = document.getElementById('groupSelectedCount');
        if (groupSelect) {
            groupSelect.addEventListener('change', function() {
                const count = this.selectedOptions.length;
                groupSelectedCount.textContent = count + ' group(s) selected';
            });
        }

        // --- Template pre-fill for Students ---
        const templateSelect = document.getElementById('templateSelect');
        const messageInput = document.getElementById('messageInput');
        if (templateSelect) {
            templateSelect.addEventListener('change', function() {
                const id = this.value;
                if (!id) { messageInput.value = ''; return; }
                fetch(`admin_message_templates.php?get_template=${id}`)
                    .then(res => res.text())
                    .then(text => { messageInput.value = text; })
                    .catch(err => console.error(err));
            });
        }

        // --- Template pre-fill for Groups ---
        const templateSelectGroup = document.getElementById('templateSelectGroup');
        const messageInputGroup = document.getElementById('messageInputGroup');
        if (templateSelectGroup) {
            templateSelectGroup.addEventListener('change', function() {
                const id = this.value;
                if (!id) { messageInputGroup.value = ''; return; }
                fetch(`admin_message_templates.php?get_template=${id}`)
                    .then(res => res.text())
                    .then(text => { messageInputGroup.value = text; })
                    .catch(err => console.error(err));
            });
        }

        // --- Template pre-fill for Classes ---
        const templateSelectClass = document.getElementById('templateSelectClass');
        const messageInputClass = document.getElementById('messageInputClass');
        if (templateSelectClass) {
            templateSelectClass.addEventListener('change', function() {
                const id = this.value;
                if (!id) { messageInputClass.value = ''; return; }
                fetch(`admin_message_templates.php?get_template=${id}`)
                    .then(res => res.text())
                    .then(text => { messageInputClass.value = text; })
                    .catch(err => console.error(err));
            });
        }

        // --- Send via AJAX ---
        document.querySelectorAll('#sendFormStudents, #sendFormGroups, #sendFormClasses').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', 'send_message');

                fetch(location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(`✅ Sent to ${data.sent} student(s). ${data.skipped > 0 ? data.skipped + ' duplicate(s) skipped.' : ''}`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('❌ ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => showToast('❌ Network error'));
            });
        });

        // --- Edit message ---
        function editMessage(id) {
            document.getElementById('msg-content-' + id).style.display = 'none';
            document.getElementById('msg-edit-' + id).style.display = 'block';
        }

        function cancelEdit(id) {
            document.getElementById('msg-content-' + id).style.display = 'block';
            document.getElementById('msg-edit-' + id).style.display = 'none';
        }

        function saveEdit(id) {
            const newMsg = document.getElementById('edit-text-' + id).value;
            fetch(location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=edit_message&id=${id}&message=${encodeURIComponent(newMsg)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('msg-content-' + id).innerHTML = newMsg.replace(/\n/g, '<br>');
                    cancelEdit(id);
                    showToast('✅ Message updated.');
                } else {
                    showToast('❌ Update failed.');
                }
            });
        }

        // --- Delete message ---
        function deleteMessage(id) {
            if (!confirm('Delete this message?')) return;
            fetch(location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=delete_message&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('msg-' + id).remove();
                    showToast('🗑️ Message deleted.');
                } else {
                    showToast('❌ Delete failed.');
                }
            });
        }

        // --- Toast ---
        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
    </script>
</body>
</html>