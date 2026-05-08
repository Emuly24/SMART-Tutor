<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level'];
$subjects = ['Mathematics', 'English', 'Chemistry', 'Physics', 'Biology'];

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
        $topics_text = trim($_POST['topics']);
        $topics = array_filter(array_map('trim', explode("\n", $topics_text)));
        
        if (empty($topics)) {
            $error = "Please enter at least one topic.";
        } else {
            // Delete old requests for this subject
            $conn->query("DELETE FROM topic_requests WHERE user_id = $uid AND subject = '$subject'");
            // Insert new topics
            $stmt = $conn->prepare("INSERT INTO topic_requests (user_id, subject, topic, class_level) VALUES (?, ?, ?, ?)");
            foreach ($topics as $topic) {
                if (!empty($topic)) {
                    $stmt->bind_param("isss", $uid, $subject, $topic, $class);
                    $stmt->execute();
                }
            }
            $success = count($topics) . " topic(s) requested for $subject. Admin will review them.";
            // Refresh existing array
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
            <p>Take your time – analyse your syllabus, identify topics you struggle with, and list them below (one per line). You can request multiple topics per subject. The admin will review and cover them in upcoming sessions.</p>
            <p><a href="covered_topics.php">📜 View already covered topics</a></p>
            <?php if ($error): ?>
                <div class="error"><?= nl2br(htmlspecialchars($error)) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject" id="subjectSelect" required>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>" <?= (isset($_POST['subject']) && $_POST['subject'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Your Topics (one per line)</label>
                    <textarea name="topics" rows="6" placeholder="e.g.&#10;Quadratic Equations&#10;Trigonometric Ratios&#10;Chemical Bonding"><?= isset($existing[$_POST['subject'] ?? 'Mathematics']) ? implode("\n", $existing[$_POST['subject'] ?? 'Mathematics']) : '' ?></textarea>
                    <small class="help-text">Enter each topic on a new line. You can request as many as you need.</small>
                </div>
                <button type="submit" name="save_requests" class="btn">Submit All Requests</button>
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
        // Optional: load existing topics when subject changes (via AJAX or just rely on POST)
        const subjectSelect = document.getElementById('subjectSelect');
        const topicsTextarea = document.querySelector('textarea[name="topics"]');
        // Pre-fill from PHP already, but if you want dynamic, you'd need an API.
    </script>
</body></html>