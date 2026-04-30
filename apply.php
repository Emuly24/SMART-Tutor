<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$user = $conn->query("SELECT approved, class_level, gender, school FROM users WHERE id=$uid")->fetch_assoc();
if ($user['approved']) {
    header("Location: dashboard.php");
    exit;
}

$application = $conn->query("SELECT * FROM applications WHERE user_id=$uid")->fetch_assoc();

$error = $success = '';
$universities = [
    "University of Malawi (UNIMA)",
    "Mzuzu University (MZUNI)",
    "Lilongwe University of Agriculture and Natural Resources (LUANAR)",
    "Malawi University of Business and Applied Sciences (MUBAS)",
    "Kamuzu University of Health Sciences (KUHeS)",
    "Malawi University of Science and Technology (MUST)",
    "DMI St. John the Baptist University",
    "Catholic University of Malawi"
];
$all_subjects = ['Mathematics', 'English', 'Chemistry', 'Physics', 'Biology', 'History', 'Agriculture'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_level = $_POST['class_level'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $school = trim($_POST['school'] ?? '');
    $subjects_assist = isset($_POST['subjects_assist']) ? implode(', ', $_POST['subjects_assist']) : '';
    $ambition = trim($_POST['ambition'] ?? '');
    $career_reason = trim($_POST['career_reason'] ?? '');
    $university = $_POST['university'] ?? '';
    $why_join = trim($_POST['why_join'] ?? '');
    $target_points = (int)($_POST['target_points'] ?? 0);
    
    if (empty($class_level) || empty($gender) || empty($school) || empty($subjects_assist) || empty($ambition) || empty($career_reason) || empty($university) || empty($why_join)) {
        $error = "Please fill all required fields.";
    } elseif ($target_points > 20) {
        $error = "🌟 Your target points ($target_points) are above 20. We believe you can aim for ≤20. Please adjust and resubmit.";
    } else {
        // Update users table (class_level, gender, school)
        $conn->query("UPDATE users SET class_level = '$class_level', gender = '$gender', school = '$school' WHERE id = $uid");
        
        // Update or insert application
        $seriousness = json_encode(['agree' => true]);
        if ($application) {
            $conn->query("UPDATE applications SET ambition='$ambition', career_reason='$career_reason', university='$university', why_join='$why_join', subject_assist='$subjects_assist', target_points=$target_points, seriousness_answers='$seriousness', status='pending' WHERE user_id=$uid");
        } else {
            $conn->query("INSERT INTO applications (user_id, ambition, career_reason, university, why_join, subject_assist, target_points, seriousness_answers) VALUES ($uid, '$ambition', '$career_reason', '$university', '$why_join', '$subjects_assist', $target_points, '$seriousness')");
        }
        $success = "Application submitted! Wait for admin approval.";
        @mail(ADMIN_EMAIL, "New Application", "User ID $uid submitted application.", "From:noreply@yoursite.com");
    }
}
?>
<!DOCTYPE html><html><head><title>Application Form</title><link rel="stylesheet" href="style.css"></head><body class="apply-page">
    <?php include_once 'includes/header.php'; ?>
    <div class="apply-container">
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?> <a href="dashboard.php">Back to Dashboard</a></div>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label>Which class are you currently in? *</label>
                    <select name="class_level" required>
                        <option value="">-- Select --</option>
                        <option value="Form 3" <?= (($user['class_level'] ?? '') == 'Form 3') ? 'selected' : '' ?>>Form 3</option>
                        <option value="Form 4" <?= (($user['class_level'] ?? '') == 'Form 4') ? 'selected' : '' ?>>Form 4</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" required>
                        <option value="">-- Select --</option>
                        <option value="Male" <?= (($user['gender'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (($user['gender'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current School (full name) *</label>
                    <input type="text" name="school" value="<?= htmlspecialchars($user['school'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Which subjects do you need assistance with? (select all that apply) *</label>
                    <div class="checkbox-group">
                        <?php $assist_subjects = explode(', ', $application['subject_assist'] ?? ''); ?>
                        <?php foreach ($all_subjects as $s): ?>
                            <label><input type="checkbox" name="subjects_assist[]" value="<?= $s ?>" <?= in_array($s, $assist_subjects) ? 'checked' : '' ?>> <?= $s ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>What career do you want to pursue? *</label>
                    <input type="text" name="ambition" value="<?= htmlspecialchars($application['ambition'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Why do you want that career? *</label>
                    <textarea name="career_reason" rows="3" required><?= htmlspecialchars($application['career_reason'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Which public university do you aim to join? *</label>
                    <select name="university" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($universities as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>" <?= (($application['university'] ?? '') == $u) ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Why do you want to join this group? *</label>
                    <textarea name="why_join" rows="3" required><?= htmlspecialchars($application['why_join'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>What is your target MSCE points (≤20)? *</label>
                    <input type="number" name="target_points" min="0" max="20" value="<?= htmlspecialchars($application['target_points'] ?? '') ?>" required>
                </div>

                <!-- Declaration -->
                <div class="declaration">
                    <p>By submitting this application, I confirm that all the information I have provided is true and complete. I understand that false or misleading information may result in rejection or dismissal from the group.</p>
                </div>

                <div class="submission-date">
                    <label>Date of Submission:</label>
                    <input type="date" value="<?= date('Y-m-d') ?>" readonly>
                </div>

                <button type="submit">Submit Application</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</body></html>