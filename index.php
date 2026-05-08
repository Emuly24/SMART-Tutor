<?php
require_once 'check_remember_me.php';
 session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>SMART Tutor – Empowering Malawi's Youth</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>

    <!-- Hero Section (no button) -->
    <div class="hero-section">
        <h1>Empowering Malawi’s Secondary Students</h1>
        <p>SMART Tutor is a free, discipline‑based tutoring platform designed to help hardworking students master challenging subjects through small study groups, practical examples, and real‑world applications.</p>
        <p class="promise"><strong>Our promise:</strong> No money, no favours – only punctuality, hard work, and respect.</p>
    </div>

    <!-- Vision, Mission, Goals -->
    <?php include_once 'includes/vision_mission.php'; ?>

    <!-- Single Get Started button after V/M/G -->
    <div class="get-started-wrapper" style="text-align: center; margin: 2rem 0;">
        <button id="mainGetStartedBtn" class="btn-hero">Get Started</button>
    </div>

    <!-- Testimonials Section (hidden by default, shown only if testimonials exist) -->
    <div id="testimonialsSection" class="testimonials-section" style="display: none;">
        <h2><i class="fas fa-star"></i> What Our Students Say</h2>
        <div id="testimonialContainer" class="testimonial-slide"></div>
    </div>

    <div class="footer">
        <a href="login.php">Login</a> | <a href="signup.php">Sign Up</a>
    </div>
</div>

<!-- Eligibility Modal (unchanged) -->
<div id="eligibilityModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><i class="fas fa-clipboard-list"></i> Am I Eligible?</h2>
        <p>To join SMART Tutor, you must meet the following criteria:</p>
        <ul class="eligibility-list">
            <li><i class="fas fa-check-circle"></i> Be in <strong>Form 3 or Form 4</strong> (secondary school)</li>
            <li><i class="fas fa-check-circle"></i> Live within <strong>Sharpevalley area</strong> or be willing to commute to the designated tutoring place</li>
            <li><i class="fas fa-check-circle"></i> Be <strong>hardworking, disciplined, and respectful</strong></li>
            <li><i class="fas fa-check-circle"></i> Have a genuine desire to improve your grades</li>
            <li><i class="fas fa-check-circle"></i> Commit to punctuality and active participation</li>
        </ul>
        <p>If you meet all the above, we welcome you! Click below to create your account.</p>
        <div class="modal-buttons">
            <a href="signup.php" class="btn">Yes, I'm Eligible – Sign Up</a>
            <button id="closeModalBtn" class="btn-secondary">Not Now</button>
        </div>
    </div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>

<script>
    // Show/hide testimonials and auto‑rotate
    let testimonials = [];
    let currentIndex = 0;
    let interval;
    const section = document.getElementById('testimonialsSection');
    const container = document.getElementById('testimonialContainer');

    function fetchTestimonials() {
        fetch('get_testimonials.php')
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    // No testimonials → hide the whole section
                    section.style.display = 'none';
                    return;
                }
                // Show the section and populate
                section.style.display = 'block';
                testimonials = data;
                showTestimonial(0);
                startRotation();
            })
            .catch(err => {
                console.error('Error fetching testimonials:', err);
                section.style.display = 'none';
            });
    }

    function showTestimonial(index) {
        const t = testimonials[index];
        const html = `<div class="testimonial-card">
            <div class="testimonial-rating">${'⭐'.repeat(t.rating)}</div>
            <p class="testimonial-text">"${escapeHtml(t.testimonial)}"</p>
            <p class="testimonial-author">– ${escapeHtml(t.fullname)}, ${escapeHtml(t.class_level)}</p>
        </div>`;
        container.style.opacity = '0';
        setTimeout(() => {
            container.innerHTML = html;
            container.style.opacity = '1';
        }, 300);
    }

    function startRotation() {
        if (interval) clearInterval(interval);
        interval = setInterval(() => {
            currentIndex = (currentIndex + 1) % testimonials.length;
            showTestimonial(currentIndex);
        }, 8000);
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Modal handling (single button)
    const modal = document.getElementById('eligibilityModal');
    const getStartedBtn = document.getElementById('mainGetStartedBtn');
    const closeSpan = document.querySelector('#eligibilityModal .close');
    const closeBtn = document.getElementById('closeModalBtn');

    function openModal() { modal.style.display = 'flex'; }
    getStartedBtn.addEventListener('click', openModal);
    closeSpan.addEventListener('click', () => modal.style.display = 'none');
    closeBtn.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    // Load testimonials on page load
    fetchTestimonials();
</script>
</body>
</html>