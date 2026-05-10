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

if (isset($_GET['acknowledge'])) {
    $req_id = (int)$_GET['acknowledge'];
    $req = $conn->query("SELECT user_id, subject, topic FROM topic_requests WHERE id = $req_id")->fetch_assoc();
    if ($req) {
        $msg = "Thank you for your topic request for \"{$req['topic']}\" in {$req['subject']}. We have received it and will do our best to cover it in upcoming sessions. Keep up the hard work!";
        $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$req['user_id']}, '$msg')");
        // Optionally mark as acknowledged (add column `acknowledged` to topic_requests? Not necessary but can add.)
    }
    header("Location: admin_topic_requests.php");
    exit;
}

if (isset($_GET['mark_covered'])) {
    $subject = $_GET['subject'];
    $topic = $_GET['topic'];
    $class = $_GET['class'];
    $conn->query("INSERT INTO topics_covered (subject, topic, class_level, covered_date) VALUES ('$subject', '$topic', '$class', CURDATE())");
    // Also delete the request (or keep it as covered)
    $conn->query("DELETE FROM topic_requests WHERE subject = '$subject' AND topic = '$topic' AND class_level = '$class'");
    header("Location: admin_topic_requests.php");
    exit;
}

if (isset($_GET['clear_class'])) {
    $class = $_GET['clear_class'];
    $conn->query("DELETE FROM topic_requests WHERE class_level='$class'");
    header("Location: admin_topic_requests.php");
    exit;
}

// Fetch all requests with student details, grouped by class
$requests = $conn->query("SELECT tr.*, u.fullname, u.phone, u.email, u.route, 
    (SELECT group_number FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = u.id) as group_number
    FROM topic_requests tr 
    JOIN users u ON tr.user_id = u.id 
    ORDER BY tr.class_level, tr.subject, tr.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Topic Requests</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Student Topic Requests</h1>
        <div class="content-grid">
            <?php if ($requests->num_rows == 0): ?>
                <div class="card"><p>No topic requests yet.</p></div>
            <?php else: ?>
                <?php while($r = $requests->fetch_assoc()): 
                    $group_display = $r['group_number'] ? "Group {$r['group_number']} ({$r['route']})" : "No group";
                ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($r['fullname']) ?> (<?= $r['class_level'] ?>)</h3>
                        <p><strong>Subject:</strong> <?= htmlspecialchars($r['subject']) ?><br>
                        <strong>Topic:</strong> <?= nl2br(htmlspecialchars($r['topic'])) ?><br>
                        <strong>Route:</strong> <?= ucfirst($r['route'] ?? 'Not set') ?> | <strong>Group:</strong> <?= $group_display ?><br>
                        <strong>Contact:</strong> <?= htmlspecialchars($r['phone']) ?> <?= $r['email'] ? "($r[email])" : '' ?><br>
                        <strong>Requested on:</strong> <?= date('d M Y', strtotime($r['created_at'])) ?></p>
                        <div class="card-buttons">
                            <a href="?acknowledge=<?= $r['id'] ?>" class="btn">📨 Acknowledge & Thank</a>
                            <a href="?mark_covered=1&subject=<?= urlencode($r['subject']) ?>&topic=<?= urlencode($r['topic']) ?>&class=<?= $r['class_level'] ?>" onclick="return confirm('Mark this topic as covered? This will remove the request.')" class="btn-success">✓ Mark Covered</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
            <div class="card">
                <h3>Batch Actions</h3>
                <div class="card-buttons">
                    <a href="?clear_class=Form 3" onclick="return confirm('Delete ALL Form 3 requests?')" class="btn-danger">Clear Form 3</a>
                    <a href="?clear_class=Form 4" onclick="return confirm('Delete ALL Form 4 requests?')" class="btn-danger">Clear Form 4</a>
                </div>
            </div>
        </div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>