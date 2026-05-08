<?php
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

// Core subjects we assist with
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDB();
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;
    
    $conn->query("INSERT INTO notes (title, subject, class_level, content) VALUES ('$title', '$subject', '$class', '$content')");
    $note_id = $conn->insert_id;
    $conn->query("DELETE FROM note_drafts");
    
    // If a specific group was selected, automatically unlock this note for that group (and lock for others)
    if ($group_id) {
        // Lock for all groups of this class and route? Better: insert lock records for all groups, then unlock this one.
        $all_groups = $conn->query("SELECT id FROM groups WHERE class_level = '$class'");
        while ($g = $all_groups->fetch_assoc()) {
            $lock = $g['id'] == $group_id ? 0 : 1;
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$g['id']}, 'note', $note_id, $lock)
                          ON DUPLICATE KEY UPDATE is_locked = $lock");
        }
        $msg = "Note saved and unlocked for the selected group.";
    } else {
        $msg = "Note saved. Use Group Content Locks to control access per group.";
    }
    echo "<script>alert('$msg');</script>";
}
?>
<!DOCTYPE html>
<html><head><title>Advanced Note Editor</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.css">
<style>
    .ck-editor__editable { min-height: 600px; width: 100% !important; }
    .ck-editor { width: 100% !important; }
    .ck-editor__editable p { text-align: justify; }
    .toolbar-extras { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; background: var(--card-alt-bg); padding: 10px; border-radius: 8px; }
    .toolbar-extras button, .toolbar-extras select { background: var(--accent); color: #1e293b; border: none; padding: 6px 14px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .toolbar-extras button:hover, .toolbar-extras select:hover { background: var(--accent-dark); transform: scale(1.02); }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 10000; }
    .modal-content { background: var(--card-bg); padding: 2rem; border-radius: 1rem; max-width: 90%; width: 800px; max-height: 90%; overflow-y: auto; }
    .library-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 10px; max-height: 500px; overflow-y: auto; }
    .library-item { border: 1px solid #ddd; padding: 8px; border-radius: 8px; cursor: pointer; transition: 0.2s; text-align: center; }
    .library-item:hover { background: var(--accent-light); transform: scale(1.02); }
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
</style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
<div style="padding: 2rem;">
    <form method="post" id="noteForm">
        <div class="form-group"><label>Title</label><input type="text" id="noteTitle" name="title" required></div>
        <div class="form-group"><label>Subject</label>
            <select name="subject" required>
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Class</label>
            <select id="noteClass" name="class_level" required>
                <option>Form 3</option><option>Form 4</option>
            </select>
        </div>
        
        <!-- Group selection (optional) -->
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

        <div class="toolbar-extras">
            <button type="button" id="symbolBtn">Ω Symbols</button>
            <button type="button" id="fileUploadBtn">📎 Attach File</button>
            <button type="button" id="citationBtn">📚 Cite</button>
            <button type="button" id="mathBtn">∫ Equation</button>
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
        </div>
        <div class="form-group"><label>Content</label><textarea name="content" id="editor"></textarea></div>
        <button type="submit">Save Note</button>
    </form>
</div>
<div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<!-- All modals (same as before) -->
<div id="symbolModal" class="modal"><div class="modal-content"><span class="close">&times;</span><h3>Insert Symbol</h3><div id="symbolList" style="display:flex;flex-wrap:wrap;gap:8px;max-height:300px;overflow-y:auto;"></div></div></div>
<div id="citationModal" class="modal"><div class="modal-content"><h3>Add Citation</h3><div class="form-group"><label>Author(s) (Last, First)</label><input type="text" id="apaAuthor"></div><div class="form-group"><label>Year</label><input type="text" id="apaYear"></div><div class="form-group"><label>Title</label><input type="text" id="apaTitle"></div><div class="form-group"><label>Source</label><input type="text" id="apaSource"></div><div class="form-group"><label>DOI (optional)</label><input type="text" id="apaDoi"></div><button id="addCitationBtn" class="btn">Add</button><button id="closeCitationBtn" class="btn-secondary">Cancel</button></div></div>
<div id="referenceModal" class="modal"><div class="modal-content"><h3>Reference List</h3><div id="referenceListContainer" class="citation-list"></div><button id="insertReferencesBtn" class="btn">Insert List</button><button id="closeReferenceBtn" class="btn-secondary">Close</button></div></div>
<div id="mathHelperModal" class="modal"><div class="modal-content"><h3>Equation Helper</h3><div class="form-group"><label>LaTeX</label><textarea id="latexHelperInput" rows="3"></textarea></div><div class="math-preview" id="mathHelperPreview"></div><button id="insertHelperEquationBtn" class="btn">Insert</button><button id="closeMathHelperBtn" class="btn-secondary">Cancel</button></div></div>
<div id="diagramEditorModal" class="modal"><div class="modal-content"><h3>Edit Diagram</h3><div class="image-editor-container"><div><img id="editorImage" src=""></div><div class="image-controls"><label>Brightness</label><input type="range" id="brightness" min="-100" max="100" value="0"><br><label>Contrast</label><input type="range" id="contrast" min="-100" max="100" value="0"><br><label>Width (px)</label><input type="number" id="resizeWidth"><br><button id="applyImageChanges" class="btn">Apply</button><button id="saveEditedImage" class="btn">Save</button></div></div><button id="closeDiagramEditorBtn" class="btn-secondary">Cancel</button></div></div>
<div id="mediaUploadModal" class="modal"><div class="modal-content"><h3>Upload Audio/Video</h3><input type="file" id="mediaFileInput" accept="audio/*,video/*"><button id="uploadMediaBtn" class="btn">Upload & Embed</button><button id="closeMediaUploadBtn" class="btn-secondary">Cancel</button></div></div>
<div id="eqLibraryModal" class="modal"><div class="modal-content"><h3>Equation Library</h3><div id="eqLibraryList" class="library-grid"></div><button id="closeEqLibBtn" class="btn-secondary">Close</button></div></div>
<div id="diagramLibraryModal" class="modal"><div class="modal-content"><h3>Diagram Library</h3><div id="diagramLibraryList" class="library-grid"></div><button id="closeDiagramLibBtn" class="btn-secondary">Close</button></div></div>
<div id="chemistryModal" class="modal"><div class="modal-content"><h3>Common Chemistry Equations</h3><select id="chemistrySelect" style="width:100%;padding:8px;margin-bottom:15px;"><option value="">-- Select --</option><option value="Photosynthesis: 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂">Photosynthesis</option><option value="Cellular Respiration: C₆H₁₂O₆ + 6O₂ → 6CO₂ + 6H₂O + ATP">Cellular Respiration</option><option value="Hydrochloric Acid: HCl + H₂O → H₃O⁺ + Cl⁻">Hydrochloric Acid</option><option value="Neutralisation: H⁺ + OH⁻ → H₂O">Neutralisation</option><option value="Electrolysis of Water: 2H₂O → 2H₂ + O₂">Electrolysis of Water</option></select><button id="insertChemistryBtn" class="btn">Insert</button><button id="closeChemistryBtn" class="btn-secondary">Cancel</button></div></div>
<div id="webResearchModal" class="modal"><div class="modal-content"><h3>Web Research</h3><div class="form-group"><label>URL</label><input type="text" id="researchUrl" value="https://scholar.google.com/"></div><button id="openBrowserBtn" class="btn">Open</button><div class="form-group"><label>Notes</label><textarea id="researchText" rows="6"></textarea></div><button id="insertResearchNoteBtn" class="btn">Insert Notes</button><button id="closeWebResearchBtn" class="btn-secondary">Cancel</button></div></div>
<div id="mediaModal" class="modal"><div class="modal-content"><h3>Embed Media (URL)</h3><div class="form-group"><label>Media URL</label><input type="text" id="mediaUrl"></div><button id="insertMediaBtn" class="btn">Embed</button><button id="closeMediaBtn" class="btn-secondary">Cancel</button></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let editorInstance = null;
    let currentCitations = [];
    let currentEditedImage = null;
    let cropper = null;

    // ======================= GROUP LOADER =======================
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
    
    // ======================= SYMBOL PALETTE =======================
    const symbolPalette = {
        "Greek": ["α","β","γ","δ","ε","ζ","η","θ","ι","κ","λ","μ","ν","ξ","ο","π","ρ","σ","τ","υ","φ","χ","ψ","ω","Α","Β","Γ","Δ","Ε","Ζ","Η","Θ","Ι","Κ","Λ","Μ","Ν","Ξ","Ο","Π","Ρ","Σ","Τ","Υ","Φ","Χ","Ψ","Ω"],
        "Math": ["+","−","×","÷","±","√","∫","∑","∏","∂","∇","∞","∝","∠","⊥","≅","≈","≠","≤","≥","→","↔","⇒","⇔"],
        "Arrows": ["←","↑","→","↓","↔","↕","↖","↗","↘","↙","↩","↪","⇒","⇐","⇔","⇑","⇓","⇕"],
        "Chemistry": ["↑","↓","→","↔","⇌","●","○","□","■","△","▲","▼","◆","◇","⇌","⇄"]
    };
    const symbolModal = document.getElementById('symbolModal');
    const symbolList = document.getElementById('symbolList');
    if (symbolList) {
        for (const [cat, syms] of Object.entries(symbolPalette)) {
            const header = document.createElement('div'); header.innerHTML = `<strong>${cat}</strong><hr>`;
            symbolList.appendChild(header);
            syms.forEach(sym => {
                const btn = document.createElement('button');
                btn.textContent = sym;
                btn.style.margin = '4px';
                btn.style.padding = '6px 12px';
                btn.style.borderRadius = '12px';
                btn.style.border = '1px solid #ccc';
                btn.style.cursor = 'pointer';
                btn.onclick = () => { insertText(sym); symbolModal.style.display = 'none'; };
                symbolList.appendChild(btn);
            });
        }
    }
    const symbolBtn = document.getElementById('symbolBtn');
    if (symbolBtn) symbolBtn.onclick = () => symbolModal.style.display = 'flex';
    const closeSymbol = symbolModal?.querySelector('.close');
    if (closeSymbol) closeSymbol.onclick = () => symbolModal.style.display = 'none';

    // ======================= FILE UPLOAD =======================
    const fileUploadBtn = document.getElementById('fileUploadBtn');
    if (fileUploadBtn) {
        fileUploadBtn.onclick = () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const fd = new FormData();
                fd.append('file', file);
                fetch('note_editor_api.php?action=upload_attachment', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => { if (data.url) insertText(`[File: ${file.name}](${data.url})`); else alert('Upload failed'); });
            };
            input.click();
        };
    }

    // ======================= APA CITATION =======================
    const citationBtn = document.getElementById('citationBtn');
    const citationModal = document.getElementById('citationModal');
    const closeCitationBtn = document.getElementById('closeCitationBtn');
    const addCitationBtn = document.getElementById('addCitationBtn');
    if (citationBtn) citationBtn.onclick = () => citationModal.style.display = 'flex';
    if (closeCitationBtn) closeCitationBtn.onclick = () => citationModal.style.display = 'none';
    if (addCitationBtn) {
        addCitationBtn.onclick = () => {
            const author = document.getElementById('apaAuthor').value;
            const year = document.getElementById('apaYear').value;
            const title = document.getElementById('apaTitle').value;
            const source = document.getElementById('apaSource').value;
            const doi = document.getElementById('apaDoi').value;
            if (!author || !year || !title || !source) { alert("Please fill author, year, title, source."); return; }
            currentCitations.push(`${author} (${year}). ${title}. ${source}${doi ? ' ' + doi : ''}`);
            citationModal.style.display = 'none';
            ['apaAuthor','apaYear','apaTitle','apaSource','apaDoi'].forEach(id => document.getElementById(id).value = '');
            alert("Citation added.");
        };
    }

    // Reference Manager
    const referenceBtn = document.getElementById('referenceBtn');
    const referenceModal = document.getElementById('referenceModal');
    const closeReferenceBtn = document.getElementById('closeReferenceBtn');
    const insertReferencesBtn = document.getElementById('insertReferencesBtn');
    if (referenceBtn) referenceBtn.onclick = () => {
        const container = document.getElementById('referenceListContainer');
        if (!container) return;
        container.innerHTML = '';
        if (currentCitations.length === 0) container.innerHTML = '<p>No citations added yet.</p>';
        else {
            currentCitations.forEach((cit, idx) => {
                const div = document.createElement('div');
                div.className = 'citation-item';
                div.innerHTML = `<span>${cit}</span> <button class="remove-citation" data-idx="${idx}">❌</button>`;
                container.appendChild(div);
            });
            document.querySelectorAll('.remove-citation').forEach(btn => {
                btn.onclick = () => {
                    const idx = parseInt(btn.getAttribute('data-idx'));
                    currentCitations.splice(idx, 1);
                    referenceBtn.click(); // refresh
                };
            });
        }
        referenceModal.style.display = 'flex';
    };
    if (closeReferenceBtn) closeReferenceBtn.onclick = () => referenceModal.style.display = 'none';
    if (insertReferencesBtn) {
        insertReferencesBtn.onclick = () => {
            if (currentCitations.length === 0) { alert("No citations."); return; }
            let refHtml = '<h3>References</h3><ul>';
            currentCitations.forEach(cit => refHtml += `<li>${cit}</li>`);
            refHtml += '</ul>';
            insertHtml(refHtml);
            referenceModal.style.display = 'none';
        };
    }

    // ======================= EQUATION HELPER =======================
    const mathBtn = document.getElementById('mathBtn');
    const mathHelperModal = document.getElementById('mathHelperModal');
    const closeMathHelperBtn = document.getElementById('closeMathHelperBtn');
    const insertHelperEquationBtn = document.getElementById('insertHelperEquationBtn');
    const latexHelperInput = document.getElementById('latexHelperInput');
    const mathHelperPreview = document.getElementById('mathHelperPreview');
    if (mathBtn) mathBtn.onclick = () => mathHelperModal.style.display = 'flex';
    if (closeMathHelperBtn) closeMathHelperBtn.onclick = () => mathHelperModal.style.display = 'none';
    if (latexHelperInput) {
        latexHelperInput.addEventListener('input', () => {
            if (mathHelperPreview) {
                mathHelperPreview.innerHTML = `\\[ ${latexHelperInput.value} \\]`;
                if (window.MathJax) MathJax.typesetPromise([mathHelperPreview]).catch(console.log);
            }
        });
    }
    if (insertHelperEquationBtn) {
        insertHelperEquationBtn.onclick = () => {
            const latex = latexHelperInput?.value;
            if (latex) {
                insertText(`$$ ${latex} $$`);
                mathHelperModal.style.display = 'none';
                if (latexHelperInput) latexHelperInput.value = '';
                if (mathHelperPreview) mathHelperPreview.innerHTML = '';
            }
        };
    }

    // ======================= DIAGRAM EDITOR =======================
    const editDiagramBtn = document.getElementById('editDiagramBtn');
    const diagramEditorModal = document.getElementById('diagramEditorModal');
    const closeDiagramEditorBtn = document.getElementById('closeDiagramEditorBtn');
    const applyImageChanges = document.getElementById('applyImageChanges');
    const saveEditedImage = document.getElementById('saveEditedImage');
    const editorImage = document.getElementById('editorImage');
    if (editDiagramBtn) {
        editDiagramBtn.onclick = () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                currentEditedImage = file;
                const url = URL.createObjectURL(file);
                if (editorImage) {
                    editorImage.src = url;
                    editorImage.onload = () => {
                        if (cropper) cropper.destroy();
                        cropper = new Cropper(editorImage, { aspectRatio: NaN, viewMode: 1 });
                        const brightness = document.getElementById('brightness');
                        const contrast = document.getElementById('contrast');
                        const resizeWidth = document.getElementById('resizeWidth');
                        if (brightness) brightness.value = 0;
                        if (contrast) contrast.value = 0;
                        if (resizeWidth) resizeWidth.value = '';
                    };
                }
                diagramEditorModal.style.display = 'flex';
            };
            input.click();
        };
    }
    if (closeDiagramEditorBtn) closeDiagramEditorBtn.onclick = () => { if (cropper) cropper.destroy(); diagramEditorModal.style.display = 'none'; };
    if (applyImageChanges) {
        applyImageChanges.onclick = () => {
            if (!cropper) return;
            const brightness = parseInt(document.getElementById('brightness')?.value || 0);
            const contrast = parseInt(document.getElementById('contrast')?.value || 0);
            const targetWidth = document.getElementById('resizeWidth')?.value;
            let canvas = cropper.getCroppedCanvas();
            const ctx = canvas.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            for (let i = 0; i < data.length; i += 4) {
                let r = data[i], g = data[i+1], b = data[i+2];
                r += brightness; g += brightness; b += brightness;
                const factor = (259 * (contrast + 255)) / (255 * (259 - contrast));
                r = factor * (r - 128) + 128;
                g = factor * (g - 128) + 128;
                b = factor * (b - 128) + 128;
                data[i] = Math.min(255, Math.max(0, r));
                data[i+1] = Math.min(255, Math.max(0, g));
                data[i+2] = Math.min(255, Math.max(0, b));
            }
            ctx.putImageData(imageData, 0, 0);
            if (targetWidth && parseInt(targetWidth) > 0) {
                const newWidth = parseInt(targetWidth);
                const newHeight = canvas.height * (newWidth / canvas.width);
                const newCanvas = document.createElement('canvas');
                newCanvas.width = newWidth;
                newCanvas.height = newHeight;
                const newCtx = newCanvas.getContext('2d');
                newCtx.drawImage(canvas, 0, 0, newWidth, newHeight);
                canvas = newCanvas;
            }
            if (editorImage) editorImage.src = canvas.toDataURL();
            if (cropper) cropper.destroy();
            cropper = new Cropper(editorImage, { aspectRatio: NaN, viewMode: 1 });
        };
    }
    if (saveEditedImage) {
        saveEditedImage.onclick = () => {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas();
            canvas.toBlob(blob => {
                const formData = new FormData();
                const title = prompt("Enter title for this diagram:", "Edited Diagram");
                if (!title) return;
                const category = prompt("Category (biology/physics/chemistry/agriculture):", "biology");
                formData.append('diagram_title', title);
                formData.append('diagram_category', category);
                formData.append('diagram_image', blob, 'edited_diagram.png');
                fetch('admin_library_manager.php', { method: 'POST', body: formData })
                    .then(() => { alert("Diagram saved."); if (cropper) cropper.destroy(); diagramEditorModal.style.display = 'none'; });
            });
        };
    }

    // ======================= BIOLOGY DIAGRAMS (library) =======================
    const diagramBtn = document.getElementById('diagramBtn');
    const diagramModal = document.getElementById('diagramModal');
    const diagramGrid = document.getElementById('diagramGrid');
    const closeDiagramBtn = document.getElementById('closeDiagramBtn');
    if (diagramBtn) {
        diagramBtn.onclick = () => {
            fetch('admin_library_api.php?type=diagrams')
                .then(res => res.json())
                .then(images => {
                    if (diagramGrid) {
                        diagramGrid.innerHTML = '';
                        if (images.length === 0) diagramGrid.innerHTML = '<p>No diagrams uploaded.</p>';
                        else {
                            images.forEach(img => {
                                const div = document.createElement('div');
                                div.className = 'library-item';
                                div.innerHTML = `<img src="${img.file_path}" style="max-width:100%;"><br><strong>${img.title}</strong><br><small>${img.category}</small>`;
                                div.onclick = () => { insertText(`![${img.title}](${img.file_path})`); diagramModal.style.display = 'none'; };
                                diagramGrid.appendChild(div);
                            });
                        }
                    }
                    diagramModal.style.display = 'flex';
                });
        };
    }
    if (closeDiagramBtn) closeDiagramBtn.onclick = () => diagramModal.style.display = 'none';

    // ======================= EQUATIONS LIBRARY =======================
    const libraryEqBtn = document.getElementById('libraryEqBtn');
    const eqLibraryModal = document.getElementById('eqLibraryModal');
    const eqLibraryList = document.getElementById('eqLibraryList');
    const closeEqLibBtn = document.getElementById('closeEqLibBtn');
    if (libraryEqBtn) {
        libraryEqBtn.onclick = () => {
            fetch('admin_library_api.php?type=equations')
                .then(res => res.json())
                .then(items => {
                    if (eqLibraryList) {
                        eqLibraryList.innerHTML = '';
                        items.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'library-item';
                            div.innerHTML = `<strong>${item.title}</strong><br><code>${item.latex}</code><br><small>${item.category}</small>`;
                            div.onclick = () => { insertText(`$$ ${item.latex} $$`); eqLibraryModal.style.display = 'none'; };
                            eqLibraryList.appendChild(div);
                        });
                    }
                    eqLibraryModal.style.display = 'flex';
                });
        };
    }
    if (closeEqLibBtn) closeEqLibBtn.onclick = () => eqLibraryModal.style.display = 'none';

    // ======================= DIAGRAMS LIBRARY (admin managed) =======================
    const libraryDiagramBtn = document.getElementById('libraryDiagramBtn');
    const diagramLibraryModal = document.getElementById('diagramLibraryModal');
    const diagramLibraryList = document.getElementById('diagramLibraryList');
    const closeDiagramLibBtn = document.getElementById('closeDiagramLibBtn');
    if (libraryDiagramBtn) {
        libraryDiagramBtn.onclick = () => {
            fetch('admin_library_api.php?type=diagrams')
                .then(res => res.json())
                .then(items => {
                    if (diagramLibraryList) {
                        diagramLibraryList.innerHTML = '';
                        items.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'library-item';
                            div.innerHTML = `<img src="${item.file_path}" style="max-width:100%;"><br><strong>${item.title}</strong><br><small>${item.category}</small>`;
                            div.onclick = () => { insertText(`![${item.title}](${item.file_path})`); diagramLibraryModal.style.display = 'none'; };
                            diagramLibraryList.appendChild(div);
                        });
                    }
                    diagramLibraryModal.style.display = 'flex';
                });
        };
    }
    if (closeDiagramLibBtn) closeDiagramLibBtn.onclick = () => diagramLibraryModal.style.display = 'none';

    // ======================= CHEMISTRY EQUATIONS =======================
    const chemistryBtn = document.getElementById('chemistryBtn');
    const chemistryModal = document.getElementById('chemistryModal');
    const closeChemistryBtn = document.getElementById('closeChemistryBtn');
    const insertChemistryBtn = document.getElementById('insertChemistryBtn');
    const chemistrySelect = document.getElementById('chemistrySelect');
    if (chemistryBtn) chemistryBtn.onclick = () => chemistryModal.style.display = 'flex';
    if (closeChemistryBtn) closeChemistryBtn.onclick = () => chemistryModal.style.display = 'none';
    if (insertChemistryBtn) {
        insertChemistryBtn.onclick = () => {
            const selected = chemistrySelect?.value;
            if (selected) insertText(selected);
            chemistryModal.style.display = 'none';
        };
    }

    // ======================= MEDIA UPLOAD =======================
    const mediaBtn = document.getElementById('mediaBtn');
    const mediaUploadModal = document.getElementById('mediaUploadModal');
    const closeMediaUploadBtn = document.getElementById('closeMediaUploadBtn');
    const uploadMediaBtn = document.getElementById('uploadMediaBtn');
    const mediaFileInput = document.getElementById('mediaFileInput');
    if (mediaBtn) mediaBtn.onclick = () => mediaUploadModal.style.display = 'flex';
    if (closeMediaUploadBtn) closeMediaUploadBtn.onclick = () => mediaUploadModal.style.display = 'none';
    if (uploadMediaBtn) {
        uploadMediaBtn.onclick = () => {
            const file = mediaFileInput?.files[0];
            if (!file) { alert("Select a file."); return; }
            const formData = new FormData();
            formData.append('file', file);
            fetch('note_editor_api.php?action=upload_media', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.url) {
                        const ext = file.name.split('.').pop().toLowerCase();
                        let tag = '';
                        if (['mp3','wav','ogg'].includes(ext)) tag = `<audio controls src="${data.url}"></audio>`;
                        else if (['mp4','webm','mov'].includes(ext)) tag = `<video controls width="100%" src="${data.url}"></video>`;
                        else tag = `<a href="${data.url}">${file.name}</a>`;
                        insertHtml(tag);
                        mediaUploadModal.style.display = 'none';
                        if (mediaFileInput) mediaFileInput.value = '';
                    } else alert('Upload failed');
                });
        };
    }

    // ======================= INSERT EXAMPLE / EXERCISE =======================
    const exampleBtn = document.getElementById('exampleBtn');
    const exerciseBtn = document.getElementById('exerciseBtn');
    if (exampleBtn) exampleBtn.onclick = () => insertHtml('<div class="example"><strong>Example:</strong><br>Type your example here.</div>');
    if (exerciseBtn) exerciseBtn.onclick = () => insertHtml('<div class="exercise"><strong>Exercise:</strong><br>Type your exercise question here.</div>');

    // ======================= INSERT FOOTER =======================
    const footerBtn = document.getElementById('footerBtn');
    if (footerBtn) {
        footerBtn.onclick = () => {
            const footerHtml = `<hr><div style="text-align:center;font-size:smaller;"><p><strong>SMART Tutor</strong> – Discipline & Integrity</p><p>Blessings Emulyn, Metallurgy & Materials Engineering, MUST</p></div>`;
            insertHtml(footerHtml);
        };
    }

    // ======================= RESEARCH MODAL =======================
    const researchPanelBtn = document.getElementById('researchPanelBtn');
    const webResearchModal = document.getElementById('webResearchModal');
    const closeWebResearchBtn = document.getElementById('closeWebResearchBtn');
    const openBrowserBtn = document.getElementById('openBrowserBtn');
    const insertResearchNoteBtn = document.getElementById('insertResearchNoteBtn');
    const researchText = document.getElementById('researchText');
    const researchUrl = document.getElementById('researchUrl');
    if (researchPanelBtn) researchPanelBtn.onclick = () => webResearchModal.style.display = 'flex';
    if (closeWebResearchBtn) closeWebResearchBtn.onclick = () => webResearchModal.style.display = 'none';
    if (openBrowserBtn) {
        openBrowserBtn.onclick = () => {
            let url = researchUrl?.value.trim();
            if (!url) url = 'https://scholar.google.com/';
            if (!url.startsWith('http')) url = 'https://' + url;
            window.open(url, '_blank');
        };
    }
    if (insertResearchNoteBtn) {
        insertResearchNoteBtn.onclick = () => {
            const note = researchText?.value;
            if (note) insertText(note);
            webResearchModal.style.display = 'none';
        };
    }

    // ======================= AUTO‑SAVE DRAFT =======================
    function autoSaveDraft() {
        if (!editorInstance) return;
        const title = document.getElementById('noteTitle')?.value;
        const subject = document.getElementById('noteSubject')?.value;
        const classLevel = document.getElementById('noteClass')?.value;
        const content = editorInstance.getData();
        fetch('note_editor_api.php?action=auto_save_draft', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, subject, class_level: classLevel, content })
        });
    }
    setInterval(autoSaveDraft, 30000);
    window.addEventListener('beforeunload', () => autoSaveDraft());
    function loadDraft() {
        fetch('note_editor_api.php?action=load_draft')
            .then(res => res.json())
            .then(draft => {
                if (draft && draft.title && confirm('Load unsaved draft?')) {
                    const titleInput = document.getElementById('noteTitle');
                    const subjectInput = document.getElementById('noteSubject');
                    const classSelect = document.getElementById('noteClass');
                    if (titleInput) titleInput.value = draft.title;
                    if (subjectInput) subjectInput.value = draft.subject;
                    if (classSelect) classSelect.value = draft.class_level;
                    if (editorInstance) editorInstance.setData(draft.content);
                }
            });
    }

    // ======================= INITIALIZE CKEDITOR =======================
    ClassicEditor
        .create(document.querySelector('#editor'), {
            toolbar: [
                'heading', '|', 'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', '|',
                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                'alignment:left', 'alignment:center', 'alignment:right', 'alignment:justify', '|',
                'bulletedList', 'numberedList', '|',
                'link', 'blockQuote', 'insertTable', 'imageUpload', 'mediaEmbed', '|',
                'undo', 'redo'
            ],
            fontSize: { options: [9, 11, 13, 'default', 17, 19, 21] },
            fontFamily: {
                options: [
                    'default',
                    'Arial, Helvetica, sans-serif',
                    'Courier New, Courier, monospace',
                    'Georgia, serif',
                    'Lucida Sans Unicode, Lucida Grande, sans-serif',
                    'Tahoma, Geneva, sans-serif',
                    'Times New Roman, Times, serif',
                    'Trebuchet MS, Helvetica, sans-serif',
                    'Verdana, Geneva, sans-serif'
                ]
            },
            image: { toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side'] },
            table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] }
        })
        .then(editor => {
            editorInstance = editor;
            loadDraft();
        })
        .catch(error => {
            console.error(error);
            alert('Editor failed to load. Check console.');
        });
});
</script>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>