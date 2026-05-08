<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
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
$message = '';
$sms_link = '';
$phone_numbers_string = '';

// ---------- Group Meeting ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_group'])) {
    $group_id = (int)$_POST['group_id'];
    $notification_text = $_POST['group_message'];
    $members = $conn->query("SELECT u.id, u.fullname, u.phone FROM group_members gm JOIN users u ON gm.user_id=u.id WHERE gm.group_id = $group_id");
    $phones = [];
    while($m = $members->fetch_assoc()) {
        $phones[] = $m['phone'];
        $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$m['id']}, '$notification_text')");
    }
    $phone_numbers_string = implode(', ', $phones);
    $sms_numbers = implode(',', $phones);
    $encoded_message = urlencode($notification_text);
    $sms_link = "sms:$sms_numbers?body=$encoded_message";
    $message = "✅ In‑app notification sent to all group members. Use the SMS link below to send the same message via your phone.";
}

// ---------- Exercise Reminders ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    $template_id = (int)$_POST['template_id'];
    $student_ids = $_POST['student_ids'] ?? [];
    $note_id = (int)$_POST['note_id'];
    $note = $conn->query("SELECT title FROM notes WHERE id=$note_id")->fetch_assoc();
    $template = $conn->query("SELECT message FROM message_templates WHERE id=$template_id")->fetch_assoc();
    if ($template && $note) {
        foreach ($student_ids as $sid) {
            $student = $conn->query("SELECT fullname FROM users WHERE id=$sid")->fetch_assoc();
            $attempt = $conn->query("SELECT promised_at FROM exercise_attempts WHERE user_id=$sid AND exercise_id IN (SELECT id FROM note_exercises WHERE note_id=$note_id) LIMIT 1")->fetch_assoc();
            $hours = $attempt ? round((strtotime($attempt['promised_at'].' +24 hours') - time())/3600) : 24;
            $msg = str_replace(['{student}', '{note_title}', '{hours_remaining}'], [$student['fullname'], $note['title'], $hours], $template['message']);
            $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ($sid, '$msg')");
        }
        $message = "✅ Reminders sent to selected students.";
    }
}

// ---------- Individual Message ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_individual'])) {
    $user_id = (int)$_POST['user_id'];
    $ind_msg = $_POST['individual_message'];
    $stmt = $conn->prepare("INSERT INTO admin_messages (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $ind_msg);
    $stmt->execute();
    $message = "✅ Message sent to student.";
}

// Fetch data for forms
$groups = $conn->query("SELECT g.id, g.class_level, g.group_number FROM groups g ORDER BY g.class_level, g.group_number");
$notes_with_pending = $conn->query("SELECT DISTINCT n.id, n.title FROM notes n 
    JOIN note_exercises e ON n.id=e.note_id 
    JOIN exercise_attempts a ON e.id=a.exercise_id 
    WHERE a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 23 HOUR)");
