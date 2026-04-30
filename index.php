<?php session_start(); ?>
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

    <!-- Hero Section -->
    <div class="hero-section">
        <h1>Empowering Malawi’s Secondary Students</h1>
        <p>SMART Tutor is a free, discipline‑based tutoring platform designed to help hardworking students master challenging subjects through small study groups, practical examples, and real‑world applications.</p>
        <p class="promise"><strong>Our promise:</strong> No money, no favours – only punctuality, hard work, and respect.</p>
        <a href="signup.php" class="btn-hero">Get Started</a>
    </div>

    <!-- Vision, Mission, Goals -->
    <?php include_once 'includes/vision_mission.php'; ?>

    <div class="footer">
        <a href="login.php">Login</a> | <a href="signup.php">Sign Up</a>
    </div>
</div>


</body>
</html>