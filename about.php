<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>About SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="about-page">
    <?php include_once 'includes/header.php'; ?>

<div class="container">
    <div class="card" style="border-top: 5px solid var(--accent); padding: 2.5rem;">
        <!-- Hero Section -->
        <div class="text-center" style="margin-bottom: 2rem;">
            <div style="width: 120px; height: 120px; background: var(--accent); border-radius: 50%; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: white;">
                <i class="fas fa-users"></i>
            </div>
            <h1 style="color: var(--accent); margin-bottom: 0.5rem;">About Us</h1>
            <p style="font-size: 1.2rem; color: var(--text-muted);">Discipline &amp; Integrity – Empowering Malawi's Future Leaders Together</p>
        </div>

        <hr style="border: 0; border-top: 2px solid var(--card-alt-bg); margin: 2rem 0;">

        <!-- Our Story (Founder introduced here) -->
        <div style="margin-bottom: 2.5rem;">
            <h2><i class="fas fa-seedling"></i> Our Story</h2>
            <p>SMART Circle was founded by <strong>Blessings Emulyn</strong>, a graduate of Metallurgy and Materials Engineering from the Malawi University of Science and Technology (MUST). He has always found genuine joy in teaching and witnessing students discover their potential.</p>
            <p>We believe that education is far more than a path to a job. It is the fundamental tool that empowers you to understand the world, create solutions, and build a life you are proud of. Whether you become an engineer, a business owner, a teacher, or a farmer, the ability to think critically, solve problems, and learn independently will always serve you.</p>
            <p>If you are disciplined, hardworking, and ambitious, we are here to help you master difficult topics and pursue your dreams. <br><strong>Welcome to the SMART Circle family.</strong></p>
        </div>

        <!-- Mission & Vision -->
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-bottom: 2.5rem;">
            <div class="card glass" style="background: var(--card-alt-bg);">
                <h3><i class="fas fa-bullseye"></i> Our Mission</h3>
                <p>To provide free, discipline‑based tutoring to secondary school students in Malawi, focusing on Mathematics, English, Physics, Chemistry, and Biology. We aim to turn hardworking students into confident, independent learners who can excel in exams and beyond.</p>
            </div>
            <div class="card glass" style="background: var(--card-alt-bg);">
                <h3><i class="fas fa-eye"></i> Our Vision</h3>
                <p>To see every young person in Malawi walk into a university hall or start a meaningful career equipped with the knowledge, discipline, and ambition to make a real difference in their communities.</p>
            </div>
        </div>

        <!-- Our Approach -->
        <div style="margin-bottom: 2.5rem;">
            <h2><i class="fas fa-lightbulb"></i> Our Approach</h2>
            <p>We do <strong>not</strong> teach only to help you pass examinations. We teach to help you <strong>truly understand</strong> what is in your textbooks and how those ideas apply to the real world.</p>
            <ul style="padding-left: 1.5rem; margin: 1rem 0;">
                <li><strong>Critical thinking</strong> – Every topic is explained with examples, exercises, and real‑life situations.</li>
                <li><strong>No rote memorisation</strong> – We encourage genuine comprehension over superficial recall.</li>
                <li><strong>Lifelong tools</strong> – Even if you do not achieve your desired grade, you will leave with a strong foundation that can guide your self‑development.</li>
                <li><strong>Discipline and hard work</strong> – We partner with students who are committed to their own growth.</li>
            </ul>
        </div>

        <!-- Commitment to Students -->
        <div style="background: var(--card-alt-bg); padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem;">
            <h3><i class="fas fa-handshake"></i> Our Commitment</h3>
            <p>We are building a community of passionate tutors and mentors who share a common vision. Whether you are here as a student, a tutor, or an educator, you are part of the SMART Circle family.</p>
        </div>

        <div class="text-center" style="margin-top: 1.5rem;">
            <a href="index.php" class="btn-back">← Back to Home</a>
            <a href="signup.php" class="btn" style="margin-left: 0.5rem;">Join SMART Circle</a>
        </div>
    </div>

    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>

<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>