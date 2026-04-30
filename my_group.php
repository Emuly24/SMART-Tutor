<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

// Fetch group info
$group = $conn->query("SELECT g.id as group_id, g.group_number, g.class_level 
    FROM group_members gm 
    JOIN groups g ON gm.group_id = g.id 
    WHERE gm.user_id = $uid")->fetch_assoc();

$fellow_members = [];
if ($group) {
    $fellow = $conn->query("SELECT u.fullname, u.phone 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = {$group['group_id']} AND u.id != $uid");
    while ($f = $fellow->fetch_assoc()) $fellow_members[] = $f;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Group - SMART Tutor</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    
    <div class="card" style="margin: 20px;">
        <h3><i class="fas fa-users"></i> My Group: <?= htmlspecialchars($group['class_level'] ?? 'Not assigned') ?> – Group <?= $group['group_number'] ?? '—' ?></h3>
        <p><strong>Fellow members:</strong></p>
        <ul>
        <?php if ($group && count($fellow_members) > 0): ?>
            <?php foreach ($fellow_members as $f): ?>
                <li><?= htmlspecialchars($f['fullname']) ?> (<?= htmlspecialchars($f['phone']) ?>)</li>
            <?php endforeach; ?>
        <?php elseif ($group): ?>
            <li>You are the first member of this group.</li>
        <?php else: ?>
            <li>You are not assigned to any group yet. Please contact admin.</li>
        <?php endif; ?>
        </ul>
    </div>
    
    <div class="footer"><a href="dashboard.php" class="btn-back">← Back to Dashboard</a></div>
</div>
</body>
</html>