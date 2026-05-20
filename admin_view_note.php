<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

$admin_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_note'])) {
        header("Location: admin_note_editor.php?id=$note_id");
        exit;
    }
    if (isset($_POST['resend_notification'])) {
        $unlocked_groups = $conn->query("SELECT g.id FROM groups g JOIN group_content_locks l ON g.id = l.group_id WHERE l.content_type='note' AND l.content_id=$note_id AND l.is_locked=0");
        while ($ug = $unlocked_groups->fetch_assoc()) {
            $members = $conn->query("SELECT user_id FROM group_members WHERE group_id={$ug['id']}");
            while ($m = $members->fetch_assoc()) {
                $msg_text = "📘 A new note '{$note['title']}' has been unlocked for your group. Check it out!";
                $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$m['user_id']}, '$msg_text')");
            }
        }
        $admin_success = "Notification resent to all unlocked groups.";
    }
}
?>
<!DOCTYPE html>
<html><head><title><?=htmlspecialchars($note['title'])?> - Admin View</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<!-- ===== MathJax CONFIG ===== -->
<script>
MathJax = {
    tex: {
        inlineMath: [['$', '$'], ['\\(', '\\)']],
        displayMath: [['$$', '$$'], ['\\[', '\\]']]
    },
    svg: {
        fontCache: 'global'
    }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>

<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    
    .admin-note-container {
        max-width: 1000px;
        margin: 2rem auto;
        background: var(--card-bg);
        border-radius: 1rem;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
        border-top: 5px solid var(--accent);
        line-height: 1.8;
        font-size: 1.1rem;
        text-align: inherit; 
    }
    
    /* ---- Equation Box Styling ---- */
    .equation-box {
        display: block;
        width: 90%;
        max-width: 800px;
        margin: 1.5rem auto;
        padding: 1.5rem;
        background: #f8fafc;
        border: 2px solid #F1C40F;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        text-align: center;
        overflow-x: auto;
    }
    .equation-box .MathJax_Display {
        text-align: center !important;
        margin: 0 !important;
    }
    .equation-box mjx-container {
        display: block !important;
        margin: 0 auto !important;
        text-align: center !important;
    }

    .admin-note-container h1, .admin-note-container h2, .admin-note-container h3 {
        color: var(--accent);
    }
    .admin-note-container img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
    }
    .admin-note-container pre {
        background: var(--card-alt-bg);
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
    }
    .admin-note-container blockquote {
        border-left: 4px solid var(--accent);
        padding-left: 1rem;
        margin: 1rem 0;
        color: var(--text-muted);
    }
    .admin-note-container table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }
    .admin-note-container table th, .admin-note-container table td {
        border: 1px solid var(--card-alt-bg);
        padding: 0.5rem;
    }
    .admin-note-container table th {
        background: var(--accent);
        color: #1e293b;
    }
    .admin-note-container .mermaid {
        background: var(--card-alt-bg);
        padding: 1rem;
        border-radius: 0.5rem;
        margin: 1rem 0;
    }

    .MathJax_Display {
        text-align: inherit;
    }
</style>
</head><body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div class="flex-between" style="margin-bottom:1.5rem;">
        <h2><?=htmlspecialchars($note['title'])?></h2>
        <div>
            <?php if ($admin_success): ?>
                <div class="success" style="margin-bottom:0.5rem;"><?= htmlspecialchars($admin_success) ?></div>
            <?php endif; ?>
            <form method="post" style="display:inline-block;">
                <button type="submit" name="edit_note" class="btn">✏️ Edit</button>
            </form>
            <form method="post" style="display:inline-block;">
                <button type="submit" name="resend_notification" class="btn btn-secondary">📨 Resend</button>
            </form>
            <a href="admin_group_locks.php?content_type=note&content_id=<?= $note_id ?>&class_level=<?= $note['class_level'] ?>&route=sciences" class="btn btn-secondary">🔒 Locks</a>
        </div>
    </div>
    <div class="admin-note-container" id="note-content">
        <?=$note['content']?>
    </div>
</div>
<?php include_once 'includes/footer.php'; ?>
<script>mermaid.initialize({startOnLoad:true});</script>
<?php include_once 'includes/toc_navigator.php'; ?>

<!-- ===== FIX: Unescape LaTeX before rendering ===== -->
<script>
(function() {
    // Get the note container
    const container = document.getElementById('note-content');
    if (!container) return;

    // Get HTML content as string
    let html = container.innerHTML;

    // Fix common escaping issues from TinyMCE
    // Replace HTML entities for backslashes with actual backslashes
    html = html.replace(/&bsol;/g, '\\');
    html = html.replace(/&#92;/g, '\\');
    html = html.replace(/\\\\/g, '\\'); // Double backslashes to single

    // Ensure $$...$$ are preserved
    // Some editors convert $$ to &dollar;&dollar; or \$\$
    html = html.replace(/&dollar;/g, '$');
    html = html.replace(/\\\$/g, '$');

    // Update the container
    container.innerHTML = html;

    // Wait for MathJax to load then render
    if (window.MathJax) {
        MathJax.typesetPromise().then(() => {
            console.log('✅ MathJax render complete (after unescape)');
        }).catch(() => {});
    } else {
        // If MathJax not loaded yet, retry after a delay
        let attempts = 0;
        const checkMathJax = setInterval(() => {
            attempts++;
            if (window.MathJax) {
                clearInterval(checkMathJax);
                MathJax.typesetPromise().then(() => {
                    console.log('✅ MathJax render complete (delayed)');
                }).catch(() => {});
            } else if (attempts > 20) {
                clearInterval(checkMathJax);
                console.warn('MathJax did not load in time');
            }
        }, 500);
    }
})();
</script>
</body></html>