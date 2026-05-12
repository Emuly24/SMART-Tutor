<?php
// includes/toc_navigator.php – Advanced Table of Contents Navigator
// Include this once at the bottom of any file, right before </body>
?>
<div id="tocFloatingBtn" title="Navigate Sections">
    <i class="fas fa-list-ul"></i>
</div>
<div id="tocPanel">
    <div class="toc-header">
        <span>📑 Jump to Section</span>
        <button id="tocCloseBtn">&times;</button>
    </div>
    <div id="tocList"></div>
    <div class="toc-footer">
        <button id="tocBackToTop">⬆ Back to Top</button>
    </div>
</div>

<style>
/* ============================================
   TOC Navigator – Styling
   ============================================ */
#tocFloatingBtn {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 50px;
    height: 50px;
    background: var(--accent);
    color: #1e293b;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1);
    opacity: 0;
    transform: scale(0.8);
    pointer-events: none;
}
#tocFloatingBtn.visible {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}
#tocFloatingBtn:hover {
    background: var(--accent-dark);
    transform: scale(1.05);
}
#tocFloatingBtn:active {
    transform: scale(0.95);
}

#tocPanel {
    position: fixed;
    bottom: 85px;
    right: 20px;
    width: 320px;
    max-width: 90vw;
    max-height: 70vh;
    background: var(--card-bg);
    border-radius: 1rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    z-index: 999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--card-alt-bg);
    transform-origin: bottom right;
    animation: tocSlideUp 0.25s ease;
}
#tocPanel.open {
    display: flex;
}

@keyframes tocSlideUp {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.toc-header {
    background: var(--primary-dark);
    color: white;
    padding: 0.8rem 1.2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 1rem 1rem 0 0;
    flex-shrink: 0;
}
.toc-header span { font-weight: 600; font-size: 0.95rem; }
.toc-header button {
    background: transparent;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0 0.3rem;
    transition: transform 0.2s;
}
.toc-header button:hover { transform: rotate(90deg); }

#tocList {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
    scrollbar-width: thin;
}
#tocList::-webkit-scrollbar { width: 4px; }
#tocList::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }

.toc-item {
    padding: 0.5rem 1.2rem;
    cursor: pointer;
    transition: background 0.15s ease;
    font-size: 0.9rem;
    color: var(--text-color);
    border-left: 3px solid transparent;
}
.toc-item:hover {
    background: var(--card-alt-bg);
    border-left-color: var(--accent);
}
.toc-item.level-h2 { font-weight: 600; }
.toc-item.level-h3 { padding-left: 2rem; font-weight: 400; }
.toc-item.level-h4 { padding-left: 3rem; font-weight: 300; font-size: 0.85rem; }

.toc-footer {
    padding: 0.6rem 1.2rem;
    border-top: 1px solid var(--card-alt-bg);
    flex-shrink: 0;
}
#tocBackToTop {
    width: 100%;
    padding: 0.5rem;
    background: var(--accent);
    color: #1e293b;
    border: none;
    border-radius: 2rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
#tocBackToTop:hover { background: var(--accent-dark); }
</style>

<script>
;(function() {
    'use strict';

    const btn = document.getElementById('tocFloatingBtn');
    const panel = document.getElementById('tocPanel');
    const listEl = document.getElementById('tocList');
    const closeBtn = document.getElementById('tocCloseBtn');
    const backToTopBtn = document.getElementById('tocBackToTop');

    // --- Helper: Check if an element is actually visible ---
    function isElementVisible(el) {
        // Check the element and all its ancestors
        while (el) {
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
                return false;
            }
            el = el.parentElement;
        }
        return true;
    }

    // --- 1. Scan the DOM for visible headings ---
    function scanHeadings() {
        // Select only headings inside .container or .note-container to avoid header/footer
        const containers = document.querySelectorAll('.container, .note-container, .admin-note-container, .student-note-container, .apply-container, .consent-container, .login-container, .signup-container');
        let headings = [];
        containers.forEach(container => {
            const found = container.querySelectorAll('h2, h3, h4');
            found.forEach(h => {
                // Skip if heading is empty or inside a hidden parent
                if (h.textContent.trim().length === 0) return;
                if (!isElementVisible(h)) return;
                headings.push(h);
            });
        });

        // Fallback: If no containers found, scan the whole body but exclude header/footer
        if (headings.length === 0) {
            const excludedTags = ['header', 'nav', 'footer', '.top-nav', '.footer', '.header'];
            document.querySelectorAll('h2, h3, h4').forEach(h => {
                let parent = h.parentElement;
                while (parent) {
                    if (excludedTags.some(tag => parent.matches && parent.matches(tag) || parent.tagName.toLowerCase() === tag)) {
                        return; // Skip this heading
                    }
                    parent = parent.parentElement;
                }
                // Also check visibility
                if (!isElementVisible(h)) return;
                headings.push(h);
            });
        }
        return headings;
    }

    // --- 2. Build the TOC list ---
    function buildTOC(headings) {
        listEl.innerHTML = '';
        if (headings.length === 0) {
            listEl.innerHTML = '<div style="padding:1rem;text-align:center;color:var(--text-muted);">No visible sections found.</div>';
            return;
        }
        headings.forEach((h, index) => {
            const level = h.tagName.toLowerCase();
            const item = document.createElement('div');
            item.className = `toc-item level-${level}`;
            item.textContent = h.textContent.trim();
            // Generate a unique id if missing to allow scrolling
            if (!h.id) {
                h.id = `toc-section-${index}`;
            }
            item.addEventListener('click', function() {
                h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                panel.classList.remove('open');
            });
            listEl.appendChild(item);
        });
    }

    // --- 3. Initialization ---
    function init() {
        const headings = scanHeadings();
        buildTOC(headings);
    }

    // --- 4. Show/hide floating button on scroll ---
    function checkScroll() {
        if (window.scrollY > 400) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
            panel.classList.remove('open');
        }
    }

    // --- 5. Event Listeners ---
    btn.addEventListener('click', function() {
        if (panel.classList.contains('open')) {
            panel.classList.remove('open');
        } else {
            // Refresh list in case content changed (e.g. dynamic notes)
            const headings = scanHeadings();
            buildTOC(headings);
            panel.classList.add('open');
        }
    });

    closeBtn.addEventListener('click', function() {
        panel.classList.remove('open');
    });

    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        panel.classList.remove('open');
    });

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (panel.classList.contains('open') && !panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('open');
        }
    });

    // --- 6. Initialize ---
    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('scroll', checkScroll);
    window.addEventListener('load', function() {
        // Re-scan after all assets load (e.g., after MathJax renders content)
        setTimeout(init, 500);
    });
})();
</script>