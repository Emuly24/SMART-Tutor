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
$last_note_id = 0;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $class = trim($_POST['class_level']);
    $content = trim($_POST['content']);
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;
    
    if (empty($title) || empty($subject) || empty($content)) {
        die("Missing required fields.");
    }

    $title = $conn->real_escape_string($title);
    $subject = $conn->real_escape_string($subject);
    $class = $conn->real_escape_string($class);
    $content = $conn->real_escape_string($content);
    
    // Check if note already exists (using unique key)
    $existing = $conn->query("SELECT id FROM notes WHERE title='$title' AND subject='$subject' AND class_level='$class'");
    if ($existing->num_rows > 0) {
        // Update the existing note
        $row = $existing->fetch_assoc();
        $note_id = $row['id'];
        $conn->query("UPDATE notes SET content='$content', created_at=NOW() WHERE id=$note_id");
    } else {
        // Insert new note
        $conn->query("INSERT INTO notes (title, subject, class_level, content) VALUES ('$title', '$subject', '$class', '$content')");
        $note_id = $conn->insert_id;
    }

    $conn->query("DELETE FROM note_drafts");

    // If the admin clicked "Finish", redirect to locking page
    if (isset($_POST['finish'])) {
        header("Location: admin_group_locks.php?content_type=note&content_id=$note_id&class_level=" . urlencode($class));
        exit;
    }

    // If just saving as draft, stay on the page
    $msg = "Note saved (ID: $note_id)";
    echo "<script>window.noteId = $note_id; alert('$msg');</script>";
}
?>
<!DOCTYPE html>
<html><head><title>Note Editor</title>
<link rel="stylesheet" href="style.css">
<!-- TinyMCE -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.7.0/tinymce.min.js"></script>

<!-- MathQuill CSS & JS (Custom panel will use this) -->
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

<!-- Cropper (for diagrams) -->
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.css">
<style>
    .ck-editor__editable { min-height: 600px; width: 100% !important; }
    .ck-editor { width: 100% !important; }
    .ck-editor__editable p { text-align: justify; }
    .toolbar-extras { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; background: var(--card-alt-bg); padding: 10px; border-radius: 8px; }
    .toolbar-extras button, .toolbar-extras select { background: var(--accent); color: #1e293b; border: none; padding: 6px 14px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .toolbar-extras button:hover, .toolbar-extras select:hover { background: var(--accent-dark); transform: scale(1.02); }
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
    .tox-tinymce { min-height: 600px !important; }
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
            <button type="button" id="templateBtn">🧩 Templates</button>
        </div>
        <div class="form-group"><label>Content</label><textarea name="content" id="editor"></textarea></div>
        
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <button type="submit" class="btn">💾 Save Draft</button>
            <button type="submit" name="finish" class="btn btn-finish">✅ Finish & Lock</button>
        </div>
    </form>
</div>
<div class="footer"><a href="admin_notes_list.php" class="btn-back">← Back to Notes</a></div>
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

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // ---------- TinyMCE CORE ----------
tinymce.init({
    selector: '#editor',
    height: 600,
    menubar: true,
    // Remove 'mathquill' from plugins – it's not a real plugin, we're using a custom button
    plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
    toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | specialchars | charmap | code | mathquill',
    content_style: 'body { font-family: Inter, sans-serif; }',
    // Remove the old mathquill config – it's incompatible
    // mathquill: { version: 'editable' },
    setup: function(editor) {
        // ---------- MODERN MATHQUILL PANEL (Fully Integrated) ----------
        editor.ui.registry.addButton('mathquill', {
            text: '∫',
            tooltip: 'Insert Math Equation (MathQuill)',
            onAction: function() {
                const panel = editor.windowManager.open({
                    title: 'MathQuill Equation Editor',
                    width: 700,
                    height: 200,
                    body: {
                        type: 'panel',
                        items: [
                            {
                                type: 'htmlpanel',
                                html: `
                                    <div style="
                                        padding: 10px;
                                        text-align: center;
                                        background: var(--card-bg, #ffffff);
                                        border-radius: 12px;
                                        border: 2px solid var(--accent, #d4af37);
                                    ">
                                        <div id="mathquill-editor" style="
                                            background: transparent;
                                            padding: 10px;
                                            font-size: 1.5rem;
                                            min-height: 60px;
                                            color: var(--text-color, #000);
                                        "></div>
                                    </div>
                                `
                            }
                        ]
                    },
                    buttons: [
                        { type: 'submit', text: 'Insert Equation', primary: true },
                        { type: 'cancel', text: 'Cancel' }
                    ],
                    onAction: function(api) {
                        const latex = this.mathField ? this.mathField.latex() : '';
                        if (latex) {
                            editor.execCommand('mceMathQuill', false, latex);
                        }
                        api.close();
                    },
                    onClose: function() {
                        if (this.mathField) this.mathField.destroy();
                    },
                    onOpen: function() {
                        const mathFieldSpan = document.getElementById('mathquill-editor');
                        if (mathFieldSpan && typeof MathQuill !== 'undefined') {
                            const MQ = MathQuill.getInterface(2);
                            this.mathField = MQ.MathField(mathFieldSpan, {
                                spaceBehavesLikeTab: true,
                                handlers: {
                                    edit: function() {} // live preview optional
                                }
                            });
                            setTimeout(() => this.mathField.focus(), 100);
                        }
                    }
                });
            }
        });

        // ---------- AUTO-SAVE DRAFT ----------
        setInterval(function() {
            saveDraft(editor);
        }, 30000);

        editor.addShortcut('Ctrl+S', 'Save Draft', function() {
            saveDraft(editor);
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

    // ---------- TEMPLATE LIBRARY ----------
    const templateBtn = document.getElementById('templateBtn');
    const templateModal = document.getElementById('templateModal');

    if (templateBtn && templateModal) {
        templateBtn.addEventListener('click', function() {
            templateModal.style.display = 'flex';
        });

        const closeTemplate = templateModal.querySelector('.close');
        if (closeTemplate) {
            closeTemplate.addEventListener('click', function() {
                templateModal.style.display = 'none';
            });
        }

        window.addEventListener('click', function(event) {
            if (event.target === templateModal) {
                templateModal.style.display = 'none';
            }
        });

        setTimeout(() => {
            const copyBtns = templateModal.querySelectorAll('.btn-copy');
            copyBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const pre = this.previousElementSibling;
                    let text = pre.textContent;
                    if (text.includes('```mermaid')) {
                        text = text.split('```mermaid')[1].split('```')[0].trim();
                    } else if (text.includes('```')) {
                        text = text.split('```')[1].split('```')[0].trim();
                    }
                    if (tinymce.activeEditor) {
                        tinymce.activeEditor.insertContent(text);
                        showToast('✅ Template inserted!');
                    } else {
                        alert('Click inside the editor first.');
                    }
                });
            });
        }, 500);
    }

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

    // ---------- EQUATION HELPER ----------
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
            tinymce.activeEditor.execCommand('mceMathQuill', false, latex);
            mathHelperModal.style.display = 'none';
            latexHelperInput.value = '';
            if (mathHelperPreview) {
                mathHelperPreview.innerHTML = '';
            }
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
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>