<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$student = $conn->query("SELECT fullname, class_level FROM users WHERE id=$uid")->fetch_assoc();
$existing = $conn->query("SELECT id, testimonial, rating, status FROM testimonials WHERE user_id=$uid ORDER BY id DESC LIMIT 1")->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testimonial = trim($_POST['testimonial']);
    $rating = (int)($_POST['rating'] ?? 5);
    if (empty($testimonial)) {
        $error = "Please write your testimonial.";
    } else {
        if ($existing) {
            $stmt = $conn->prepare("UPDATE testimonials SET testimonial=?, rating=?, status='pending', approved_at=NULL WHERE id=?");
            $stmt->bind_param("sii", $testimonial, $rating, $existing['id']);
            $stmt->execute();
            $success = "Your testimonial has been updated and submitted for re‑approval.";
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (user_id, fullname, class_level, testimonial, rating, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issis", $uid, $student['fullname'], $student['class_level'], $testimonial, $rating);
            $stmt->execute();
            $success = "Thank you! Your testimonial has been submitted and will appear after admin approval.";
        }
        $conn->query("UPDATE users SET testimonial_prompt_shown=0 WHERE id=$uid");
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $existing ? 'Edit' : 'Submit' ?> Testimonial – SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <div class="apply-container">
        <h2><?= $existing ? 'Update Your Testimonial' : 'Share Your Experience' ?></h2>
        <p>Tell us how SMART Tutor has helped you. Your testimonial may appear on our homepage to inspire other students.</p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <p><a href="dashboard.php">← Back to Dashboard</a></p>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label>Your testimonial *</label>
                    <textarea name="testimonial" rows="5" required><?= htmlspecialchars($existing['testimonial'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Rating (1–5 stars)</label>
                    <select name="rating">
                        <?php for($i=5;$i>=1;$i--): ?>
                            <option value="<?= $i ?>" <?= ($existing && $existing['rating']==$i) ? 'selected' : '' ?>><?= str_repeat('⭐', $i) ?> (<?= $i ?>/5)</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit"><?= $existing ? 'Update Testimonial' : 'Submit Testimonial' ?></button>
            </form>
            <p><a href="dashboard.php">← Back to Dashboard</a></p>
        <?php endif; ?>
    </div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
<script>
    const prefill = sessionStorage.getItem('prefill_testimonial');
    if (prefill) {
        const textarea = document.querySelector('textarea[name="testimonial"]');
        if (textarea && !textarea.value.trim()) {
            textarea.value = prefill;
        }
        sessionStorage.removeItem('prefill_testimonial');
    }
</script>
</body>
</html>