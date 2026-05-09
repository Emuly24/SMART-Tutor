<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
require_once 'topics_data.php'; // Include the new shared file

$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level']; // This is "Form 3" or "Form 4"

$subjects = ['Mathematics', 'English', 'Biology', 'Physics', 'Chemistry'];

$error = '';
$success = '';

// Fetch existing topics for this user (to pre-fill)
$existing = [];
$res = $conn->query("SELECT subject, topic FROM topic_requests WHERE user_id = $uid");
while ($r = $res->fetch_assoc()) {
    $existing[$r['subject']][] = $r['topic'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_requests'])) {
        $subject = $_POST['subject'];
        $topics = isset($_POST['topics']) ? $_POST['topics'] : [];
        
        if (empty($topics)) {
            $error = "Please select at least one topic.";
        } else {
            $conn->query("DELETE FROM topic_requests WHERE user_id = $uid AND subject = '$subject'");
            $stmt = $conn->prepare("INSERT INTO topic_requests (user_id, subject, topic, class_level) VALUES (?, ?, ?, ?)");
            foreach ($topics as $topic) {
                if (!empty($topic)) {
                    $stmt->bind_param("isss", $uid, $subject, $topic, $class);
                    $stmt->execute();
                }
            }
            $success = count($topics) . " topic(s) requested for $subject. Admin will review them.";
            $existing[$subject] = $topics;
        }
    } elseif (isset($_POST['check_topic'])) {
        $subject = $_POST['subject'];
        $topic = trim($_POST['single_topic']);
        if (empty($topic)) {
            $error = "Enter a topic to check.";
        } else {
            $check = $conn->query("SELECT covered_date FROM topics_covered WHERE class_level='$class' AND subject='$subject' AND topic='$topic'");
            if ($check->num_rows) {
                $covered_date = $check->fetch_assoc()['covered_date'];
                $error = "⚠️ This topic was covered on $covered_date. You can still request it but priority may be lower.";
            } else {
                $error = "✅ This topic is new. You can request it by adding it to the list above.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Request Topic</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-lightbulb"></i> Request Topics to be Covered</h2>
            <p>Select a subject, then choose topics from the list below. You can select multiple topics.</p>
            <p><a href="covered_topics.php">📜 View already covered topics</a></p>
            <?php if ($error): ?>
                <div class="error"><?= nl2br(htmlspecialchars($error)) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post" id="topicForm">
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject" id="subjectSelect" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>" <?= (isset($_POST['subject']) && $_POST['subject'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="topicContainer">
                    <label>Select Topics (one or more)</label>
                    <div class="loading-message" style="padding: 20px; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin"></i> Loading topics...
                    </div>
                    <select name="topics[]" id="topicSelect" multiple style="height: 300px; width: 100%; display: none;">
                        <!-- Topics will be loaded via AJAX -->
                    </select>
                    <small class="help-text">Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to select multiple topics.</small>
                </div>
                <button type="submit" name="save_requests" class="btn">Submit Request</button>
            </form>
            
            <hr>
            <h3>Check if a topic has already been covered</h3>
            <form method="post" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <div style="flex:1;">
                    <label>Subject</label>
                    <select name="subject">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:2;">
                    <label>Topic</label>
                    <input type="text" name="single_topic" placeholder="e.g., Quadratic Equations">
                </div>
                <button type="submit" name="check_topic" class="btn-secondary">Check</button>
            </form>
        </div>
        <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
    <script>
        document.getElementById('subjectSelect').addEventListener('change', function() {
            const subject = this.value;
            const topicSelect = document.getElementById('topicSelect');
            const loadingMsg = document.querySelector('.loading-message');
            
            topicSelect.style.display = 'none';
            loadingMsg.style.display = 'block';
            loadingMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading topics...';
            topicSelect.innerHTML = '';
            
            if (!subject) {
                loadingMsg.style.display = 'none';
                return;
            }
            
            // Fetch topics based on subject and current class level (Form 3 or Form 4)
            const classLevel = '<?= $_SESSION['class_level'] ?>';
            fetch(`get_topics.php?subject=${encodeURIComponent(subject)}&class=${encodeURIComponent(classLevel)}`)
                .then(res => res.json())
                .then(data => {
                    loadingMsg.style.display = 'none';
                    topicSelect.style.display = 'block';
                    // Pre-select existing topics if any
                    const existingTopics = <?= json_encode($existing) ?>;
                    data.forEach(topic => {
                        const opt = document.createElement('option');
                        opt.value = topic;
                        opt.textContent = topic;
                        if (existingTopics[subject] && existingTopics[subject].includes(topic)) {
                            opt.selected = true;
                        }
                        topicSelect.appendChild(opt);
                    });
                })
                .catch(err => {
                    console.error(err);
                    loadingMsg.innerHTML = 'Error loading topics. Please refresh.';
                });
        });
        
        // Trigger initial load for selected subject
        const initialSubject = document.getElementById('subjectSelect').value;
        if (initialSubject) {
            document.getElementById('subjectSelect').dispatchEvent(new Event('change'));
        }
    </script>
</body></html>