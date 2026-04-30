<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

// Fetch user data, application data, group info
$user = $conn->query("SELECT u.fullname, u.gender, u.dob, u.class_level, u.school, u.subjects, u.address, u.phone, u.parent_phone, u.email, u.profile_pic, u.created_at, 
    a.ambition, a.university, a.target_points, a.career_reason, a.subject_assist 
    FROM users u 
    LEFT JOIN applications a ON u.id = a.user_id 
    WHERE u.id = $uid")->fetch_assoc();

$group = $conn->query("SELECT g.group_number, g.class_level, g.id as group_id
    FROM group_members gm 
    JOIN groups g ON gm.group_id = g.id 
    WHERE gm.user_id = $uid")->fetch_assoc();

$group_members = [];
if ($group) {
    $members = $conn->query("SELECT u.fullname, u.phone 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = {$group['group_id']} AND u.id != $uid");
    while($m = $members->fetch_assoc()) $group_members[] = $m;
}

$error = $success = '';
$pic_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $phone = $_POST['phone'];
        $parent_phone = $_POST['parent_phone'];
        $email = $_POST['email'];
        $school = $_POST['school'];
        $password = $_POST['password'];
        
        $updates = [];
        $params = [];
        $types = "";
        if ($phone) { $updates[] = "phone=?"; $params[] = $phone; $types .= "s"; }
        if ($parent_phone) { $updates[] = "parent_phone=?"; $params[] = $parent_phone; $types .= "s"; }
        if ($email) { $updates[] = "email=?"; $params[] = $email; $types .= "s"; }
        if ($school) { $updates[] = "school=?"; $params[] = $school; $types .= "s"; }
        if (!empty($password) && strlen($password) >= 5) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updates[] = "password=?";
            $params[] = $hashed;
            $types .= "s";
        } elseif (!empty($password)) {
            $error = "Password must be at least 5 characters.";
        }
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $dir = 'uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed)) {
                $filename = "profile_{$uid}_" . time() . ".$ext";
                if ($user['profile_pic'] && file_exists($user['profile_pic'])) unlink($user['profile_pic']);
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dir . $filename)) {
                    $updates[] = "profile_pic=?";
                    $params[] = $dir . $filename;
                    $types .= "s";
                } else {
                    $pic_error = "Failed to upload image.";
                }
            } else {
                $pic_error = "Only JPG, PNG, GIF allowed.";
            }
        }
        
        if (!empty($updates)) {
            $params[] = $uid;
            $types .= "i";
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $success = "Profile updated.";
                // Refresh user data
                $user = $conn->query("SELECT u.fullname, u.gender, u.dob, u.class_level, u.school, u.subjects, u.address, u.phone, u.parent_phone, u.email, u.profile_pic, u.created_at, 
                    a.ambition, a.university, a.target_points, a.career_reason, a.subject_assist 
                    FROM users u 
                    LEFT JOIN applications a ON u.id = a.user_id 
                    WHERE u.id = $uid")->fetch_assoc();
            } else {
                $error = "Database error.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $hash = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc()['password'];
        if (!password_verify($old, $hash)) {
            $error = "Current password is incorrect.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } elseif (strlen($new) < 5) {
            $error = "Password must be at least 5 characters.";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$new_hash' WHERE id=$uid");
            $success = "Password changed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>My Profile</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>

<div class="container">
    
    
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($pic_error): ?><div class="error"><?= htmlspecialchars($pic_error) ?></div><?php endif; ?>

    <div class="profile-header">
        <?php if ($user['profile_pic'] && file_exists($user['profile_pic'])): ?>
            <img src="<?= $user['profile_pic'] ?>" class="profile-pic" alt="Profile Picture">
        <?php else: ?>
            <i class="fas fa-user-circle" style="font-size: 100px; color: var(--brown-dark);"></i>
        <?php endif; ?>
        <div class="profile-name">
            <h2><?= htmlspecialchars($user['fullname']) ?></h2>
            <p><?= htmlspecialchars($user['ambition'] ? "An aspiring " . $user['ambition'] . " aiming to study at " . $user['university'] : "No career goal set yet") ?></p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <h3>Personal Details</h3>
            <p><strong>Gender:</strong> <?= $user['gender'] ?></p>
            <p><strong>Date of Birth:</strong> <?= $user['dob'] ?></p>
            <p><strong>School:</strong> <?= htmlspecialchars($user['school']) ?></p>
            <p><strong>Class Level:</strong> <?= $user['class_level'] ?></p>
            <p><strong>Target Points:</strong> <?= $user['target_points'] ?></p>
            <p><strong>Membership Date:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?></p>
        </div>
        <div class="info-card">
            <h3>Subjects & Goals</h3>
            <p><strong>Subjects I am currently taking:</strong> <?= nl2br(htmlspecialchars($user['subjects'])) ?></p>
            <p><strong>Subjects I need assistance with:</strong> <?= nl2br(htmlspecialchars($user['subject_assist'])) ?></p>
            <p><strong>Career Reason:</strong> <?= nl2br(htmlspecialchars($user['career_reason'])) ?></p>
        </div>
        <div class="info-card">
            <h3>Contact</h3>
            <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?></p>
            <p><strong>Parent/Guardian Phone:</strong> <?= htmlspecialchars($user['parent_phone']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        </div>
        <div class="info-card">
            <h3>Group Information</h3>
            <p><strong>Group:</strong> <?= $group ? $group['class_level'] . ' – Group ' . $group['group_number'] : 'Not assigned yet' ?></p>
            <button id="showMembersBtn" class="btn">View Group Members</button>
            <div id="groupMembersList" style="display:none;" class="group-members-list">
                <?php if ($group && count($group_members) > 0): ?>
                    <ul><?php foreach($group_members as $m): ?><li><?= htmlspecialchars($m['fullname']) ?> (<?= $m['phone'] ?>)</li><?php endforeach; ?></ul>
                <?php elseif ($group): ?>
                    <p>You are the only member in this group (yet).</p>
                <?php else: ?>
                    <p>No group assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="info-card">
            <h3>Actions</h3>
            <button id="editProfileBtn" class="btn">Edit Profile</button>
            <button id="changePasswordBtn" class="btn">Change Password</button>
            
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Profile</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></div>
                <div class="form-group"><label>Parent/Guardian Phone</label><input type="tel" name="parent_phone" value="<?= htmlspecialchars($user['parent_phone']) ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"></div>
                <div class="form-group"><label>School</label><input type="text" name="school" value="<?= htmlspecialchars($user['school']) ?>"></div>
                <div class="form-group"><label>New Password (leave blank to keep)</label><input type="password" name="password"></div>
                <div class="form-group"><label>Profile Picture</label><input type="file" name="profile_pic" accept="image/*"></div>
                <button type="submit" name="update_profile">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Change Password</h3>
            <form method="post">
                <div class="form-group"><label>Current Password</label><input type="password" name="old_password" required></div>
                <div class="form-group"><label>New Password (min 5 chars)</label><input type="password" name="new_password" required></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
                <button type="submit" name="change_password">Update Password</button>
            </form>
        </div>
    </div>

    <div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
</div>
<script>
    var editModal = document.getElementById('editModal');
    var passModal = document.getElementById('passwordModal');
    var editBtn = document.getElementById('editProfileBtn');
    var passBtn = document.getElementById('changePasswordBtn');
    var showMembersBtn = document.getElementById('showMembersBtn');
    var membersList = document.getElementById('groupMembersList');
    var spans = document.getElementsByClassName('close');
    
    editBtn.onclick = function() { editModal.style.display = 'flex'; }
    passBtn.onclick = function() { passModal.style.display = 'flex'; }
    if (showMembersBtn) {
        showMembersBtn.onclick = function() {
            if (membersList.style.display === 'none') membersList.style.display = 'block';
            else membersList.style.display = 'none';
        }
    }
    for (var i = 0; i < spans.length; i++) {
        spans[i].onclick = function() {
            editModal.style.display = 'none';
            passModal.style.display = 'none';
        }
    }
    window.onclick = function(event) {
        if (event.target == editModal) editModal.style.display = 'none';
        if (event.target == passModal) passModal.style.display = 'none';
    }
</script>

</body>
</html>