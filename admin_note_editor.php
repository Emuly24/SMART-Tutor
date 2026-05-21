<?php
require_once 'check_remember_me.php';
require_once 'config.php';

// ===== SECTION EXTRACTION FUNCTION =====
function extractSectionsFromHTML($html, $conn, $note_id) {
    $html = trim($html);
    preg_match_all('/<h[34][^>]*>.*?Exercise.*?<\/h[34]>/i', $html, $matches, PREG_OFFSET_CAPTURE);
    $headings = $matches[0];
    $sections = [];
    $lastPos = 0;
    
    foreach ($headings as $index => $heading) {
        $pos = $heading[1];
        if ($index == 0) {
            $introContent = substr($html, 0, $pos);
            if (trim($introContent)) {
                $sections[] = ['type' => 'introduction', 'content' => $introContent, 'exercise_id' => null];
            }
        }
        $nextPos = isset($headings[$index + 1]) ? $headings[$index + 1][1] : strlen($html);
        $exerciseContent = substr($html, $pos, $nextPos - $pos);
        $exerciseId = null;
        if (preg_match('/Exercise\s+(\d+)/i', $heading[0], $idMatch)) {
            $exerciseId = (int)$idMatch[1];
        }
        $sections[] = ['type' => 'exercise', 'content' => $exerciseContent, 'exercise_id' => $exerciseId];
        $lastPos = $nextPos;
    }
    
    if (empty($sections)) {
        $sections[] = ['type' => 'introduction', 'content' => $html, 'exercise_id' => null];
    }
    return $sections;
}

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
if (!$conn) die("Database connection failed.");

