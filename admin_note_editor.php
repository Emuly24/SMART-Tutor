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
    } else {
        echo "<script>window.noteId = $note_id; alert('$msg');</script>";
    }
}
?>
<!DOCTYPE html>
<html><head><title>Note Editor</title>
<link rel="stylesheet" href="style.css">
<!-- TinyMCE -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.7.0/tinymce.min.js"></script>
<!-- MathQuill CSS & JS -->
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
    /* Hide textarea to prevent raw HTML flash */
    #editor { display: none; }
    
    /* Sticky toolbar container */
    .sticky-toolbar-wrapper {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        padding: 10px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .toolbar-extras {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
        background: var(--card-alt-bg);
        padding: 10px;
        border-radius: 8px;
    }
    .toolbar-extras button, .toolbar-extras select {
        background: var(--accent);
        color: #1e293b;
        border: none;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
    }
    .toolbar-extras button:hover, .toolbar-extras select:hover {
        background: var(--accent-dark);
        transform: scale(1.02);
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

    /* Bottom action bar */
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
    .bottom-action-bar .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 20px;
        font-weight: 600;
        cursor: pointer;
    }
    .bottom-action-bar .btn-primary { background: var(--accent); color: #1e293b; }
    .bottom-action-bar .btn-secondary { background: #e2e8f0; color: #1e293b; }
    .bottom-action-bar .btn-finish { background: var(--success); color: white; }
    .bottom-action-bar .btn-finish:hover { background: #2e7d32; }
    body { padding-bottom: 80px; } /* prevent content from being hidden behind bottom bar */
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

        <!-- Sticky wrapper for toolbars -->
        <div class="sticky-toolbar-wrapper">
            <div class="toolbar-extras">
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

    <!-- Lock Manager -->
    <div id="lockManager" class="lock-manager">
        <h3>🔒 Group Access Control for this Note</h3>
        <p>Toggle lock/unlock for each group. Locked = group cannot see the note. Unlocked = group can see the note.</p>
        <div id="lockManagerContent"></div>
    </div>
</div>

<!-- Bottom Action Bar -->
<div class="bottom-action-bar">
    <button class="btn btn-secondary" onclick="saveDraftAction()">💾 Save Draft</button>
    <button class="btn btn-secondary" onclick="saveAsAction()">📄 Save As</button>
    <button class="btn btn-finish" onclick="finishAction()">✅ Finish, Lock & Unlock</button>
</div>

<div class="footer" style="margin-bottom: 80px;"><a href="admin_notes_list.php" class="btn-back">← Back to Notes</a></div>
</div>

<!-- Template Library Modal -->
<div id="templateModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <span class="close">&times;</span>
        <h3>📚 Template Library</h3>
        <div style="max-height: 70vh; overflow-y: auto; padding: 10px;">
            <?php include 'complete_templates.html'; ?>
        </div>
    </div>
</div>

<!-- Symbol Modal -->
<div id="symbolModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h3>Insert Symbol</h3><div id="symbolList" style="display:flex;flex-wrap:wrap;gap:8px;max-height:300px;overflow-y:auto;"></div></div></div>

<!-- Citation Modal -->
<div id="citationModal" class="modal"><div class="modal-content"><h3>Add Citation</h3><div class="form-group"><label>Author(s) (Last, First)</label><input type="text" id="apaAuthor"></div><div class="form-group"><label>Year</label><input type="text" id="apaYear"></div><div class="form-group"><label>Title</label><input type="text" id="apaTitle"></div><div class="form-group"><label>Source</label><input type="text" id="apaSource"></div><div class="form-group"><label>DOI (optional)</label><input type="text" id="apaDoi"></div><button id="addCitationBtn" class="btn">Add</button><button id="closeCitationBtn" class="btn-secondary">Cancel</button></div></div>

<!-- Reference Modal -->
<div id="referenceModal" class="modal"><div class="modal-content"><h3>Reference List</h3><div id="referenceListContainer" class="citation-list"></div><button id="insertReferencesBtn" class="btn">Insert List</button><button id="closeReferenceBtn" class="btn-secondary">Close</button></div></div>

<!-- LaTeX Equation Helper Modal -->
<div id="mathHelperModal" class="modal"><div class="modal-content"><h3>Equation Helper (LaTeX)</h3>
    <div class="form-group"><label>LaTeX</label><textarea id="latexHelperInput" rows="3" placeholder="e.g. N(t)=N_0 e^{kt}"></textarea></div>
    <div class="math-preview" id="mathHelperPreview"></div>
    <button id="insertHelperEquationBtn" class="btn">Insert</button>
    <button id="closeMathHelperBtn" class="btn-secondary">Cancel</button>
</div></div>

<!-- MathQuill Helper Modal -->
<div id="mathquillModal" class="modal"><div class="modal-content"><h3>MathQuill Equation Editor</h3>
    <div style="background:#f5f5f5; padding:20px; border-radius:8px; margin:15px 0; text-align:center;">
        <div id="mathquill-field" style="font-size:24px; min-height:60px; background:white; padding:10px; border:1px solid #ccc; border-radius:4px;"></div>
    </div>
    <p style="color:#666;">Type your equation. Use <code>^</code> for superscript, <code>_</code> for subscript, <code>\frac{}{}</code> for fractions.</p>
    <button id="insertMathquillBtn" class="btn">Insert Equation</button>
    <button id="closeMathquillBtn" class="btn-secondary">Cancel</button>
</div></div>

<!-- Diagram Editor Modal -->
<div id="diagramEditorModal" class="modal"><div class="modal-content"><h3>Edit Diagram</h3><div class="image-editor-container"><div><img id="editorImage" src=""></div><div class="image-controls"><label>Brightness</label><input type="range" id="brightness" min="-100" max="100" value="0"><br><label>Contrast</label><input type="range" id="contrast" min="-100" max="100" value="0"><br><label>Width (px)</label><input type="number" id="resizeWidth"><br><button id="applyImageChanges" class="btn">Apply</button><button id="saveEditedImage" class="btn">Save</button></div></div><button id="closeDiagramEditorBtn" class="btn-secondary">Cancel</button></div></div>

<!-- Media Upload Modal -->
<div id="mediaUploadModal" class="modal"><div class="modal-content"><h3>Upload Audio/Video</h3><input type="file" id="mediaFileInput" accept="audio/*,video/*"><button id="uploadMediaBtn" class="btn">Upload & Embed</button><button id="closeMediaUploadBtn" class="btn-secondary">Cancel</button></div></div>

<!-- Equation Library Modal -->
<div id="eqLibraryModal" class="modal"><div class="modal-content"><h3>Equation Library</h3><div id="eqLibraryList" class="library-grid"></div><button id="closeEqLibBtn" class="btn-secondary">Close</button></div></div>

<!-- Diagram Library Modal -->
<div id="diagramLibraryModal" class="modal"><div class="modal-content"><h3>Diagram Library</h3><div id="diagramLibraryList" class="library-grid"></div><button id="closeDiagramLibBtn" class="btn-secondary">Close</button></div></div>

<!-- Chemistry Modal -->
<div id="chemistryModal" class="modal"><div class="modal-content"><h3>Common Chemistry Equations</h3><select id="chemistrySelect" style="width:100%;padding:8px;margin-bottom:15px;"><option value="">-- Select --</option><option value="Photosynthesis: 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂">Photosynthesis</option><option value="Cellular Respiration: C₆H₁₂O₆ + 6O₂ → 6CO₂ + 6H₂O + ATP">Cellular Respiration</option><option value="Hydrochloric Acid: HCl + H₂O → H₃O⁺ + Cl⁻">Hydrochloric Acid</option><option value="Neutralisation: H⁺ + OH⁻ → H₂O">Neutralisation</option><option value="Electrolysis of Water: 2H₂O → 2H₂ + O₂">Electrolysis of Water</option></select><button id="insertChemistryBtn" class="btn">Insert</button><button id="closeChemistryBtn" class="btn-secondary">Cancel</button></div></div>

<!-- Web Research Modal -->
<div id="webResearchModal" class="modal"><div class="modal-content"><h3>Web Research</h3><div class="form-group"><label>URL</label><input type="text" id="researchUrl" value="https://scholar.google.com/"></div><button id="openBrowserBtn" class="btn">Open</button><div class="form-group"><label>Notes</label><textarea id="researchText" rows="6"></textarea></div><button id="insertResearchNoteBtn" class="btn">Insert Notes</button><button id="closeWebResearchBtn" class="btn-secondary">Cancel</button></div></div>

<!-- Media Embed Modal -->
<div id="mediaModal" class="modal"><div class="modal-content"><h3>Embed Media (URL)</h3><div class="form-group"><label>Media URL</label><input type="text" id="mediaUrl"></div><button id="insertMediaBtn" class="btn">Embed</button><button id="closeMediaBtn" class="btn-secondary">Cancel</button></div></div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // ---------- TINYMCE CORE ----------
    tinymce.init({
        selector: '#editor',
        height: 600,
        menubar: true,
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
        toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | specialchars | charmap | code',
        toolbar_sticky: true,
        content_style: 'body { font-family: Inter, sans-serif; }',
        setup: function(editor) {
            // ---------- SET CONTENT SAFELY ----------
            const existingContent = <?= json_encode($existing_note['content'] ?? '') ?>;
            if (existingContent) {
                editor.on('init', function() {
                    editor.setContent(existingContent);
                });
            }

            // ---------- AUTO-SAVE DRAFT ----------
            setInterval(function() {
                saveDraft(editor);
            }, 30000);

            editor.addShortcut('Ctrl+S', 'Save Draft', function() {
                saveDraft(editor);
            });

            // ---------- ADD CUSTOM MENU ITEMS ----------
            editor.ui.registry.addMenuItem('save', {
                text: 'Save',
                icon: 'save',
                onAction: function() {
                    saveDraftAction();
                }
            });
            editor.ui.registry.addMenuItem('saveas', {
                text: 'Save As...',
                icon: 'newdocument',
                onAction: function() {
                    saveAsAction();
                }
            });
            // Add them to File menu
            editor.ui.registry.addMenuItem('savegroup', {
                type: 'separator'
            });
            // Prepend to File menu
            editor.on('init', function() {
                const menu = editor.menuItems;
                if (menu['file']) {
                    // Insert after 'newdocument' (approx)
                    const fileMenu = menu['file'];
                    // Better: use custom menu, but for simplicity we add via API
                    editor.menu.add('file', {
                        title: 'File',
                        items: 'newdocument | save | saveas | print'
                    });
                }
            });

            // ---------- MATHJAX & DIAGRAMS ----------
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

    // ---------- DRAFT FUNCTIONS ----------
    function saveDraft(editor) {
        const title = document.getElementById('noteTitle').value;
        const subject = document.querySelector('select[name="subject"]').value;
        const classLevel = document.querySelector('select[name="class_level"]').value;
        const content = editor.getData();
        localStorage.setItem('note_draft', JSON.stringify({
            title: title,
            subject: subject,
            class_level: classLevel,
            content: content
        }));
        console.log('Draft saved to localStorage at ' + new Date().toLocaleTimeString());
    }

    // ---------- FORM SUBMIT HELPERS ----------
    window.saveDraftAction = function() {
        // Trigger form submit without special flags
        const form = document.getElementById('noteForm');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'save_draft';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };
    window.saveAsAction = function() {
        const form = document.getElementById('noteForm');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'save_as';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };
    window.finishAction = function() {
        const form = document.getElementById('noteForm');
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'finish';
        hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };

    // ---------- GROUP LOADER ----------
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

    // ---------- LOCK MANAGER ----------
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

    // ---------- SYMBOL PALETTE ----------
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

    // ---------- LATEX EQUATION HELPER (Fixed) ----------
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

    latexHelperInput.addEventListener('input', function() {
        if (mathHelperPreview) {
            mathHelperPreview.innerHTML = `\\[ ${latexHelperInput.value} \\]`;
            if (window.MathJax) {
                MathJax.typesetPromise([mathHelperPreview]).catch(console.log);
            }
        }
    });

    insertHelperEquationBtn.onclick = function() {
        const latex = latexHelperInput?.value;
        if (latex && tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('$$ ' + latex + ' $$');
            mathHelperModal.style.display = 'none';
            latexHelperInput.value = '';
            if (mathHelperPreview) {
                mathHelperPreview.innerHTML = '';
            }
        }
    };

    // ---------- MATHQUILL EQUATION HELPER (New) ----------
    const mathquillBtn = document.getElementById('mathquillBtn');
    const mathquillModal = document.getElementById('mathquillModal');
    const closeMathquillBtn = document.getElementById('closeMathquillBtn');
    const insertMathquillBtn = document.getElementById('insertMathquillBtn');
    const mathquillField = document.getElementById('mathquill-field');

    let mqField = null;
    mathquillBtn.onclick = function() {
        mathquillModal.style.display = 'flex';
        setTimeout(() => {
            if (!mqField && mathquillField) {
                const MQ = MathQuill.getInterface(2);
                mqField = MQ.MathField(mathquillField, {
                    spaceBehavesLikeTab: true,
                    handlers: {
                        edit: function() {
                            // optional live preview
                        }
                    }
                });
                // Focus the field
                mqField.focus();
            }
        }, 200);
    };
    closeMathquillBtn.onclick = function() {
        mathquillModal.style.display = 'none';
    };
    insertMathquillBtn.onclick = function() {
        if (mqField && tinymce.activeEditor) {
            const latex = mqField.latex();
            if (latex.trim()) {
                tinymce.activeEditor.insertContent('$$ ' + latex + ' $$');
            }
            mathquillModal.style.display = 'none';
            mqField = null; // will reinit next time
            mathquillField.innerHTML = '';
        }
    };

    // ---------- INSERT EXAMPLE / EXERCISE ----------
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

    // ---------- INSERT FOOTER ----------
    document.getElementById('footerBtn').onclick = function() {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent('<hr><div style="text-align:center;font-size:smaller;"><p><strong>SMART Circle</strong> – Discipline & Integrity</p><p>Blessings Emulyn, Metallurgy & Materials Engineering, MUST</p></div>');
        }
    };

    // ---------- HELPER FUNCTIONS ----------
    function insertText(text) {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(text);
        }
    }
    function insertHtml(html) {
        if (tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(html);
        }
    }
});
</script>
<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>