<?php
require_once 'check_remember_me.php';

require_once 'config.php';
session_start();

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
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete dismissed students
    if (isset($_POST['delete_dismissed']) && !empty($_POST['dismissed_ids'])) {
        $ids = array_map('intval', $_POST['dismissed_ids']);
        $ids_str = implode(',', $ids);
        $conn->query("DELETE FROM users WHERE id IN ($ids_str) AND status = 'dismissed'");
        $msg = "Selected dismissed students permanently deleted.";
    }
    // Delete rejected applications
    elseif (isset($_POST['delete_rejected']) && !empty($_POST['rejected_ids'])) {
        $ids = array_map('intval', $_POST['rejected_ids']);
        $ids_str = implode(',', $ids);
        $conn->query("DELETE FROM applications WHERE id IN ($ids_str) AND status = 'rejected'");
        $msg = "Selected rejected applications deleted.";
    }
    // Restore rejected applications
    elseif (isset($_POST['restore_rejected']) && !empty($_POST['reject_restore_ids'])) {
        $ids = array_map('intval', $_POST['reject_restore_ids']);
        $ids_str = implode(',', $ids);
        $conn->query("UPDATE applications SET status = 'pending', admin_notes = CONCAT('Restored by admin. Previous note: ', admin_notes) WHERE id IN ($ids_str) AND status = 'rejected'");
        $msg = "Selected rejected applications restored to pending status.";
    }
    // Reactivate dismissed student
    elseif (isset($_POST['reactivate_student']) && !empty($_POST['reactivate_ids'])) {
        $ids = array_map('intval', $_POST['reactivate_ids']);
        $ids_str = implode(',', $ids);
        $conn->query("UPDATE users SET status = 'active' WHERE id IN ($ids_str) AND status = 'dismissed'");
        $msg = "Selected dismissed students reactivated. Please reassign them to groups if needed.";
    }
    if ($msg) header("Location: admin_purge_records.php?msg=" . urlencode($msg));
    exit;
}

$msg = $_GET['msg'] ?? '';

// Dismissed students
$dismissed = $conn->query("SELECT id, fullname, class_level, created_at FROM users WHERE status = 'dismissed' ORDER BY created_at DESC");
// Rejected applications
$rejected = $conn->query("SELECT a.id, a.user_id, u.fullname, u.class_level, a.admin_notes, a.submitted_at 
    FROM applications a JOIN users u ON a.user_id = u.id WHERE a.status = 'rejected' ORDER BY a.submitted_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Purge Records - History & Management</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Historical Records: Dismissed Students & Rejected Applications</h1>
    <?php if ($msg): ?>
        <div class="success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post" id="purgeForm">
        <!-- Dismissed Students Section -->
        <div class="card">
            <h2>Dismissed Students</h2>
            <?php if ($dismissed->num_rows == 0): ?>
                <p>No dismissed students.</p>
            <?php else: ?>
                <div>
                    <label><input type="checkbox" id="selectAllDismissed"> Select All</label>
                </div>
                <table class="data-table">
                    <thead><tr><th>Select</th><th>Name</th><th>Class</th><th>Dismissed On</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($s = $dismissed->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" name="dismissed_ids[]" value="<?= $s['id'] ?>" class="dismissed-checkbox"></td>
                            <td><?= htmlspecialchars($s['fullname']) ?></td>
                            <td><?= $s['class_level'] ?></td>
                            <td><?= $s['created_at'] ?></td>
                            <td>
                                <button type="submit" name="reactivate_student" value="1" formaction="?reactivate=<?= $s['id'] ?>" class="btn-success" onclick="this.form.reactivate_ids.value='<?= $s['id'] ?>'; return confirm('Reactivate this student? They will become active but will need to be reassigned to a group.')">Reactivate</button>
                                <input type="hidden" name="reactivate_ids" value="">
                             </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <button type="submit" name="delete_dismissed" class="btn-danger" onclick="return confirm('Permanently delete selected dismissed students? This removes all their data.')">Delete Selected Dismissed Students</button>
            <?php endif; ?>
        </div>

        <!-- Rejected Applications Section -->
        <div class="card">
            <h2>Rejected Applications</h2>
            <?php if ($rejected->num_rows == 0): ?>
                <p>No rejected applications.</p>
            <?php else: ?>
                <div>
                    <label><input type="checkbox" id="selectAllRejected"> Select All</label>
                </div>
                <table class="data-table">
                    <thead><tr><th>Select</th><th>Student</th><th>Class</th><th>Rejected On</th><th>Reason</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($r = $rejected->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" name="rejected_ids[]" value="<?= $r['id'] ?>" class="rejected-checkbox"></td>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= $r['class_level'] ?></td>
                            <td><?= $r['submitted_at'] ?></td>
                            <td><?= nl2br(htmlspecialchars($r['admin_notes'])) ?></td>
                            <td>
                                <button type="submit" name="restore_rejected" value="1" formaction="?restore=<?= $r['id'] ?>" class="btn-success" onclick="this.form.reject_restore_ids.value='<?= $r['id'] ?>'; return confirm('Restore this application to pending status?')">Restore</button>
                                <input type="hidden" name="reject_restore_ids" value="">
                             </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <button type="submit" name="delete_rejected" class="btn-warning" onclick="return confirm('Delete selected rejected application records? This only deletes the application, not the user account.')">Delete Selected Rejected Applications</button>
                <button type="submit" name="restore_rejected" class="btn-success" onclick="return confirm('Restore selected rejected applications to pending status?')">Restore Selected Rejected Applications</button>
            <?php endif; ?>
        </div>
    </form>

    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
<script>
    // Select All for Dismissed
    const selectAllDismissed = document.getElementById('selectAllDismissed');
    if (selectAllDismissed) {
        selectAllDismissed.addEventListener('change', function() {
            document.querySelectorAll('.dismissed-checkbox').forEach(cb => cb.checked = this.checked);
        });
    }
    // Select All for Rejected
    const selectAllRejected = document.getElementById('selectAllRejected');
    if (selectAllRejected) {
        selectAllRejected.addEventListener('change', function() {
            document.querySelectorAll('.rejected-checkbox').forEach(cb => cb.checked = this.checked);
        });
    }
    // Handle individual restore/reactivate buttons by setting hidden input values
    document.querySelectorAll('button[formaction]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const form = document.getElementById('purgeForm');
            const action = this.getAttribute('formaction');
            if (action.includes('restore')) {
                form.reject_restore_ids.value = this.closest('tr').querySelector('.rejected-checkbox').value;
            } else if (action.includes('reactivate')) {
                form.reactivate_ids.value = this.closest('tr').querySelector('.dismissed-checkbox').value;
            }
            form.action = action;
        });
    });
</script>
</body></html>