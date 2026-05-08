<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$book_id = (int)$_GET['id'];
$book = $conn->query("SELECT id, title, subject, file_path FROM books WHERE id = $book_id")->fetch_assoc();
if (!$book) die("Book not found.");
$book_title = htmlspecialchars($book['title']);

// Handle AJAX actions (save/load annotations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $page = (int)($_POST['page'] ?? 0);
    if ($action === 'save_annotation') {
        $type = $_POST['type']; // highlight, underline, sticky
        $content = trim($_POST['content'] ?? '');
        $position = $_POST['position'] ?? '';
        $stmt = $conn->prepare("INSERT INTO book_annotations (user_id, book_id, page_number, annotation_type, content, position) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $uid, $book_id, $page, $type, $content, $position);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        exit;
    } elseif ($action === 'load_annotations') {
        $stmt = $conn->prepare("SELECT id, annotation_type, content, position FROM book_annotations WHERE user_id = ? AND book_id = ? AND page_number = ?");
        $stmt->bind_param("iii", $uid, $book_id, $page);
        $stmt->execute();
        $result = $stmt->get_result();
        $annotations = [];
        while ($row = $result->fetch_assoc()) {
            $annotations[] = $row;
        }
        echo json_encode(['success' => true, 'annotations' => $annotations]);
        exit;
    } elseif ($action === 'delete_annotation') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM book_annotations WHERE id = $id AND user_id = $uid");
        echo json_encode(['success' => true]);
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html><head>
    <title><?= $book_title ?> - SMART Tutor Reader</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf_viewer.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        body { background: var(--primary-light); }
        .reader-header {
            background: var(--primary-dark);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .annotation-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            background: var(--card-alt-bg);
            padding: 8px;
            border-radius: 8px;
        }
        .annotation-toolbar button { padding: 0.3rem 0.8rem; font-size: 0.8rem; }
        #viewer-container {
            background: #525659;
            padding: 20px;
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .pdf-controls {
            background: rgba(0,0,0,0.7);
            padding: 8px;
            border-radius: 2rem;
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
            color: white;
        }
        canvas {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            background: white;
            border-radius: 4px;
        }
        .textLayer {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            opacity: 0.2;
            line-height: 1;
        }
        .textLayer ::selection {
            background: rgba(212,175,55,0.4);
        }
        .highlight {
            background-color: rgba(255,255,0,0.5);
        }
        .underline {
            border-bottom: 2px solid red;
        }
        .sticky-note {
            position: absolute;
            background: #ffc;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: move;
            z-index: 100;
            font-size: 0.7rem;
            max-width: 150px;
        }
        .tool-active { background: var(--accent-dark) !important; color: white !important; }
        #stickyDialog textarea { width: 100%; margin-bottom: 10px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>
<body>
<div class="container">
    <div class="reader-header">
        <h2><i class="fas fa-book-open"></i> <?= $book_title ?></h2>
        <a href="library.php" class="btn-back">← Back to Library</a>
    </div>
    <div class="annotation-toolbar" id="toolbar">
        <button id="highlightBtn" class="btn-secondary">🟡 Highlight</button>
        <button id="underlineBtn" class="btn-secondary">📝 Underline</button>
        <button id="stickyBtn" class="btn-secondary">📌 Sticky Note</button>
        <button id="eraseBtn" class="btn-secondary">🗑️ Erase</button>
        <button id="saveAnnotationsBtn" class="btn-success">💾 Save All</button>
        <button id="loadAnnotationsBtn" class="btn-info">📂 Load</button>
    </div>
    <div id="viewer-container">
        <div class="pdf-controls">
            <button id="prev" class="btn-secondary">◀ Previous</button>
            <span>Page <span id="page_num">1</span> / <span id="page_count">?</span></span>
            <button id="next" class="btn-secondary">Next ▶</button>
        </div>
        <div id="canvas-container" style="position: relative;">
            <canvas id="pdf-canvas"></canvas>
            <div id="text-layer" class="textLayer"></div>
        </div>
    </div>
</div>

<div id="stickyDialog" title="Add Sticky Note" style="display:none;">
    <textarea id="stickyText" rows="3" cols="30" placeholder="Enter your note..."></textarea>
</div>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
    let canvas = document.getElementById('pdf-canvas');
    let ctx = canvas.getContext('2d');
    let currentPage = null;
    let currentTool = 'highlight';
    let currentAnnotations = [];
    let stickyCounter = 0;
    const bookId = <?= $book_id ?>;
    const userId = <?= $uid ?>;

    // Helper: render page
    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(function(page) {
            currentPage = page;
            const viewport = page.getViewport({ scale: scale });
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            const renderContext = { canvasContext: ctx, viewport: viewport };
            const renderTask = page.render(renderContext);
            renderTask.promise.then(function() {
                pageRendering = false;
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
            // Get text content for selection
            page.getTextContent().then(function(textContent) {
                const textLayerDiv = document.getElementById('text-layer');
                textLayerDiv.style.width = viewport.width + 'px';
                textLayerDiv.style.height = viewport.height + 'px';
                pdfjsLib.renderTextLayer({
                    textContent: textContent,
                    container: textLayerDiv,
                    viewport: viewport,
                    textDivs: []
                });
            });
        });
        document.getElementById('page_num').textContent = num;
    }

    function queueRenderPage(num) {
        if (pageRendering) { pageNumPending = num; } else { renderPage(num); }
    }
    function onPrevPage() { if (pageNum <= 1) return; pageNum--; queueRenderPage(pageNum); }
    function onNextPage() { if (pageNum >= pdfDoc.numPages) return; pageNum++; queueRenderPage(pageNum); }

    // Load PDF
    const url = "<?= $book['file_path'] ?>";
    pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
        pdfDoc = pdfDoc_;
        document.getElementById('page_count').textContent = pdfDoc.numPages;
        renderPage(pageNum);
    });
    document.getElementById('prev').addEventListener('click', onPrevPage);
    document.getElementById('next').addEventListener('click', onNextPage);

    // Tool selection
    document.getElementById('highlightBtn').addEventListener('click', () => { currentTool = 'highlight'; setActiveTool('highlightBtn'); });
    document.getElementById('underlineBtn').addEventListener('click', () => { currentTool = 'underline'; setActiveTool('underlineBtn'); });
    document.getElementById('stickyBtn').addEventListener('click', () => { currentTool = 'sticky'; setActiveTool('stickyBtn'); });
    document.getElementById('eraseBtn').addEventListener('click', () => { currentTool = 'erase'; setActiveTool('eraseBtn'); });
    function setActiveTool(activeId) {
        ['highlightBtn','underlineBtn','stickyBtn','eraseBtn'].forEach(id => {
            document.getElementById(id).classList.remove('tool-active');
        });
        document.getElementById(activeId).classList.add('tool-active');
    }

    // Handle text selection (highlight/underline)
    document.addEventListener('mouseup', function() {
        if (currentTool !== 'highlight' && currentTool !== 'underline') return;
        const selection = window.getSelection();
        const text = selection.toString().trim();
        if (!text) return;
        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        const containerRect = document.getElementById('canvas-container').getBoundingClientRect();
        const relativeTop = rect.top - containerRect.top;
        const relativeLeft = rect.left - containerRect.left;
        const position = JSON.stringify({
            x: relativeLeft, y: relativeTop, width: rect.width, height: rect.height
        });
        saveAnnotation(currentTool, text, position);
        selection.removeAllRanges();
    });

    // Sticky note: click on canvas
    $('#canvas-container').on('click', function(e) {
        if (currentTool !== 'sticky') return;
        const offset = $(this).offset();
        const x = e.pageX - offset.left;
        const y = e.pageY - offset.top;
        $('#stickyDialog').dialog({
            modal: true,
            buttons: {
                "Save": function() {
                    const note = $('#stickyText').val();
                    if (note) {
                        const position = JSON.stringify({ x: x, y: y });
                        saveAnnotation('sticky', note, position);
                    }
                    $(this).dialog("close");
                },
                "Cancel": function() { $(this).dialog("close"); }
            }
        });
    });

    function saveAnnotation(type, content, position) {
        $.post(`?action=save_annotation`, { page: pageNum, type: type, content: content, position: position }, function(res) {
            if (res.success) {
                showToast('Annotation saved', 'success');
                if (type === 'sticky') addStickyNote(res.id, content, JSON.parse(position));
            }
        }, 'json').fail(() => showToast('Error saving', 'error'));
    }

    function addStickyNote(id, text, pos) {
        const div = $('<div>').addClass('sticky-note').text(text).attr('data-id', id);
        div.css({ left: pos.x, top: pos.y });
        div.draggable({ containment: '#canvas-container', stop: function() {
            const newPos = { x: $(this).position().left, y: $(this).position().top };
            $.post(`?action=save_annotation`, { id: id, position: JSON.stringify(newPos) });
        }});
        div.append($('<button>×</button>').css({ float: 'right', background: 'red', color: 'white', border: 'none', cursor: 'pointer' }).click(function(e) {
            e.stopPropagation();
            deleteAnnotation(id, div);
        }));
        $('#canvas-container').append(div);
    }

    function deleteAnnotation(id, element) {
        $.post(`?action=delete_annotation`, { id: id }, function(res) {
            if (res.success) { element.remove(); showToast('Deleted', 'success'); }
        }, 'json');
    }

    // Load annotations
    $('#loadAnnotationsBtn').click(function() {
        $.post(`?action=load_annotations`, { page: pageNum }, function(res) {
            if (res.success && res.annotations) {
                // Remove existing sticky notes from DOM
                $('.sticky-note').remove();
                res.annotations.forEach(ann => {
                    if (ann.annotation_type === 'sticky' && ann.position) {
                        addStickyNote(ann.id, ann.content, JSON.parse(ann.position));
                    }
                    // For highlights/underlines, we could re-apply on text layer; for simplicity we rely on stored data
                });
                showToast('Annotations loaded', 'success');
            }
        }, 'json');
    });

    // Save all annotations (already saved individually, but you can implement bulk)
    $('#saveAnnotationsBtn').click(() => showToast('Annotations already saved individually', 'info'));

    function showToast(msg, type) { /* using your existing toast function */ if(window.showToast) showToast(msg, type); else alert(msg); }
</script>
<div class="footer"><a href="library.php" class="btn-back">← Back to Library</a></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>