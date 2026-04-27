<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head><title>About SMART Tutor</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-chalkboard-teacher"></i> About SMART Tutor</h1>
        <a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a>
    </div>
    <div class="note-container" style="background: rgba(255,248,240,0.7);">
        <div class="card" style="text-align: left;">
            <!-- Concise, professional personal bio -->
            <p><strong>Hello, I am Blessings Emulyn.</strong><br>
            I hold an Honours degree in Metallurgy and Materials Engineering from Malawi University of Science and Technology (MUST). I have always loved learning and feel genuine joy when I see students become passionate about their education.</p>

            <p>I believe education is more than a ticket to employment. It is the key to understanding the world and creating a better one for many. It empowers you to employ others or become a remarkable employee.</p>

            <p>I am committed to helping secondary school students, especially those in science subjects, who demonstrate self‑discipline, hard work, ambition, and dedication. I have successfully tutored Form 4 classes of 30–35 students of Kabekere  Scottish CDSS at Thambo Primary School (Ntcheu) in 2020 and 2021, and have taught individuals before that. Most of the subjects i tutor include Mathematics, English, Physics, Chemistry, Biology.</p>

            <p>Secondary school often defines a young person's future – some marry early, others drop out to start business without mature knowledge. I want to see Malawian youth walking into university halls and beyond.</p>

            <p>If you are disciplined, hardworking, and ambitious, I am here to help you master difficult topics and pursue your dreams. Welcome to the SMART Tutor family.</p>
            <p>– Blessings Emulyn</p>

            <hr>

            <h2>📌 Our Approach</h2>
            <p>We do <strong>not</strong> teach only to help you pass examinations. We teach to help you <strong>truly understand</strong> what is in your textbooks and how those ideas apply to the real world. Whether you become an engineer, a business owner, a farmer, or a teacher, the ability to think critically, solve problems, and learn on your own will always serve you.</p>
            <p>We <strong>discourage memorisation without comprehension</strong>. Every topic is explained with examples, exercises, and real‑life situations. If you do not achieve your desired grade or are not accepted into a public university, you will still leave with a strong foundation that can guide your self‑development and future goals. Education is not a lottery – it is a lifelong tool.</p>
            <p>Currently, I am the only tutor, but I plan to invite fellow educators who share this vision. Together, we can reach more students and help them build a future they are proud of.</p>
        </div>
    </div>
    <div class="footer"><a href="dashboard.php">← Back to Dashboard</a></div>
</div>
</body>
</html>