$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$existing_note = null;
if ($note_id > 0) {
    $result = $conn->query("SELECT * FROM notes WHERE id = $note_id");
    if ($result) $existing_note = $result->fetch_assoc();
}
$last_note_id = 0;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $class = trim($_POST['class_level']);
    $content = trim($_POST['content']);
    $note_id_post = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;
    
    if (empty($title) || empty($subject) || empty($content)) {
        die("Missing required fields.");
    }

    $title = $conn->real_escape_string($title);
    $subject = $conn->real_escape_string($subject);
    $class = $conn->real_escape_string($class);
    $content = $conn->real_escape_string($content);
    
    if ($note_id_post > 0) {
        $conn->query("UPDATE notes SET title='$title', subject='$subject', class_level='$class', content='$content', created_at=NOW() WHERE id=$note_id_post");
        $note_id = $note_id_post;
    } else {
        $conn->query("INSERT INTO notes (title, subject, class_level, content) VALUES ('$title', '$subject', '$class', '$content')");
        $note_id = $conn->insert_id;
    }
    $conn->query("DELETE FROM note_drafts");

    // ===== AUTO-EXTRACT SECTIONS =====
    $sections = extractSectionsFromHTML($content, $conn, $note_id);
    
    // Clear existing sections for this note
    $conn->query("DELETE FROM note_sections WHERE note_id = $note_id");
    
    // Insert new sections
    foreach ($sections as $index => $section) {
        $sort_order = $index + 1;
        $section_type = $section['type'];
        $section_content = $conn->real_escape_string($section['content']);
        $exercise_id = $section['exercise_id'] ? $section['exercise_id'] : 'NULL';
        
        // If it's an exercise, ensure a row in note_exercises
        if ($section['type'] == 'exercise' && $section['exercise_id']) {
            $checkEx = $conn->query("SELECT id FROM note_exercises WHERE note_id = $note_id AND sort_order = $sort_order");
            if ($checkEx->num_rows == 0) {
                $conn->query("INSERT INTO note_exercises (note_id, sort_order, question) VALUES ($note_id, $sort_order, 'Exercise $sort_order')");
            }
            $exRow = $conn->query("SELECT id FROM note_exercises WHERE note_id = $note_id AND sort_order = $sort_order")->fetch_assoc();
            $exercise_id = $exRow['id'];
        }
        
        $conn->query("INSERT INTO note_sections (note_id, sort_order, section_type, content, exercise_id) 
            VALUES ($note_id, $sort_order, '$section_type', '$section_content', " . ($exercise_id ? $exercise_id : 'NULL') . ")");
    }

    if ($group_id) {
        $all_groups = $conn->query("SELECT id FROM groups WHERE class_level = '$class'");
        while ($g = $all_groups->fetch_assoc()) {
            $lock = $g['id'] == $group_id ? 0 : 1;
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$g['id']}, 'note', $note_id, $lock)
                          ON DUPLICATE KEY UPDATE is_locked = $lock");
        }
        $msg = "Note saved and unlocked for the selected group.";
    } else {
        $msg = "Note saved. Use the lock manager below to control group access.";
    }

    if (isset($_POST['finish'])) {
        header("Location: admin_group_locks.php?content_type=note&content_id=$note_id&class_level=" . urlencode($class));
        exit;
    } elseif (isset($_POST['save_as'])) {
        $conn->query("INSERT INTO notes (title, subject, class_level, content) VALUES ('$title (Copy)', '$subject', '$class', '$content')");
        $new_id = $conn->insert_id;
        header("Location: admin_note_editor.php?id=$new_id");
        exit;
    } elseif (!isset($_POST['auto_save'])) {
        echo "<script>window.noteId = $note_id; alert('$msg');</script>";
    }
}
?>
<!DOCTYPE html>
<html><head><title>Note Editor</title>
<link rel="stylesheet" href="style.css">
<!-- TinyMCE -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
<!-- MathQuill -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.min.js"></script>
<!-- MathJax -->
<script>MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] }, svg: { fontCache: 'global' } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<!-- Mermaid -->
<script src="https://cdn.jsdelivr.net/npm/mermaid@11.6.0/dist/mermaid.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Highlight.js -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<!-- Cropper -->
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.css">
<style>
    #editor { width: 100%; height: 600px; display: block; }
    .sticky-toolbar-wrapper {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        padding: 8px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .toolbar-extras {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 8px;
        background: var(--card-alt-bg);
        padding: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .toolbar-extras.hidden { display: none; }
    .toggle-toolbar-container { margin-bottom: 5px; }
    .toggle-toolbar-btn {
        background: var(--accent);
        color: #1e293b;
        border: none;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-bottom: 5px;
        display: inline-block;
    }
    .toggle-toolbar-btn:hover { background: var(--accent-dark); transform: scale(1.02); }
    .toolbar-extras button, .toolbar-extras select {
        background: var(--accent);
        color: #1e293b;
        border: none;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-size: 14px;
    }
    .toolbar-extras button:hover, .toolbar-extras select:hover {
        background: var(--accent-dark);
        transform: scale(1.02);
    }
    .btn-save {
        background: #3b82f6;
        color: white;
    }
    .btn-save:hover {
        background: #2563eb;
    }
    #saveNotification.show {
        transform: translateY(0);
        opacity: 1;
    }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000; }
    .modal-content { background: var(--card-bg); padding: 2rem; border-radius: 1rem; max-width: 90%; width: 800px; max-height: 90%; overflow-y: auto; }
    .library-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 10px; max-height: 500px; overflow-y: auto; }
    .library-item { border: 1px solid #ddd; padding: 8px; border-radius: 8px; cursor: pointer; transition: 0.2s; text-align: center; }
    .library-item:hover { background: rgba(212,175,55,0.1); transform: scale(1.02); }
    .library-item img { max-width: 100%; max-height: 100px; }
    .math-preview { background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 4px; min-height: 50px; }
    .image-editor-container { display: flex; gap: 20px; flex-wrap: wrap; }
    .image-editor-container img { max-width: 400px; max-height: 400px; }
    .image-controls { flex: 1; display: flex; flex-direction: column; gap: 10px; }
    .image-controls label { font-weight: bold; }
    .image-controls input { width: 100%; }
    .citation-list { background: #f5f5f5; padding: 10px; border-radius: 8px; margin: 10px 0; max-height: 200px; overflow-y: auto; }
    .citation-item { padding: 5px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; }
    .group-selector { margin-bottom: 1rem; padding: 1rem; background: var(--card-alt-bg); border-radius: 0.75rem; }
    .group-selector select { margin-right: 10px; margin-bottom: 5px; }
    .lock-manager { margin-top: 2rem; padding: 1rem; background: var(--card-alt-bg); border-radius: 0.75rem; display: none; }
    .lock-manager table { width: 100%; }
    .lock-manager td, .lock-manager th { padding: 8px; }
    .lock-toggle { cursor: pointer; background: var(--accent); color: #1e293b; border: none; padding: 4px 12px; border-radius: 20px; }
    .lock-toggle.locked { background: var(--error); color: white; }
    .tox-tinymce { min-height: 600px !important; }
    .bottom-action-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: white;
        border-top: 1px solid #ddd;
        padding: 10px 20px;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        z-index: 1500;
        box-sizing: border-box;
    }
    .bottom-action-bar .btn { padding: 8px 24px; border: none; border-radius: 20px; font-weight: 600; cursor: pointer; }
    .bottom-action-bar .btn-finish { background: var(--success); color: white; }
    .bottom-action-bar .btn-finish:hover { background: #2e7d32; }
    body { padding-bottom: 80px; }
    #imageCropperModal .modal-content { max-width: 900px; }
    #imageCropperModal img { max-width: 100%; max-height: 500px; }
</style>
</head>
<body>
<div class="container">
<div style="padding: 2rem 2rem 6rem 2rem;">
    <form method="post" id="noteForm">
        <input type="hidden" name="note_id" value="<?= $note_id ?>">
        <div class="form-group"><label>Title</label><input type="text" id="noteTitle" name="title" value="<?= htmlspecialchars($existing_note['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Subject</label>
            <select name="subject" required>
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= htmlspecialchars($sub) ?>" <?= (($existing_note['subject'] ?? '') == $sub) ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Class</label>
            <select id="noteClass" name="class_level" required>
                <option value="Form 3" <?= (($existing_note['class_level'] ?? '') == 'Form 3') ? 'selected' : '' ?>>Form 3</option>
                <option value="Form 4" <?= (($existing_note['class_level'] ?? '') == 'Form 4') ? 'selected' : '' ?>>Form 4</option>
            </select>
        </div>
        
        <div class="group-selector">
            <h4>🎯 Assign to specific group (optional)</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select id="routeSelect" style="min-width: 120px;">
                    <option value="">-- Route --</option>
                    <option value="sciences">Sciences</option>
                    <option value="humanities">Humanities</option>
                </select>
                <select id="groupSelect" name="group_id" style="min-width: 150px;">
                    <option value="">-- Any group (use locks later) --</option>
                </select>
            </div>
            <small class="help-text">If you select a group, this note will be instantly unlocked for that group and locked for others.</small>
        </div>

        <div class="sticky-toolbar-wrapper">
            <div class="toggle-toolbar-container">
                <button type="button" id="toggleGoldBtn" class="toggle-toolbar-btn">🛠️ Toggle Tools</button>
            </div>
            <div id="goldToolbar" class="toolbar-extras hidden">
                <button type="button" id="symbolBtn">Ω Symbols</button>
                <button type="button" id="fileUploadBtn">📎 Attach File</button>
                <button type="button" id="citationBtn">📚 Cite</button>
                <button type="button" id="mathBtn">∫ Equation</button>
                <button type="button" id="mathquillBtn">🧮 MathQuill</button>
                <button type="button" id="exampleBtn">📘 Insert Example</button>
                <button type="button" id="exerciseBtn">✍️ Insert Exercise</button>
                <button type="button" id="chemistryBtn">🧪 Chemistry</button>
                <button type="button" id="diagramBtn">🔬 Diagrams</button>
                <button type="button" id="mediaBtn">🎬 Embed Media</button>
                <button type="button" id="researchPanelBtn">📖 Research</button>
                <button type="button" id="libraryEqBtn">📐 Eq Library</button>
                <button type="button" id="libraryDiagramBtn">🖼️ Diagram Library</button>
                <button type="button" id="footerBtn">📄 Insert Footer</button>
                <button type="button" id="referenceBtn">📚 Reference Manager</button>
                <button type="button" id="editDiagramBtn">✏️ Edit Diagram Image</button>
                <button type="button" id="templateBtn">🧩 Templates</button>
            </div>
        </div>

        <div class="form-group"><label>Content</label>
            <textarea id="editor" name="content" style="height: 600px;"></textarea>
        </div>
    </form>

    <div id="lockManager" class="lock-manager">
        <h3>🔒 Group Access Control for this Note</h3>
        <p>Toggle lock/unlock for each group. Locked = group cannot see the note. Unlocked = group can see the note.</p>
        <div id="lockManagerContent"></div>
    </div>
</div>

<div class="bottom-action-bar">
    <button class="btn btn-save" onclick="manualSave()">💾 Save</button>
    <button class="btn btn-finish" onclick="finishAction()">✅ Finish, Lock & Unlock</button>
</div>

<div class="footer" style="margin-bottom: 80px;"><a href="admin_notes_list.php" class="btn-back">← Back to Notes</a></div>
</div>

<!-- Saved notification toast -->
<div id="saveNotification" style="
    position: fixed;
    bottom: 100px;
    right: 20px;
    background: #22c55e;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s ease;
    z-index: 9999;
">
    ✅ Saved successfully
</div>

<!-- ========== ALL MODALS ========== -->

<div id="templateModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <span class="close">&times;</span>
        <h3>📚 Template Library</h3>
        <div style="max-height: 70vh; overflow-y: auto; padding: 10px;">
            <?php include 'complete_templates.html'; ?>
        </div>
    </div>
</div>

<div id="symbolModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h3>Insert Symbol</h3><div id="symbolList" style="display:flex;flex-wrap:wrap;gap:8px;max-height:300px;overflow-y:auto;"></div></div></div>

<div id="citationModal" class="modal"><div class="modal-content"><h3>Add Citation</h3><div class="form-group"><label>Author(s) (Last, First)</label><input type="text" id="apaAuthor"></div><div class="form-group"><label>Year</label><input type="text" id="apaYear"></div><div class="form-group"><label>Title</label><input type="text" id="apaTitle"></div><div class="form-group"><label>Source</label><input type="text" id="apaSource"></div><div class="form-group"><label>DOI (optional)</label><input type="text" id="apaDoi"></div><button id="addCitationBtn" class="btn">Add</button><button id="closeCitationBtn" class="btn-secondary">Cancel</button></div></div>

<div id="referenceModal" class="modal"><div class="modal-content"><h3>Reference List</h3><div id="referenceListContainer" class="citation-list"></div><button id="insertReferencesBtn" class="btn">Insert List</button><button id="closeReferenceBtn" class="btn-secondary">Close</button></div></div>

<div id="mathHelperModal" class="modal"><div class="modal-content"><h3>Equation Helper (LaTeX)</h3>
    <div class="form-group"><label>LaTeX</label><textarea id="latexHelperInput" rows="3" placeholder="e.g. N(t)=N_0 e^{kt}"></textarea></div>
    <div class="math-preview" id="mathHelperPreview"></div>
    <button id="insertHelperEquationBtn" class="btn">Insert</button>
    <button id="closeMathHelperBtn" class="btn-secondary">Cancel</button>
</div></div>

<div id="mathquillModal" class="modal"><div class="modal-content"><h3>MathQuill Equation Editor</h3>
    <div style="background:#f5f5f5; padding:20px; border-radius:8px; margin:15px 0; text-align:center;">
        <div id="mathquill-field" style="font-size:24px; min-height:60px; background:white; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
    </div>
    <p style="color:#666;">Type your equation. Use <code>^</code> for superscript, <code>_</code> for subscript, <code>\frac{}{}</code> for fractions.</p>
    <button id="insertMathquillBtn" class="btn">Insert Equation</button>
    <button id="closeMathquillBtn" class="btn-secondary">Cancel</button>
</div></div>

<div id="imageCropperModal" class="modal">
    <div class="modal-content">
        <h3>✂️ Crop Image</h3>
        <div style="max-height:70vh; overflow:hidden; margin:15px 0;">
            <img id="cropperImage" src="" style="max-width:100%; max-height:500px;">
        </div>
        <div style="display:flex; gap:15px; flex-wrap:wrap; margin:15px 0;">
            <button id="cropApplyBtn" class="btn" style="background:var(--success);color:white;">✅ Crop</button>
            <button id="rotateLeftBtn" class="btn" style="background:var(--accent);color:#1e293b;">↺ Rotate Left</button>
            <button id="rotateRightBtn" class="btn" style="background:var(--accent);color:#1e293b;">↻ Rotate Right</button>
            <button id="cropResetBtn" class="btn-secondary">Reset</button>
            <button id="cropCancelBtn" class="btn-secondary" style="background:var(--error);color:white;">Cancel</button>
        </div>
    </div>
</div>

<div id="diagramEditorModal" class="modal">
    <div class="modal-content"><h3>Edit Diagram</h3>
        <div class="image-editor-container">
            <div><img id="editorImage" src=""></div>
            <div class="image-controls">
                <label>Brightness</label><input type="range" id="brightness" min="-100" max="100" value="0">
                <label>Contrast</label><input type="range" id="contrast" min="-100" max="100" value="0">
                <label>Width (px)</label><input type="number" id="resizeWidth">
                <button id="applyImageChanges" class="btn">Apply</button>
                <button id="saveEditedImage" class="btn">Save</button>
            </div>
        </div>
        <button id="closeDiagramEditorBtn" class="btn-secondary">Cancel</button>
    </div>
</div>

<div id="mediaUploadModal" class="modal"><div class="modal-content"><h3>Upload Audio/Video</h3><input type="file" id="mediaFileInput" accept="audio/*,video/*"><button id="uploadMediaBtn" class="btn">Upload & Embed</button><button id="closeMediaUploadBtn" class="btn-secondary">Cancel</button></div></div>

<div id="eqLibraryModal" class="modal"><div class="modal-content"><h3>Equation Library</h3><div id="eqLibraryList" class="library-grid"></div><button id="closeEqLibBtn" class="btn-secondary">Close</button></div></div>

<div id="diagramLibraryModal" class="modal"><div class="modal-content"><h3>Diagram Library</h3><div id="diagramLibraryList" class="library-grid"></div><button id="closeDiagramLibBtn" class="btn-secondary">Close</button></div></div>

<div id="chemistryModal" class="modal"><div class="modal-content"><h3>Common Chemistry Equations</h3><select id="chemistrySelect" style="width:100%;padding:8px;margin-bottom:15px;"><option value="">-- Select --</option><option value="Photosynthesis: 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂">Photosynthesis</option><option value="Cellular Respiration: C₆H₁₂O₆ + 6O₂ → 6CO₂ + 6H₂O + ATP">Cellular Respiration</option><option value="Hydrochloric Acid: HCl + H₂O → H₃O⁺ + Cl⁻">Hydrochloric Acid</option><option value="Neutralisation: H⁺ + OH⁻ → H₂O">Neutralisation</option><option value="Electrolysis of Water: 2H₂O → 2H₂ + O₂">Electrolysis of Water</option></select><button id="insertChemistryBtn" class="btn">Insert</button><button id="closeChemistryBtn" class="btn-secondary">Cancel</button></div></div>

<div id="webResearchModal" class="modal"><div class="modal-content"><h3>Web Research</h3><div class="form-group"><label>URL</label><input type="text" id="researchUrl" value="https://scholar.google.com/"></div><button id="openBrowserBtn" class="btn">Open</button><div class="form-group"><label>Notes</label><textarea id="researchText" rows="6"></textarea></div><button id="insertResearchNoteBtn" class="btn">Insert Notes</button><button id="closeWebResearchBtn" class="btn-secondary">Cancel</button></div></div>

<div id="mediaModal" class="modal"><div class="modal-content"><h3>Embed Media (URL)</h3><div class="form-group"><label>Media URL</label><input type="text" id="mediaUrl"></div><button id="insertMediaBtn" class="btn">Embed</button><button id="closeMediaBtn" class="btn-secondary">Cancel</button></div></div>


<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // ---------- TOGGLE ----------
    const toggleBtn = document.getElementById('toggleGoldBtn');
    const goldToolbar = document.getElementById('goldToolbar');
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        goldToolbar.classList.toggle('hidden');
        toggleBtn.textContent = goldToolbar.classList.contains('hidden') ? '🛠️ Show Tools' : '🛠️ Hide Tools';
    });

    // ---------- SAVE NOTIFICATION ----------
    function showSavedNotification(message = '✅ Saved successfully') {
        const notif = document.getElementById('saveNotification');
        if (notif) {
            notif.textContent = message;
            notif.style.background = message.includes('❌') ? '#ef4444' : '#22c55e';
            notif.classList.add('show');
            setTimeout(() => {
                notif.classList.remove('show');
            }, 3000);
        }
    }

    // ---------- AUTO SAVE ----------
    function autoSaveToServer(editor) {
        if (!editor) return;
        const title = document.getElementById('noteTitle').value;
        const subject = document.querySelector('select[name="subject"]').value;
        const classLevel = document.querySelector('select[name="class_level"]').value;
        const content = editor.getData();
        const noteId = <?= $note_id ?>;

        const formData = new FormData();
        formData.append('title', title);
        formData.append('subject', subject);
        formData.append('class_level', classLevel);
        formData.append('content', content);
        formData.append('note_id', noteId);
        formData.append('auto_save', '1');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error ' + response.status);
            }
            return response.text();
        })
        .then(() => {
            showSavedNotification('✅ Saved successfully');
        })
        .catch(err => {
            console.error('Auto-save failed:', err);
            showSavedNotification('❌ Save failed! Check console for details.');
        });
    }

    // ---------- MANUAL SAVE ----------
    window.manualSave = function() {
        let attempts = 0;
        const checkEditor = setInterval(() => {
            attempts++;
            if (tinymce.activeEditor) {
                clearInterval(checkEditor);
                autoSaveToServer(tinymce.activeEditor);
            } else if (attempts > 30) {
                clearInterval(checkEditor);
                const title = document.getElementById('noteTitle').value;
                const subject = document.querySelector('select[name="subject"]').value;
                const classLevel = document.querySelector('select[name="class_level"]').value;
                const content = document.getElementById('editor').value;
                const noteId = <?= $note_id ?>;

                const formData = new FormData();
                formData.append('title', title);
                formData.append('subject', subject);
                formData.append('class_level', classLevel);
                formData.append('content', content);
                formData.append('note_id', noteId);
                formData.append('auto_save', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server error ' + response.status);
                    }
                    return response.text();
                })
                .then(() => {
                    showSavedNotification('✅ Saved successfully (fallback)');
                })
                .catch(err => {
                    console.error('Auto-save failed:', err);
                    showSavedNotification('❌ Save failed! Please try again.');
                });
            }
        }, 100);
    };

    // ---------- FINISH ACTION ----------
    window.finishAction = function() {
        const form = document.getElementById('noteForm');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'finish';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };

    // ---------- TINYMCE ----------
    tinymce.init({
        selector: '#editor',
        height: 600,
        menubar: true,
        plugins: 'anchor autolink charmap codesample emoticons image imagetools link lists media searchreplace table visualblocks wordcount code',
        toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | charmap | code | editimage',
        toolbar_sticky: true,
        menubar: 'file edit view insert format tools table',
        content_style: 'body { font-family: Inter, sans-serif; }',
        forced_root_block: false,
        valid_elements: '*[*]',
        extended_valid_elements: 'script[type|src|async],style[type]',
        sanitize: false,
        allow_script_urls: true,
        images_upload_url: 'note_editor_api.php?action=upload_image',
        automatic_uploads: true,
        image_advtab: true,
        image_dimensions: true,
        image_caption: true,
        init_instance_callback: function(editor) {
            document.getElementById('editor').style.display = 'none';
        },
        setup: function(editor) {
            const existingContent = <?= json_encode($existing_note['content'] ?? '') ?>;
            if (existingContent) {
                editor.on('init', function() {
                    editor.setContent(existingContent);
                });
            }

            setInterval(() => autoSaveToServer(editor), 30000);
            editor.addShortcut('Ctrl+S', 'Auto Save', () => autoSaveToServer(editor));

            editor.ui.registry.addMenuItem('customSave', {
                text: 'Save (Auto)',
                icon: 'save',
                onAction: () => autoSaveToServer(editor)
            });
            editor.ui.registry.addMenuItem('customSaveAs', {
                text: 'Save As...',
                icon: 'newdocument',
                onAction: function() {
                    const form = document.getElementById('noteForm');
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'save_as';
                    hidden.value = '1';
                    form.appendChild(hidden);
                    form.submit();
                }
            });
            editor.on('init', function() {
                editor.menu.add('file', {
                    title: 'File',
                    items: 'newdocument | customSave customSaveAs | print'
                });
            });

            editor.on('init', function() {
                const content = editor.getContent();
                if (content && window.MathJax) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    MathJax.typesetPromise([tempDiv]).then(() => {
                        editor.setContent(tempDiv.innerHTML);
                    }).catch(() => {});
                }
                if (window.mermaid) mermaid.run({ nodes: document.querySelectorAll('.mermaid') });
                if (window.hljs) hljs.highlightAll();
            });
            editor.on('SetContent', function() {
                if (window.hljs) setTimeout(hljs.highlightAll, 100);
            });
        }
    });

    // ---------- IMAGE CROPPER ----------
    let cropper = null;
    let currentImageElement = null;
    const cropperModal = document.getElementById('imageCropperModal');
    const cropperImg = document.getElementById('cropperImage');
    
    function showImageCropper(src, imgElement) {
        currentImageElement = imgElement;
        cropperImg.src = src;
        cropperModal.style.display = 'flex';
        setTimeout(() => {
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropperImg, {
                aspectRatio: NaN,
                viewMode: 1,
                autoCropArea: 0.8,
            });
        }, 300);
    }
    
    document.getElementById('cropApplyBtn').onclick = function() {
        if (cropper && currentImageElement && tinymce.activeEditor) {
            const canvas = cropper.getCroppedCanvas({
                maxWidth: 800,
                maxHeight: 800,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            canvas.toBlob(function(blob) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const newSrc = e.target.result;
                    tinymce.activeEditor.dom.setAttrib(currentImageElement, 'src', newSrc);
                    cropperModal.style.display = 'none';
                    if (cropper) cropper.destroy();
                    cropper = null;
                    currentImageElement = null;
                };
                reader.readAsDataURL(blob);
            });
        }
    };
    document.getElementById('rotateLeftBtn').onclick = function() {
        if (cropper) cropper.rotate(-90);
    };
    document.getElementById('rotateRightBtn').onclick = function() {
        if (cropper) cropper.rotate(90);
    };
    document.getElementById('cropResetBtn').onclick = function() {
        if (cropper) cropper.reset();
    };
    document.getElementById('cropCancelBtn').onclick = function() {
        cropperModal.style.display = 'none';
        if (cropper) cropper.destroy();
        cropper = null;
        currentImageElement = null;
    };

    // ---------- GROUP ----------
    const classSelect = document.getElementById('noteClass');
    const routeSelect = document.getElementById('routeSelect');
    const groupSelect = document.getElementById('groupSelect');
    function loadGroups() {
        const classLevel = classSelect.value;
        const route = routeSelect.value;
        if (!classLevel || !route) {
            groupSelect.innerHTML = '<option value="">-- Select route and class first --</option>';
            return;
        }
        fetch(`admin_get_groups.php?class=${encodeURIComponent(classLevel)}&route=${encodeURIComponent(route)}`)
            .then(res => res.json())
            .then(data => {
                groupSelect.innerHTML = '<option value="">-- Any group (use locks later) --</option>';
                data.forEach(group => {
                    groupSelect.innerHTML += `<option value="${group.id}">Group ${group.group_number} (${group.current_members}/5 members)</option>`;
                });
            })
            .catch(err => {
                console.error(err);
                groupSelect.innerHTML = '<option value="">Error loading groups</option>';
            });
    }
    classSelect.addEventListener('change', loadGroups);
    routeSelect.addEventListener('change', loadGroups);

    // ---------- LOCK ----------
    let currentNoteId = <?= $note_id > 0 ? $note_id : 0 ?>;
    const lockManagerDiv = document.getElementById('lockManager');
    const lockManagerContent = document.getElementById('lockManagerContent');
    function loadLockManager(noteId) {
        if (!noteId) {
            lockManagerDiv.style.display = 'none';
            return;
        }
        fetch(`admin_note_lock_api.php?action=get_locks&note_id=${noteId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.locks) {
                    let html = '<table class="data-table"><thead><tr><th>Route</th><th>Group</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                    data.locks.forEach(lock => {
                        const statusText = lock.is_locked ? '🔒 Locked' : '🔓 Unlocked';
                        const btnText = lock.is_locked ? 'Unlock' : 'Lock';
                        html += `<tr>
                                    <td>${lock.route === 'sciences' ? 'Sciences' : 'Humanities'}</td>
                                    <td>Group ${lock.group_number}</td>
                                    <td id="status-${lock.group_id}">${statusText}</td>
                                    <td><button class="lock-toggle ${lock.is_locked ? 'locked' : ''}" data-note="${noteId}" data-group="${lock.group_id}">${btnText}</button></td>
                                 </tr>`;
                    });
                    html += '</tbody></table>';
                    lockManagerContent.innerHTML = html;
                    lockManagerDiv.style.display = 'block';
                    document.querySelectorAll('.lock-toggle').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const note = this.dataset.note;
                            const group = this.dataset.group;
                            fetch(`admin_note_lock_api.php?action=toggle_lock&note_id=${note}&group_id=${group}`)
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success) {
                                        const statusSpan = document.getElementById(`status-${group}`);
                                        const isLocked = res.is_locked;
                                        statusSpan.innerHTML = isLocked ? '🔒 Locked' : '🔓 Unlocked';
                                        this.innerHTML = isLocked ? 'Unlock' : 'Lock';
                                        this.classList.toggle('locked', isLocked);
                                    } else {
                                        alert('Error toggling lock');
                                    }
                                });
                        });
                    });
                } else {
                    lockManagerDiv.style.display = 'none';
                }
            });
    }
    if (currentNoteId) loadLockManager(currentNoteId);

    // ---------- SYMBOL ----------
    const symbolPalette = {
        "📐 Math Symbols": ["+","−","×","÷","±","∓","∗","∙","⋅","∘","√","∛","∜","∞","≈","≅","≠","≤","≥","≡","≢","≪","≫","∑","∏","∫","∮","∯","∂","∇","∅","∎","□","▭","▱","◻","◼","◯"],
        "📊 Set Theory": ["∈","∉","∋","∌","⊂","⊃","⊆","⊇","∪","∩","∖","∨","∧","⊕","⊖","⊗","⊘","⊙","⊚","⊛","⊞","⊟","⊠","⊡","⋂","⋃","⋀","⋁"],
        "📈 Calculus": ["∂","∇","∫","∮","∯","∰","∱","∲","∳","lim","∑","∏","∐","Δ","δ","ε","λ","μ","ν","ξ","π","ρ","τ","φ","ψ","ω"],
        "🔺 Geometry & Trig": ["∠","∡","∢","⊥","∥","∦","△","▽","◿","◺","°","′","″","∇","·"],
        "🔬 Physics": ["ℏ","ħ","α","β","γ","Γ","Δ","δ","ε","ζ","η","θ","Θ","ι","κ","λ","μ","ν","ξ","π","ρ","σ","τ","υ","φ","Φ","χ","ψ","Ω","∇","∂","∫","∮","∞","⊕","⊗"],
        "🧪 Chemistry": ["⇌","⇄","→","←","↔","↑","↓","●","○","□","■","△","▲","▼","◆","◇","♢","♠","♥","♣","♦","⚗","⚛","☢","☣","⚡"],
        "🧬 Biology/Life Sciences": ["⚕","⚗","⚘","⚙","⚚","⊕","⊖","⊘","⊗","⊛","⊞","⊟","⊠","⊡","⋂","⋃","⋀","⋁","⁺","⁻","⁰","¹","²","³","⁴","⁵","⁶","⁷","⁸","⁹","₁","₂","₃","₄","₅","₆","₇","₈","₉"],
        "📈 Statistics": ["∑","∏","Δ","μ","σ","X̄","x̄","ȳ","P̄","p̄","n","N","k","c","p","q","±","≈","≠","≤","≥","∞","∇"],
        "💻 Computer Science": ["λ","μ","π","σ","τ","ε","η","ζ","θ","ι","κ","ν","ξ","ρ","χ","ψ","ω","Φ","∇","∂","∫","∮","∞","⊕","∘","·","×","≠","≤","≥","≡","≢"],
        "🔄 Arrows": ["←","↑","→","↓","↖","↗","↘","↙","↔","↕","↩","↪","↫","↬","↭","↮","↰","↱","↲","↳","↴","↵","↶","↷","↸","↹","↺","↻","⇐","⇑","⇒","⇓","⇔","⇕","⇖","⇗","⇘","⇙","⇚","⇛"],
        "🧮 Greek Letters (Symbols)": ["α","β","γ","δ","ε","ζ","η","θ","ι","κ","λ","μ","ν","ξ","ο","π","ρ","σ","τ","υ","φ","χ","ψ","ω","Α","Β","Γ","Δ","Ε","Ζ","Η","Θ","Ι","Κ","Λ","Μ","Ν","Ξ","Ο","Π","Ρ","Σ","Τ","Υ","Φ","Χ","Ψ","Ω"],
        "✨ Punctuation & Special": ["•","·","…","—","–","™","®","©","℗","∅","⊘","⌀","⌂","⌚","⌛","⏰","⏱","⏲","⌨","✉","✍","✎","✏","✐","✑","✒","⚒","⚔","⚖","⚗","⚙","⚛","⚜","⛰","⛪","⛲","⛳","⛵","⛺","⛽","✈","⚓","⛴","⛵","✌","✋","✊","👊","✌","🍀","☘","🌿","🌱","🌿"],
        "💰 Currency": ["$","€","£","¥","₱","₹","₽","₩","₪","₫","₦","₨","₸","₺","₮","₲","₴","₵","₧","₣","₡","₭","₼","₾","₽","₪","₩","¥"]
    };
    const symbolModal = document.getElementById('symbolModal');
    const symbolBtn = document.getElementById('symbolBtn');
    const symbolList = document.getElementById('symbolList');

    if (symbolModal && symbolBtn && symbolList) {
        for (const [cat, syms] of Object.entries(symbolPalette)) {
            const header = document.createElement('div');
            header.innerHTML = `<strong style="display:block; margin-top:10px;">${cat}</strong><hr>`;
            symbolList.appendChild(header);
            syms.forEach(sym => {
                const btn = document.createElement('button');
                btn.textContent = sym;
                btn.style.margin = '4px';
                btn.style.padding = '6px 12px';
                btn.style.borderRadius = '12px';
                btn.style.border = '1px solid #ccc';
                btn.style.cursor = 'pointer';
                btn.style.background = 'var(--card-alt-bg)';
                btn.style.color = 'var(--text-color)';
                btn.onclick = () => {
                    insertText(sym);
                    symbolModal.style.display = 'none';
                };
                symbolList.appendChild(btn);
            });
        }

        symbolBtn.onclick = function() {
            symbolModal.style.display = 'flex';
        };
        const closeSymbol = symbolModal.querySelector('.close');
        if (closeSymbol) {
            closeSymbol.onclick = function() {
                symbolModal.style.display = 'none';
            };
        }
        window.addEventListener('click', function(event) {
            if (event.target === symbolModal) {
                symbolModal.style.display = 'none';
            }
        });
    }

    // ---------- FILE UPLOAD ----------
    document.getElementById('fileUploadBtn').onclick = function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (!file || !tinymce.activeEditor) return;
            const fd = new FormData();
            fd.append('file', file);
            fetch('note_editor_api.php?action=upload_attachment', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.url) {
                        tinymce.activeEditor.insertContent(`<a href="${data.url}">${file.name}</a>`);
                    } else {
                        alert('Upload failed');
                    }
                });
        };
        input.click();
    };

    // ---------- CITATION ----------
    const citationBtn = document.getElementById('citationBtn');
    const citationModal = document.getElementById('citationModal');
    const closeCitationBtn = document.getElementById('closeCitationBtn');
    const addCitationBtn = document.getElementById('addCitationBtn');

    citationBtn.onclick = function() {
        citationModal.style.display = 'flex';
    };
    closeCitationBtn.onclick = function() {
        citationModal.style.display = 'none';
    };
    addCitationBtn.onclick = function() {
        const author = document.getElementById('apaAuthor').value;
        const year = document.getElementById('apaYear').value;
        const title = document.getElementById('apaTitle').value;
        const source = document.getElementById('apaSource').value;
        const doi = document.getElementById('apaDoi').value;
        if (!author || !year || !title || !source) {
            alert("Please fill author, year, title, source.");
            return;
        }
        citationModal.style.display = 'none';
        document.getElementById('apaAuthor').value = '';
        document.getElementById('apaYear').value = '';
        document.getElementById('apaTitle').value = '';
        document.getElementById('apaSource').value = '';
        document.getElementById('apaDoi').value = '';
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(`${author} (${year}). ${title}. ${source}${doi ? ' ' + doi : ''}`);
        }
    };

    // ---------- LATEX ----------
    const mathBtn = document.getElementById('mathBtn');
    const mathHelperModal = document.getElementById('mathHelperModal');
    const closeMathHelperBtn = document.getElementById('closeMathHelperBtn');
    const insertHelperEquationBtn = document.getElementById('insertHelperEquationBtn');
    const latexHelperInput = document.getElementById('latexHelperInput');
    const mathHelperPreview = document.getElementById('mathHelperPreview');

    mathBtn.onclick = function() {
        mathHelperModal.style.display = 'flex';
    };
    closeMathHelperBtn.onclick = function() {
        mathHelperModal.style.display = 'none';
    };

    let debounceTimer;
    latexHelperInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (mathHelperPreview) {
                const rawLatex = latexHelperInput.value.trim();
                if (rawLatex.length > 0) {
                    mathHelperPreview.innerHTML = `\\[ ${rawLatex} \\]`;
                    if (window.MathJax) {
                        MathJax.typesetPromise([mathHelperPreview])
                            .catch((err) => {
                                console.log("MathJax Error:", err);
                                const msg = "✏️ Incomplete LaTeX (keep typing!)";
                                mathHelperPreview.innerHTML = `<span style='color:#d97706; font-weight:bold;'>${msg}</span>`;
                            });
                    }
                } else {
                    mathHelperPreview.innerHTML = '';
                }
            }
        }, 400);
    });

    insertHelperEquationBtn.onclick = function() {
        const latex = latexHelperInput?.value.trim();
        if (latex && tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('$$ ' + latex + ' $$');
            mathHelperModal.style.display = 'none';
            latexHelperInput.value = '';
            if (mathHelperPreview) {
                mathHelperPreview.innerHTML = '';
            }
        }
    };

    // ---------- MATHQUILL ----------
    const mathquillBtn = document.getElementById('mathquillBtn');
    const mathquillModal = document.getElementById('mathquillModal');
    const closeMathquillBtn = document.getElementById('closeMathquillBtn');
    const insertMathquillBtn = document.getElementById('insertMathquillBtn');
    const mathquillField = document.getElementById('mathquill-field');

    let mqField = null;
    mathquillBtn.onclick = function() {
        mathquillModal.style.display = 'flex';
        setTimeout(() => {
            if (mathquillField) {
                mathquillField.innerHTML = '';
                const MQ = MathQuill.getInterface(2);
                mqField = MQ.MathField(mathquillField, {
                    spaceBehavesLikeTab: true,
                });
                mqField.focus();
            }
        }, 300);
    };
    closeMathquillBtn.onclick = function() {
        mathquillModal.style.display = 'none';
        if (mqField) mqField = null;
    };
    insertMathquillBtn.onclick = function() {
        if (mqField && tinymce.activeEditor) {
            const latex = mqField.latex();
            if (latex.trim()) {
                tinymce.activeEditor.insertContent('$$ ' + latex + ' $$');
            }
            mathquillModal.style.display = 'none';
            mqField = null;
            mathquillField.innerHTML = '';
        }
    };

    // ---------- TEMPLATES ----------
    document.getElementById('templateBtn').onclick = function() {
        document.getElementById('templateModal').style.display = 'flex';
    };
    const closeTemplate = document.querySelector('#templateModal .close');
    if (closeTemplate) {
        closeTemplate.onclick = function() {
            document.getElementById('templateModal').style.display = 'none';
        };
    }

    // ---------- CHEMISTRY ----------
    document.getElementById('chemistryBtn').onclick = function() {
        document.getElementById('chemistryModal').style.display = 'flex';
    };
    document.getElementById('closeChemistryBtn').onclick = function() {
        document.getElementById('chemistryModal').style.display = 'none';
    };
    document.getElementById('insertChemistryBtn').onclick = function() {
        const val = document.getElementById('chemistrySelect').value;
        if (val && tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(val);
            document.getElementById('chemistryModal').style.display = 'none';
        }
    };

    // ---------- DIAGRAM ----------
    document.getElementById('diagramBtn').onclick = function() {
        alert('Diagram feature coming soon - upload your diagram image via Insert > Image or Attach File');
    };

    // ---------- MEDIA ----------
    document.getElementById('mediaBtn').onclick = function() {
        document.getElementById('mediaModal').style.display = 'flex';
    };
    document.getElementById('closeMediaBtn').onclick = function() {
        document.getElementById('mediaModal').style.display = 'none';
    };
    document.getElementById('insertMediaBtn').onclick = function() {
        const url = document.getElementById('mediaUrl').value;
        if (url && tinymce.activeEditor) {
            const embedHtml = `<iframe src="${url}" width="560" height="315" frameborder="0" allowfullscreen></iframe>`;
            tinymce.activeEditor.insertContent(embedHtml);
            document.getElementById('mediaModal').style.display = 'none';
            document.getElementById('mediaUrl').value = '';
        }
    };

    // ---------- RESEARCH ----------
    document.getElementById('researchPanelBtn').onclick = function() {
        document.getElementById('webResearchModal').style.display = 'flex';
    };
    document.getElementById('closeWebResearchBtn').onclick = function() {
        document.getElementById('webResearchModal').style.display = 'none';
    };
    document.getElementById('insertResearchNoteBtn').onclick = function() {
        const notes = document.getElementById('researchText').value;
        if (notes && tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(`<p><strong>Research Note:</strong><br>${notes}</p>`);
            document.getElementById('webResearchModal').style.display = 'none';
            document.getElementById('researchText').value = '';
        }
    };
    document.getElementById('openBrowserBtn').onclick = function() {
        const url = document.getElementById('researchUrl').value;
        if (url) window.open(url, '_blank');
    };

    // ---------- EQUATION LIBRARY ----------
    document.getElementById('libraryEqBtn').onclick = function() {
        document.getElementById('eqLibraryModal').style.display = 'flex';
        loadEqLibrary();
    };
    document.getElementById('closeEqLibBtn').onclick = function() {
        document.getElementById('eqLibraryModal').style.display = 'none';
    };
    function loadEqLibrary() {
        const list = document.getElementById('eqLibraryList');
        list.innerHTML = '';
        const eqs = [
            { name: 'Quadratic Formula', latex: 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}' },
            { name: 'Pythagorean Theorem', latex: 'a^2 + b^2 = c^2' },
            { name: 'Einstein\'s E=mc²', latex: 'E = mc^2' },
            { name: 'Sine Rule', latex: '\\frac{a}{\\sin A} = \\frac{b}{\\sin B} = \\frac{c}{\\sin C}' },
            { name: 'Cosine Rule', latex: 'c^2 = a^2 + b^2 - 2ab\\cos C' },
            { name: 'Area of Circle', latex: 'A = \\pi r^2' }
        ];
        eqs.forEach(eq => {
            const div = document.createElement('div');
            div.className = 'library-item';
            div.innerHTML = `<strong>${eq.name}</strong><br><span style="font-size:1.2em;">$$${eq.latex}$$</span>`;
            div.onclick = function() {
                if (tinymce.activeEditor) {
                    tinymce.activeEditor.insertContent('$$ ' + eq.latex + ' $$');
                    document.getElementById('eqLibraryModal').style.display = 'none';
                }
            };
            list.appendChild(div);
        });
    }

    // ---------- DIAGRAM LIBRARY ----------
    document.getElementById('libraryDiagramBtn').onclick = function() {
        document.getElementById('diagramLibraryModal').style.display = 'flex';
    };
    document.getElementById('closeDiagramLibBtn').onclick = function() {
        document.getElementById('diagramLibraryModal').style.display = 'none';
    };

    // ---------- EDIT DIAGRAM ----------
    document.getElementById('editDiagramBtn').onclick = function() {
        alert('Please click directly on an image in the editor to crop it, or use the image toolbar.');
    };

    // ---------- MEDIA UPLOAD ----------
    document.getElementById('mediaUploadBtn').onclick = function() {
        document.getElementById('mediaUploadModal').style.display = 'flex';
    };
    document.getElementById('closeMediaUploadBtn').onclick = function() {
        document.getElementById('mediaUploadModal').style.display = 'none';
    };
    document.getElementById('uploadMediaBtn').onclick = function() {
        const input = document.getElementById('mediaFileInput');
        const file = input.files[0];
        if (!file || !tinymce.activeEditor) return;
        const fd = new FormData();
        fd.append('file', file);
        fetch('note_editor_api.php?action=upload_media', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.url) {
                    if (file.type.startsWith('audio/')) {
                        tinymce.activeEditor.insertContent(`<audio controls src="${data.url}"></audio>`);
                    } else if (file.type.startsWith('video/')) {
                        tinymce.activeEditor.insertContent(`<video controls src="${data.url}"></video>`);
                    }
                    document.getElementById('mediaUploadModal').style.display = 'none';
                    document.getElementById('mediaFileInput').value = '';
                }
            });
    };

    // ---------- EXAMPLE / EXERCISE / FOOTER ----------
    document.getElementById('exampleBtn').onclick = function() {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('<div class="example"><strong>Example:</strong><br>Type your example here.</div>');
        }
    };
    document.getElementById('exerciseBtn').onclick = function() {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('<div class="exercise"><strong>Exercise:</strong><br>Type your exercise question here.</div>');
        }
    };
    document.getElementById('footerBtn').onclick = function() {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('<hr><div style="text-align:center;font-size:smaller;"><p><strong>SMART Circle</strong> – Discipline & Integrity</p><p>Blessings Emulyn, Metallurgy & Materials Engineering, MUST</p></div>');
        }
    };

    // ---------- REFERENCE ----------
    document.getElementById('referenceBtn').onclick = function() {
        document.getElementById('referenceModal').style.display = 'flex';
    };
    document.getElementById('closeReferenceBtn').onclick = function() {
        document.getElementById('referenceModal').style.display = 'none';
    };
    document.getElementById('insertReferencesBtn').onclick = function() {
        const list = document.getElementById('referenceListContainer');
        const refs = list.querySelectorAll('.citation-item');
        if (refs.length === 0) {
            alert('No references to insert. Use the Citation button to add references first.');
            return;
        }
        let html = '<ul style="list-style:none; padding-left:0;">';
        refs.forEach(ref => {
            html += `<li style="margin-bottom:8px;">${ref.textContent}</li>`;
        });
        html += '</ul>';
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(html);
            document.getElementById('referenceModal').style.display = 'none';
        }
    };

    // ---------- INSERT CITE ----------
    function addToReferenceList(citationText) {
        const container = document.getElementById('referenceListContainer');
        const item = document.createElement('div');
        item.className = 'citation-item';
        item.textContent = citationText;
        const removeBtn = document.createElement('button');
        removeBtn.textContent = '✕';
        removeBtn.style.marginLeft = '10px';
        removeBtn.style.cursor = 'pointer';
        removeBtn.onclick = function() {
            container.removeChild(item);
        };
        item.appendChild(removeBtn);
        container.appendChild(item);
    }

    const originalAddCitation = addCitationBtn.onclick;
    addCitationBtn.onclick = function() {
        const author = document.getElementById('apaAuthor').value;
        const year = document.getElementById('apaYear').value;
        const title = document.getElementById('apaTitle').value;
        const source = document.getElementById('apaSource').value;
        const doi = document.getElementById('apaDoi').value;
        if (!author || !year || !title || !source) {
            alert("Please fill author, year, title, source.");
            return;
        }
        const citationText = `${author} (${year}). ${title}. ${source}${doi ? ' ' + doi : ''}`;
        addToReferenceList(citationText);
        document.getElementById('apaAuthor').value = '';
        document.getElementById('apaYear').value = '';
        document.getElementById('apaTitle').value = '';
        document.getElementById('apaSource').value = '';
        document.getElementById('apaDoi').value = '';
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(citationText);
        }
        document.getElementById('citationModal').style.display = 'none';
    };

    // ---------- HELPER ----------
    function insertText(text) {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(text);
        }
    }
});
</script>
<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>