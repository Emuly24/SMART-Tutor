<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level'];
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
if (!$subject) die("No subject specified.");

// Fetch unlocked notes for this subject
$notes = $conn->query("SELECT n.id, n.title, n.created_at 
    FROM notes n 
    WHERE n.class_level='$class' AND n.subject='$subject'
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='note' AND gcl.content_id=n.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY n.title");

// Fetch unlocked assignments for this subject (if any)
$assignments = $conn->query("SELECT a.id, a.title, a.due_date, 
    (SELECT submitted_at FROM assignment_submissions WHERE assignment_id=a.id AND user_id=$uid) as submitted,
    (SELECT marks FROM assignment_submissions WHERE assignment_id=a.id AND user_id=$uid) as marks
    FROM assignments a 
    WHERE a.class_level='$class' AND a.subject='$subject'
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='assignment' AND gcl.content_id=a.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY a.due_date");

// Fetch unlocked quizzes for this subject
$quizzes = $conn->query("SELECT q.id, q.title, q.description, q.time_limit
    FROM quizzes q 
    JOIN notes n ON q.note_id = n.id
    WHERE n.class_level='$class' AND n.subject='$subject'
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='quiz' AND gcl.content_id=q.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY q.title");
?>
<!DOCTYPE html>
<html><head><title><?= htmlspecialchars($subject) ?> - SMART Tutor</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container">

<div class="content-grid">
    <!-- Notes Section -->
    <div class="card"><h3>📖 Notes</h3><?php if($notes->num_rows): ?><?php while($n=$notes->fetch_assoc()):?><p><strong><?=htmlspecialchars($n['title'])?></strong><br><a href="view_note.php?id=<?=$n['id']?>">Read Note</a></p><?php endwhile; ?><?php else: ?><p>No notes unlocked yet.</p><?php endif; ?></div>
    
    <!-- Assignments Section -->
    <div class="card"><h3>📋 Assignments</h3><?php if($assignments->num_rows): ?><?php while($a=$assignments->fetch_assoc()):?><p><strong><?=htmlspecialchars($a['title'])?></strong> (Due: <?=$a['due_date']?>)<br><?php if($a['submitted']):?>Submitted on <?=$a['submitted']?><?php if($a['marks']!==null) echo " - Marks: {$a['marks']}";?><?php else:?><a href="submit_assignment.php?assignment_id=<?=$a['id']?>">Submit</a><?php endif;?></p><?php endwhile; ?><?php else: ?><p>No assignments yet.</p><?php endif; ?></div>
    
    <!-- Quizzes Section -->
    <div class="card"><h3>📝 Quizzes</h3><?php if($quizzes->num_rows): ?><?php while($q=$quizzes->fetch_assoc()): 
        $attempt = $conn->query("SELECT status FROM quiz_attempts WHERE user_id=$uid AND quiz_id={$q['id']}")->fetch_assoc();
        $link = ($attempt && $attempt['status']=='submitted') ? "quiz_results.php?quiz_id={$q['id']}" : "take_quiz.php?quiz_id={$q['id']}";
        $text = ($attempt && $attempt['status']=='submitted') ? "View Results" : "Take Quiz";
    ?><p><strong><?=htmlspecialchars($q['title'])?></strong><br><a href="<?=$link?>"><?=$text?></a></p><?php endwhile; ?><?php else: ?><p>No quizzes yet.</p><?php endif; ?></div>
</div>

<div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>