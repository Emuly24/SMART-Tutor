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
        $type = $_POST['type'];
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
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $book_title ?> - SMART Tutor Reader</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf_viewer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap');
        
        body {
            background: #f0f0f0;
            font-family: 'Inter', sans-serif;
        }

        /* --- Header --- */
        .reader-header {
            background: #1e293b;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .reader-header h2 { margin: 0; font-size: 1.5rem; }
        .reader-header .header-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        .reader-header a.btn-back {
            background: var(--accent);
            color: #1e293b;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .reader-header a.btn-back:hover { background: var(--accent-dark); transform: scale(1.02); }

        /* --- Viewer Container --- */
        #viewer-container {
            position: relative;
            background: #525659;
            border-radius: 1rem;
            padding: 1rem 0.5rem;
            margin-bottom: 6rem;
            overflow: hidden;
        }

        /* --- Scroll Container --- */
        #scroll-container {
            overflow-y: auto;
            overflow-x: hidden;
            max-height: 85vh;
            padding: 1rem 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            scroll-behavior: smooth;
            min-width: 800px;
            transform-origin: top center;
        }

        /* --- Page Wrapper --- */
        .page-wrapper {
            position: relative;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
            border-radius: 4px;
            width: 800px;
            height: auto;
            flex-shrink: 0;
            transform-origin: top center;
        }
        .page-wrapper canvas {
            width: 100%;
            height: auto;
            display: block;
        }
        .page-wrapper .textLayer {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            pointer-events: none;
            opacity: 0.2;
        }
        .page-wrapper .textLayer div {
            cursor: text;
            position: absolute;
            color: transparent;
            transition: color 0.2s;
        }
        .highlight { background: rgba(255,255,0,0.5); color: initial !important; }
        .underline { border-bottom: 2px solid red; color: initial !important; }

        /* --- Sticky Note --- */
        .sticky-note {
            position: absolute;
            background: #ffc;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: move;
            z-index: 100;
            font-size: 0.8rem;
            max-width: 160px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            pointer-events: auto;
        }
        .sticky-note .close {
            float: right;
            background: red;
            color: white;
            border: none;
            cursor: pointer;
            padding: 0 4px;
            font-size: 10px;
            line-height: 1.2;
            border-radius: 2px;
        }

        /* --- Floating Toolbar --- */
        .floating-toolbar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(20, 20, 35, 0.92);
            backdrop-filter: blur(8px);
            border-radius: 2rem;
            padding: 0.5rem 0.8rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
            justify-content: center;
            z-index: 2000;
            border: 1px solid rgba(255,255,255,0.1);
            max-width: 95%;
        }
        .floating-toolbar button {
            background: transparent;
            border: none;
            color: #e2e8f0;
            cursor: pointer;
            padding: 0.4rem 0.6rem;
            border-radius: 2rem;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .floating-toolbar button:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }
        .floating-toolbar .separator {
            width: 1px;
            height: 1.5rem;
            background: rgba(255,255,255,0.15);
            margin: 0 0.2rem;
        }
        .floating-toolbar input[type="number"] {
            width: 3.5rem;
            background: rgba(0,0,0,0.4);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 2rem;
            padding: 0.2rem 0.4rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .floating-toolbar select {
            background: rgba(0,0,0,0.4);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 2rem;
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }
        .floating-toolbar .tool-active {
            background: var(--accent) !important;
            color: #1e293b !important;
        }
        .floating-toolbar .tool-active:hover {
            background: var(--accent-dark) !important;
        }
        .floating-toolbar span.page-total {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* --- Responsive --- */
        @media (max-width: 1024px) {
            #scroll-container { min-width: auto; width: 100%; }
            .page-wrapper { width: 100%; max-width: 800px; }
            .floating-toolbar { padding: 0.4rem 0.6rem; gap: 0.2rem; }
            .floating-toolbar button { padding: 0.2rem 0.4rem; font-size: 0.8rem; }
        }
        @media (max-width: 768px) {
            .reader-header { flex-direction: column; align-items: stretch; text-align: center; }
            .reader-header .header-actions { justify-content: center; }
            .floating-toolbar { bottom: 10px; max-width: 98%; padding: 0.3rem 0.4rem; }
            .floating-toolbar button { font-size: 0.7rem; padding: 0.2rem 0.3rem; }
            .floating-toolbar input[type="number"] { width: 2.5rem; font-size: 0.8rem; }
            .floating-toolbar select { font-size: 0.7rem; padding: 0.1rem 0.2rem; }
            .floating-toolbar .separator { margin: 0 0.1rem; }
        }

        /* --- Scrollbar --- */
        #scroll-container::-webkit-scrollbar { width: 6px; }
        #scroll-container::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
        #scroll-container::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }
        #scroll-container::-webkit-scrollbar-thumb:hover { background: var(--accent-dark); }

        .text-center { text-align: center; }
        .mt-2 { margin-top: 1rem; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>
<body>
<div class="container" id="main-container">
    <!-- Header -->
    <div class="reader-header">
        <h2><i class="fas fa-book-open"></i> <?= $book_title ?></h2>
        <div class="header-actions">
            <button id="fullScreenBtn" class="btn-secondary" style="background: rgba(255,255,255,0.15); color: white; border: none; padding: 0.5rem 1rem; border-radius: 2rem; cursor: pointer;">
                <i class="fas fa-expand"></i> Fullscreen
            </button>
            <a href="library.php" class="btn-back">← Back to Library</a>
        </div>
    </div>

    <!-- Viewer -->
    <div id="viewer-container">
        <div id="scroll-container">
            <!-- Pages will be inserted here -->
        </div>
    </div>

    <!-- Floating Toolbar -->
    <div class="floating-toolbar" id="mainToolbar">
        <button id="viewFirst" title="First Page"><i class="fas fa-fast-backward"></i></button>
        <button id="viewPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <input type="number" id="pageInput" value="1" min="1">
        <span class="page-total">/ <span id="totalPages">1</span></span>
        <button id="viewNext" title="Next"><i class="fas fa-chevron-right"></i></button>
        <button id="viewLast" title="Last Page"><i class="fas fa-fast-forward"></i></button>
        <button id="askQuestionBtn" title="Ask admin about selected text"><i class="fas fa-question-circle"></i></button>

        <div class="separator"></div>
        <!-- Ask Question Modal -->
<div id="askQuestionDialog" title="Ask Admin About This Text" style="display:none;">
    <div class="form-group">
        <label>Selected text:</label>
        <div id="selectedTextDisplay" style="background:var(--card-alt-bg); padding:0.5rem; border-radius:0.5rem; margin-bottom:0.5rem; max-height:150px; overflow-y:auto;"></div>
    </div>
    <div class="form-group">
        <label for="questionInput">Your question:</label>
        <textarea id="questionInput" rows="3" cols="30" placeholder="What do you need help with?" style="width:100%;"></textarea>
    </div>
</div>

        <button id="zoomOut" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
        <select id="zoomSelect">
            <option value="0.25">25%</option>
            <option value="0.5">50%</option>
            <option value="0.75">75%</option>
            <option value="1">100%</option>
            <option value="1.25">125%</option>
            <option value="1.5" selected>150%</option>
            <option value="2">200%</option>
            <option value="3">300%</option>
        </select>
        <button id="fitWidth" title="Fit to Width"><i class="fas fa-expand-alt"></i></button>
        <button id="fitPage" title="Fit to Page"><i class="fas fa-arrows-alt"></i></button>
        <button id="zoomIn" title="Zoom In"><i class="fas fa-search-plus"></i></button>

        <div class="separator"></div>

        <button id="highlightBtn" title="Highlight"><i class="fas fa-highlighter"></i></button>
        <button id="underlineBtn" title="Underline"><i class="fas fa-underline"></i></button>
        <button id="stickyBtn" title="Sticky Note"><i class="fas fa-sticky-note"></i></button>
        <button id="eraseBtn" title="Erase"><i class="fas fa-eraser"></i></button>
        <button id="loadAnnotationsBtn" title="Load Annotations"><i class="fas fa-folder-open"></i></button>
    </div>
</div>

<!-- Sticky Note Modal -->
<div id="stickyDialog" title="Add Sticky Note" style="display:none;">
    <textarea id="stickyText" rows="3" cols="30" placeholder="Enter your note..." style="width:100%;"></textarea>
</div>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    let pdfDoc = null,
        pageWrappers = {},
        currentZoom = 1.5,
        currentTool = 'highlight',
        currentMode = 'scroll',
        totalPages = 0,
        pageBufferSize = 10,
        loadedPages = [];

    const container = document.getElementById('scroll-container');
    const pageInput = document.getElementById('pageInput');
    const totalPagesSpan = document.getElementById('totalPages');
    const zoomSelect = document.getElementById('zoomSelect');

    const bookId = <?= $book_id ?>;
    const userId = <?= $uid ?>;
    const url = '<?= $book['file_path'] ?>';

    // --- Render a single page ---
    function renderPage(num) {
        return new Promise((resolve) => {
            if (pageWrappers[num]) {
                resolve(pageWrappers[num]);
                return;
            }
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: 1 });
                const wrapper = document.createElement('div');
                wrapper.className = 'page-wrapper';
                wrapper.dataset.page = num;
                wrapper.style.width = '800px';
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.width = '100%';
                canvas.style.height = 'auto';
                wrapper.appendChild(canvas);

                const renderContext = { canvasContext: ctx, viewport: viewport };
                page.render(renderContext).promise.then(function() {
                    pageWrappers[num] = wrapper;
                    // Load annotations for this page
                    loadAnnotations(num);
                    resolve(wrapper);
                });
            });
        });
    }

    // --- Load pages (chunked) ---
    function loadPages(start, end) {
        const fragment = document.createDocumentFragment();
        let promises = [];
        for (let i = start; i <= end; i++) {
            if (i < 1 || i > totalPages) continue;
            if (!pageWrappers[i]) {
                promises.push(renderPage(i).then(wrapper => {
                    fragment.appendChild(wrapper);
                }));
            } else if (!container.contains(pageWrappers[i])) {
                fragment.appendChild(pageWrappers[i]);
            }
        }
        Promise.all(promises).then(() => {
            if (fragment.childNodes.length > 0) {
                container.appendChild(fragment);
            }
            loadedPages = Array.from(container.querySelectorAll('.page-wrapper')).map(el => parseInt(el.dataset.page));
            updateVisiblePage();
        });
    }

    // --- Clear container and reload ---
    function reloadPages() {
        container.innerHTML = '';
        let start = Math.max(1, parseInt(pageInput.value) - Math.floor(pageBufferSize / 2));
        let end = Math.min(totalPages, start + pageBufferSize - 1);
        loadPages(start, end);
        applyZoom(currentZoom);
    }

    // --- Scroll Detection (Infinite Scroll) ---
    container.addEventListener('scroll', function() {
        if (currentMode === 'scroll') {
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            if (scrollTop + clientHeight >= scrollHeight - 200) {
                // Load more pages
                let lastLoaded = Math.max(...loadedPages);
                if (lastLoaded < totalPages) {
                    let nextStart = lastLoaded + 1;
                    let nextEnd = Math.min(totalPages, nextStart + Math.floor(pageBufferSize / 2));
                    loadPages(nextStart, nextEnd);
                }
            }
            // Load pages above if scrolling near top
            if (scrollTop < 200) {
                let firstLoaded = Math.min(...loadedPages);
                if (firstLoaded > 1) {
                    let prevStart = Math.max(1, firstLoaded - Math.floor(pageBufferSize / 2));
                    let prevEnd = firstLoaded - 1;
                    loadPages(prevStart, prevEnd);
                }
            }
            updateVisiblePage();
        }
    });

    // --- Update visible page in input ---
    function updateVisiblePage() {
        const wrappers = container.querySelectorAll('.page-wrapper');
        let visiblePage = parseInt(pageInput.value);
        let minDist = Infinity;
        const containerRect = container.getBoundingClientRect();
        wrappers.forEach(wrapper => {
            const rect = wrapper.getBoundingClientRect();
            const dist = Math.abs(rect.top - containerRect.top);
            if (dist < minDist) {
                minDist = dist;
                visiblePage = parseInt(wrapper.dataset.page);
            }
        });
        if (visiblePage !== parseInt(pageInput.value)) {
            pageInput.value = visiblePage;
        }
    }

    // --- Navigate to page (Single Page Mode) ---
    function goToPage(num) {
        if (num < 1) num = 1;
        if (num > totalPages) num = totalPages;
        pageInput.value = num;
        if (currentMode === 'single') {
            reloadPages();
            // Scroll to the wrapper
            const targetWrapper = container.querySelector(`[data-page="${num}"]`);
            if (targetWrapper) {
                targetWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } else {
            const targetWrapper = container.querySelector(`[data-page="${num}"]`);
            if (targetWrapper) {
                targetWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                // Page not loaded yet, reload around it
                let start = Math.max(1, num - Math.floor(pageBufferSize / 2));
                let end = Math.min(totalPages, start + pageBufferSize - 1);
                container.innerHTML = '';
                loadPages(start, end).then(() => {
                    const wrapper = container.querySelector(`[data-page="${num}"]`);
                    if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        }
    }

    // --- Apply Zoom ---
    function applyZoom(zoomValue) {
        currentZoom = parseFloat(zoomValue);
        container.style.transform = `scale(${currentZoom})`;
        // Adjust container width to maintain scroll integrity
        container.style.width = `${100 / currentZoom}%`;
        container.style.transformOrigin = 'top center';
        // Update selection to match zoom
        if (currentZoom < 0.5) currentZoom = 0.5;
        if (currentZoom > 3) currentZoom = 3;
        zoomSelect.value = currentZoom;
    }

    // --- Fit to Width ---
    function fitToWidth() {
        const containerWidth = container.clientWidth;
        const pageWidth = 800;
        const scale = (containerWidth / pageWidth) * 0.95;
        applyZoom(scale);
        zoomSelect.value = scale;
    }

    // --- Fit to Page ---
    function fitToPage() {
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;
        const pageWidth = 800;
        const pageHeight = 1100;
        const scaleX = (containerWidth / pageWidth) * 0.95;
        const scaleY = (containerHeight / pageHeight) * 0.95;
        applyZoom(Math.min(scaleX, scaleY));
        zoomSelect.value = Math.min(scaleX, scaleY);
    }

    // --- Tool Selection ---
    function setActiveTool(toolId) {
        ['highlightBtn','underlineBtn','stickyBtn','eraseBtn'].forEach(id => {
            document.getElementById(id).classList.remove('tool-active');
        });
        document.getElementById(toolId).classList.add('tool-active');
    }

    // --- Annotations ---
    function loadAnnotations(pageNum) {
        $.post(`?action=load_annotations`, { page: pageNum }, function(res) {
            if (res.success && res.annotations) {
                const wrapper = container.querySelector(`[data-page="${pageNum}"]`);
                if (!wrapper) return;
                // Remove existing sticky notes on this page
                wrapper.querySelectorAll('.sticky-note').forEach(el => el.remove());
                res.annotations.forEach(ann => {
                    if (ann.annotation_type === 'sticky' && ann.position) {
                        addStickyNoteToDOM(pageNum, ann.id, ann.content, JSON.parse(ann.position));
                    }
                });
            }
        }, 'json');
    }

    // --- Add Sticky Note to DOM ---
    function addStickyNoteToDOM(pageNum, id, text, pos) {
        const wrapper = container.querySelector(`[data-page="${pageNum}"]`);
        if (!wrapper) return;
        const div = document.createElement('div');
        div.className = 'sticky-note';
        div.textContent = text;
        div.dataset.id = id;
        div.style.left = pos.x + 'px';
        div.style.top = pos.y + 'px';
        
        const closeBtn = document.createElement('button');
        closeBtn.className = 'close';
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteAnnotation(id, div);
        });
        div.appendChild(closeBtn);

        // Make draggable
        div.addEventListener('mousedown', function(e) {
            const startX = e.clientX;
            const startY = e.clientY;
            const origLeft = parseFloat(this.style.left);
            const origTop = parseFloat(this.style.top);
            const self = this;
            function onMove(ev) {
                self.style.left = (origLeft + (ev.clientX - startX)) + 'px';
                self.style.top = (origTop + (ev.clientY - startY)) + 'px';
            }
            function onUp(ev) {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                // Save new position
                const newPos = { 
                    x: parseFloat(self.style.left), 
                    y: parseFloat(self.style.top) 
                };
                $.post(`?action=save_annotation`, { 
                    id: id, 
                    position: JSON.stringify(newPos) 
                });
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        wrapper.appendChild(div);
    }

    // --- Delete Annotation ---
    function deleteAnnotation(id, element) {
        $.post(`?action=delete_annotation`, { id: id }, function(res) {
            if (res.success) {
                element.remove();
                showToast('Annotation deleted');
            }
        }, 'json');
    }

    // --- Save Annotation (highlight, underline, sticky) ---
    function saveAnnotation(type, content, position, pageNum) {
        $.post(`?action=save_annotation`, { 
            page: pageNum, 
            type: type, 
            content: content, 
            position: JSON.stringify(position) 
        }, function(res) {
            if (res.success) {
                showToast('Annotation saved');
                if (type === 'sticky') {
                    addStickyNoteToDOM(pageNum, res.id, content, position);
                }
            }
        }, 'json');
    }

    // --- Highlight / Underline via Text Selection ---
    document.addEventListener('mouseup', function() {
        if (currentTool !== 'highlight' && currentTool !== 'underline') return;
        const selection = window.getSelection();
        const text = selection.toString().trim();
        if (!text) return;
        const range = selection.getRangeAt(0);
        // Determine which page wrapper the selection is in
        const containerRect = container.getBoundingClientRect();
        const pageWrappers = container.querySelectorAll('.page-wrapper');
        let targetWrapper = null;
        pageWrappers.forEach(wrapper => {
            const rect = wrapper.getBoundingClientRect();
            if (rect.top <= range.getClientRects()[0].top && rect.bottom >= range.getClientRects()[0].bottom) {
                targetWrapper = wrapper;
            }
        });
        if (!targetWrapper) return;
        const pageNum = parseInt(targetWrapper.dataset.page);
        const rect = range.getBoundingClientRect();
        const wrapperRect = targetWrapper.getBoundingClientRect();
        const position = {
            x: rect.left - wrapperRect.left,
            y: rect.top - wrapperRect.top,
            width: rect.width,
            height: rect.height
        };
        saveAnnotation(currentTool, text, position, pageNum);
        selection.removeAllRanges();
    });

    // --- Sticky Note Click on Page ---
    $(document).on('click', '.page-wrapper', function(e) {
        if (currentTool !== 'sticky') return;
        // Prevent click if it's on a sticky note or its close button
        if (e.target.closest('.sticky-note') || e.target.closest('.close')) return;
        const wrapper = $(this);
        const pageNum = parseInt(wrapper.data('page'));
        const offset = wrapper.offset();
        const x = e.pageX - offset.left;
        const y = e.pageY - offset.top;
        $('#stickyDialog').dialog({
            modal: true,
            buttons: {
                "Save": function() {
                    const note = $('#stickyText').val();
                    if (note) {
                        saveAnnotation('sticky', note, { x: x, y: y }, pageNum);
                    }
                    $(this).dialog("close");
                },
                "Cancel": function() { $(this).dialog("close"); }
            }
        });
    });

    // --- Load PDF ---
    pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
        pdfDoc = pdfDoc_;
        totalPages = pdfDoc.numPages;
        totalPagesSpan.textContent = totalPages;
        pageInput.max = totalPages;
        // Initial load
        let start = 1;
        let end = Math.min(totalPages, pageBufferSize);
        container.innerHTML = '';
        loadPages(start, end).then(() => {
            applyZoom(1.5);
            zoomSelect.value = 1.5;
            // Scroll to page 1
            const firstWrapper = container.querySelector('[data-page="1"]');
            if (firstWrapper) firstWrapper.scrollIntoView({ block: 'start' });
        });
    }).catch(function(error) {
        console.error('Error loading PDF:', error);
        container.innerHTML = '<div style="color:white;padding:2rem;text-align:center;">Error loading PDF. Please ensure the file path is correct.</div>';
    });

    // --- Event Listeners ---
    document.getElementById('viewPrev').addEventListener('click', function() {
        let current = parseInt(pageInput.value);
        goToPage(current - 1);
    });
    document.getElementById('viewNext').addEventListener('click', function() {
        let current = parseInt(pageInput.value);
        goToPage(current + 1);
    });
    document.getElementById('viewFirst').addEventListener('click', function() {
        goToPage(1);
    });
    document.getElementById('viewLast').addEventListener('click', function() {
        goToPage(totalPages);
    });
    pageInput.addEventListener('change', function() {
        let val = parseInt(this.value);
        if (isNaN(val) || val < 1) val = 1;
        if (val > totalPages) val = totalPages;
        this.value = val;
        goToPage(val);
    });

    // Zoom controls
    document.getElementById('zoomIn').addEventListener('click', function() {
        let val = parseFloat(zoomSelect.value) + 0.25;
        if (val > 3) val = 3;
        applyZoom(val);
        zoomSelect.value = val;
    });
    document.getElementById('zoomOut').addEventListener('click', function() {
        let val = parseFloat(zoomSelect.value) - 0.25;
        if (val < 0.25) val = 0.25;
        applyZoom(val);
        zoomSelect.value = val;
    });
    zoomSelect.addEventListener('change', function() {
        applyZoom(this.value);
    });
    document.getElementById('fitWidth').addEventListener('click', fitToWidth);
    document.getElementById('fitPage').addEventListener('click', fitToPage);

    // Tool selection
    document.getElementById('highlightBtn').addEventListener('click', function() {
        currentTool = 'highlight';
        setActiveTool('highlightBtn');
    });
    document.getElementById('underlineBtn').addEventListener('click', function() {
        currentTool = 'underline';
        setActiveTool('underlineBtn');
    });
    document.getElementById('stickyBtn').addEventListener('click', function() {
        currentTool = 'sticky';
        setActiveTool('stickyBtn');
    });
    document.getElementById('eraseBtn').addEventListener('click', function() {
        currentTool = 'erase';
        setActiveTool('eraseBtn');
    });

    // Load annotations button
    document.getElementById('loadAnnotationsBtn').addEventListener('click', function() {
        const pages = container.querySelectorAll('.page-wrapper');
        pages.forEach(wrapper => {
            const pageNum = parseInt(wrapper.dataset.page);
            loadAnnotations(pageNum);
        });
        showToast('Annotations reloaded');
    });

    // Fullscreen
    document.getElementById('fullScreenBtn').addEventListener('click', function() {
        const container = document.getElementById('viewer-container');
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) {
            container.webkitRequestFullscreen();
        }
    });

    // --- Toast Notification ---
    function showToast(message) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; background: #1e293b; color: white;
            padding: 0.75rem 1.5rem; border-radius: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 3000; font-size: 0.9rem; border-left: 4px solid var(--accent);
            animation: slideInRight 0.3s ease forwards;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }
    let selectedTextForQuestion = '';
let selectedPageForQuestion = 0;
let selectedPositionForQuestion = null;

// Override mouseup to capture selected text for questions
document.addEventListener('mouseup', function() {
    const selection = window.getSelection();
    const text = selection.toString().trim();
    if (!text) {
        selectedTextForQuestion = '';
        return;
    }
    selectedTextForQuestion = text;
    // Find which page wrapper the selection is in
    const range = selection.getRangeAt(0);
    const pageWrappers = container.querySelectorAll('.page-wrapper');
    pageWrappers.forEach(wrapper => {
        const rect = wrapper.getBoundingClientRect();
        if (rect.top <= range.getClientRects()[0].top && rect.bottom >= range.getClientRects()[0].bottom) {
            selectedPageForQuestion = parseInt(wrapper.dataset.page);
            const wrapperRect = wrapper.getBoundingClientRect();
            selectedPositionForQuestion = {
                x: range.getClientRects()[0].left - wrapperRect.left,
                y: range.getClientRects()[0].top - wrapperRect.top,
                width: range.getClientRects()[0].width,
                height: range.getClientRects()[0].height
            };
        }
    });
});

// --- Ask Question Feature ---
let selectedTextForQuestion = '';
let selectedPageForQuestion = 0;

document.addEventListener('mouseup', function() {
    const selection = window.getSelection();
    const text = selection.toString().trim();
    if (!text) {
        selectedTextForQuestion = '';
        return;
    }
    selectedTextForQuestion = text;
    // Find which page wrapper the selection is in
    const range = selection.getRangeAt(0);
    const pageWrappers = container.querySelectorAll('.page-wrapper');
    pageWrappers.forEach(wrapper => {
        const rect = wrapper.getBoundingClientRect();
        if (rect.top <= range.getClientRects()[0].top && rect.bottom >= range.getClientRects()[0].bottom) {
            selectedPageForQuestion = parseInt(wrapper.dataset.page);
        }
    });
});

document.getElementById('askQuestionBtn').addEventListener('click', function() {
    if (!selectedTextForQuestion) {
        showToast('Please highlight some text first.');
        return;
    }
    $('#selectedTextDisplay').text(selectedTextForQuestion);
    $('#questionInput').val('');
    $('#askQuestionDialog').dialog({
        modal: true,
        width: 500,
        buttons: {
            "Send Question": function() {
                const question = $('#questionInput').val().trim();
                if (!question) {
                    showToast('Please write a question.');
                    return;
                }
                // Send to server
                $.post('save_book_question.php', {
                    book_id: bookId,
                    book_title: '<?= addslashes($book_title) ?>',
                    page_number: selectedPageForQuestion,
                    selected_text: selectedTextForQuestion,
                    question: question
                }, function(res) {
                    if (res.success) {
                        showToast('Your question has been sent to the admin.');
                        $(this).dialog("close");
                    } else {
                        showToast('Error: ' + (res.error || 'Unknown error'));
                    }
                }, 'json');
            },
            "Cancel": function() { $(this).dialog("close"); }
        }
    });
});
    // --- Animation Keyframes ---
    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    `;
    document.head.appendChild(styleSheet);
</script>
<div class="footer" style="margin-top: 2rem;">
    <a href="library.php" class="btn-back" style="display:inline-block; background:var(--accent); color:#1e293b; padding:0.6rem 1.5rem; border-radius:2rem; text-decoration:none; font-weight:600;">← Back to Library</a>
</div>
<!-- Ask Question Modal -->
<div id="askQuestionDialog" title="Ask Admin About This Text" style="display:none;">
    <div class="form-group">
        <label>Selected text:</label>
        <div id="selectedTextDisplay" style="background:var(--card-alt-bg); padding:0.5rem; border-radius:0.5rem; margin-bottom:0.5rem; max-height:150px; overflow-y:auto;"></div>
    </div>
    <div class="form-group">
        <label for="questionInput">Your question:</label>
        <textarea id="questionInput" rows="3" cols="30" placeholder="What do you need help with?" style="width:100%;"></textarea>
    </div>
</div>
</body>
</html>