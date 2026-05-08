<?php
require_once 'check_remember_me.php';

require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$book_id = (int)$_GET['id'];

// Check if the book is borrowed by this user and not returned
$borrowed = $conn->query("SELECT * FROM borrowed_books WHERE user_id = $uid AND book_id = $book_id AND returned_at IS NULL");
if ($borrowed->num_rows == 0) {
    die("You have not borrowed this book. Please borrow it first from the library.");
}
$book = $borrowed->fetch_assoc();
$book_title = htmlspecialchars($book['book_title']);
$file_path = $book['file_path'];

// Get saved progress
$progress = $conn->query("SELECT last_page FROM reading_progress WHERE user_id = $uid AND book_id = $book_id")->fetch_assoc();
$saved_page = $progress ? (int)$progress['last_page'] : 1;
?>
<!DOCTYPE html>
<html><head>
    <title><?= $book_title ?> - SMART Tutor Reader</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <style>
        /* ... (same as before) ... */
        body {
            background: var(--primary-light);
        }
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
        .reader-header h2 { margin: 0; font-size: 1.2rem; }
        .pdf-controls {
            background: var(--card-alt-bg);
            padding: 10px;
            border-radius: 2rem;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
        }
        #viewer-container {
            background: #525659;
            padding: 20px;
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        canvas {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            background: white;
            border-radius: 4px;
        }
        .ask-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: var(--accent);
            color: #1e293b;
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            z-index: 2000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            font-weight: bold;
        }
        .ask-btn:hover { background: var(--accent-dark); }
        .question-modal {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            width: 320px;
            padding: 15px;
            z-index: 2000;
            display: none;
            border-left: 5px solid var(--accent);
        }
        .question-modal textarea { width: 100%; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="reader-header">
        <h2><i class="fas fa-book-open"></i> <?= $book_title ?></h2>
        <div>
            <span class="badge" style="background: var(--accent); color:#1e293b;">Borrowed until: <?= date('d M Y', strtotime($book['due_date'])) ?></span>
        </div>
    </div>
    <div id="viewer-container">
        <div class="pdf-controls">
            <button id="prev" class="btn-secondary">◀ Previous</button>
            <span>Page <span id="page_num">1</span> / <span id="page_count">?</span></span>
            <button id="next" class="btn-secondary">Next ▶</button>
        </div>
        <div id="pdf-canvas-container"></div>
    </div>
</div>
<div class="card review-section" style="margin-top: 20px;">
    <h3>Rate & Review this Book</h3>
    <form id="reviewForm">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">
        <div class="form-group">
            <label>Rating (1‑5 stars)</label>
            <select name="rating" required>
                <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                <option value="4">⭐⭐⭐⭐ (4)</option>
                <option value="3">⭐⭐⭐ (3)</option>
                <option value="2">⭐⭐ (2)</option>
                <option value="1">⭐ (1)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Review (optional)</label>
            <textarea name="review" rows="3"></textarea>
        </div>
        <button type="submit" class="btn">Submit Review</button>
    </form>
    <div id="reviewMessage"></div>
</div>
<script>
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('submit_book_review.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            document.getElementById('reviewMessage').innerHTML = data.message;
            if (data.success) this.reset();
        });
});
</script>

<div id="askBtn" class="ask-btn" style="display: none;">❓ Ask about selected text</div>
<div id="questionModal" class="question-modal">
    <h4>Ask a question</h4>
    <div><strong>Selected text:</strong> <span id="selectedText"></span></div>
    <textarea id="questionText" rows="3" placeholder="Write your question here..."></textarea>
    <button id="submitQuestion" class="btn">Submit Question</button>
    <button id="closeModal" class="btn-secondary">Cancel</button>
</div>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    let pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        scale = 1.5,
        canvasContainer = document.getElementById('pdf-canvas-container');
    let currentPage = null;
    const bookId = <?= $book_id ?>;
    const savedPage = <?= $saved_page ?>;

    function saveCurrentPage() {
        fetch('save_reading_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'book_id=' + bookId + '&page=' + pageNum
        }).catch(err => console.log('Save failed', err));
    }

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(function(page) {
            currentPage = page;
            const viewport = page.getViewport({ scale: scale });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            canvasContainer.innerHTML = '';
            canvasContainer.appendChild(canvas);
            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };
            const renderTask = page.render(renderContext);
            renderTask.promise.then(function() {
                pageRendering = false;
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });
        document.getElementById('page_num').textContent = num;
        // Save progress after render
        saveCurrentPage();
    }

    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }

    function onPrevPage() {
        if (pageNum <= 1) return;
        pageNum--;
        queueRenderPage(pageNum);
    }

    function onNextPage() {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        queueRenderPage(pageNum);
    }

    const url = "<?= $file_path ?>";
    pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
        pdfDoc = pdfDoc_;
        document.getElementById('page_count').textContent = pdfDoc.numPages;
        // Start at saved page if valid and not beyond total pages
        let startPage = savedPage;
        if (startPage < 1) startPage = 1;
        if (startPage > pdfDoc.numPages) startPage = 1;
        pageNum = startPage;
        renderPage(pageNum);
    });

    document.getElementById('prev').addEventListener('click', onPrevPage);
    document.getElementById('next').addEventListener('click', onNextPage);

    // Save progress before page unload (e.g., close tab)
    window.addEventListener('beforeunload', function() {
        saveCurrentPage();
    });

    // Text selection & questions (same as before)
    let selectedText = '';
    const askBtn = document.getElementById('askBtn');
    const modal = document.getElementById('questionModal');
    const selectedTextSpan = document.getElementById('selectedText');
    const questionTextarea = document.getElementById('questionText');
    
    document.addEventListener('mouseup', function() {
        const selection = window.getSelection();
        const text = selection.toString().trim();
        if (text.length > 0) {
            selectedText = text;
            selectedTextSpan.textContent = selectedText.substring(0, 150);
            askBtn.style.display = 'flex';
        } else {
            askBtn.style.display = 'none';
            modal.style.display = 'none';
        }
    });

    askBtn.addEventListener('click', function() {
        modal.style.display = 'block';
    });

    document.getElementById('closeModal').addEventListener('click', function() {
        modal.style.display = 'none';
        questionTextarea.value = '';
    });

    document.getElementById('submitQuestion').addEventListener('click', function() {
        const question = questionTextarea.value.trim();
        if (question === '') {
            alert('Please write your question.');
            return;
        }
        fetch('book_questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                book_id: <?= $book_id ?>,
                book_title: '<?= addslashes($book_title) ?>',
                page: pageNum,
                selected_text: selectedText,
                question: question
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Your question has been submitted to the admin.');
                modal.style.display = 'none';
                questionTextarea.value = '';
                selectedText = '';
                askBtn.style.display = 'none';
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => {
            alert('Network error. Please try again.');
        });
    });
</script>
<div class="footer"><a href="library.php" class="btn-back">← Back to Library</a></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>