$templates = $conn->query("SELECT id, title FROM message_templates");
$students = $conn->query("SELECT id, fullname, class_level FROM users WHERE approved=1 ORDER BY fullname");
$messages_history = $conn->query("SELECT m.*, u.fullname FROM admin_messages m JOIN users u ON m.user_id=u.id ORDER BY m.sent_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html><head><title>Admin Notifications Center</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>

<div class="container">
    
    
    <?php if ($message) echo "<div class='success'>$message</div>"; ?>

    <div class="tab">
        <button class="tablinks active" onclick="openTab(event, 'GroupMeeting')">📅 Group Meeting</button>
        <button class="tablinks" onclick="openTab(event, 'ExerciseReminders')">✍️ Exercise Reminders</button>
        <button class="tablinks" onclick="openTab(event, 'IndividualMessage')">💬 Individual Message</button>
    </div>

    <!-- Group Meeting Tab -->
    <div id="GroupMeeting" class="tabcontent" style="display: block;">
        <form method="post">
            <div class="form-group">
                <label>Select Group</label>
                <select name="group_id" required>
                    <option value="">-- Select --</option>
                    <?php while($g = $groups->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>"><?= $g['class_level'] ?> – Group <?= $g['group_number'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Meeting Message (use placeholders like {time}, {place}, {date})</label>
                <textarea name="group_message" rows="4" required placeholder="Example: Meeting tomorrow at {time} at {place}. Be punctual!"></textarea>
            </div>
            <button type="submit" name="send_group" class="btn">Send In‑App Notification</button>
        </form>
        <?php if ($sms_link): ?>
            <hr>
            <div class="card">
                <h3>Send SMS to all group members at once</h3>
                <p><strong>Phone numbers:</strong> <?= htmlspecialchars($phone_numbers_string) ?></p>
                <a href="<?= $sms_link ?>" class="btn" style="background:#27ae60;">📱 Send SMS via Phone</a>
                <p><small>Clicking this will open your phone's messaging app with all numbers and the message pre‑filled.</small></p>
                <button id="copyNumbersBtn" class="btn-secondary">Copy Phone Numbers</button>
            </div>
            <script>
                document.getElementById('copyNumbersBtn').onclick = function() {
                    let numbers = "<?= htmlspecialchars($phone_numbers_string) ?>";
                    navigator.clipboard.writeText(numbers).then(() => alert('Numbers copied!')).catch(() => alert('Could not copy.'));
                }
            </script>
        <?php endif; ?>
    </div>

    <!-- Exercise Reminders Tab -->
    <div id="ExerciseReminders" class="tabcontent" style="display: none;">
        <form method="post">
            <div class="form-group">
                <label>Select Note (with pending paper exercises)</label>
                <select name="note_id" required>
                    <option value="">-- Select --</option>
                    <?php while($n = $notes_with_pending->fetch_assoc()): ?>
                        <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Template</label>
                <select name="template_id" required>
                    <option value="">-- Select --</option>
                    <?php while($t = $templates->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div id="student-list" class="form-group">
                <div id="students-placeholder">Select a note first.</div>
            </div>
            <button type="submit" name="send_reminders" class="btn">Send Reminders</button>
        </form>
    </div>

    <!-- Individual Message Tab -->
    <div id="IndividualMessage" class="tabcontent" style="display: none;">
        <div class="grid">
            <div class="card">
                <h3>Send Message to Student</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="user_id" required>
                            <option value="">-- Select --</option>
                            <?php while($s = $students->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['fullname']) ?> (<?= $s['class_level'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="individual_message" rows="4" required></textarea>
                    </div>
                    <button type="submit" name="send_individual" class="btn">Send</button>
                </form>
            </div>
            <div class="card">
                <h3>Recent Messages</h3>
                <?php if ($messages_history->num_rows == 0): ?>
                    <p>No messages sent yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Message</th><th>Sent</th><th>Read</th></tr>
                        </thead>
                        <tbody>
                        <?php while($m = $messages_history->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['fullname']) ?></td>
                                <td><?= nl2br(htmlspecialchars($m['message'])) ?></td>
                                <td><?= $m['sent_at'] ?></td>
                                <td>
                                <?php if ($m['read_at']): ?>
                                    <span class="status-badge status-read">Read</span>
                                <?php else: ?>
                                    <span class="status-badge status-unread">Unread</span>
                                <?php endif; ?>
                            </td>
                                </tr>
                                                    <?php endwhile; ?>
                        </tbody>
                    </table>
                    <script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast toast-' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Trigger toast from PHP variables
<?php if ($success): ?>
    showToast("<?= htmlspecialchars($success) ?>", "success");
<?php elseif (!empty($error)): ?>
    showToast("<?= htmlspecialchars($error) ?>", "error");
<?php endif; ?>
</script>
<!-- Toast container -->
<div id="toast" class="toast"></div>
<!-- Toast container -->
<div id="toast" class="toast"></div>

<script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast toast-' + type; // apply variant
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Example: Copy numbers button
document.getElementById('copyNumbersBtn').onclick = function() {
    let numbers = "<?= htmlspecialchars($phone_numbers_string) ?>";
    navigator.clipboard.writeText(numbers).then(() => {
        showToast('Numbers copied!', 'success');
    }).catch(() => {
        showToast('Could not copy.', 'error');
    });
};
</script>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    // Load students when note is selected (for exercise reminders)
    const noteSelect = document.querySelector('#ExerciseReminders select[name="note_id"]');
    const studentPlaceholder = document.getElementById('students-placeholder');
    if (noteSelect) {
        noteSelect.addEventListener('change', function() {
            let noteId = this.value;
            if (!noteId) {
                studentPlaceholder.innerHTML = 'Select a note first.';
                return;
            }
            fetch(`admin_get_pending_students.php?note_id=${noteId}`)
                .then(response => response.text())
                .then(html => {
                    studentPlaceholder.innerHTML = html;
                });
        });
    }
</script>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>