<?php
if (!isset($_SESSION['user_id'])) return;
$uid = $_SESSION['user_id'];
$conn = getDB();

$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$context = [
    'exam_results' => ['question' => 'How did your exam go?', 'example' => 'I improved my score from 60% to 85% after using SMART Circle!'],
    'submit_quiz'   => ['question' => 'How was the quiz?', 'example' => 'The quiz helped me understand photosynthesis much better.'],
    'view_note'     => ['question' => 'How is your reading progressing?', 'example' => 'The note on algebra made everything click for me.'],
    'dashboard'     => ['question' => 'How are you finding the SMART Circle program?', 'example' => 'I love the small group discussions – they really help.'],
    'consent'       => ['question' => 'Ready to commit to your success?', 'example' => 'I’m excited to start this journey!'],
    'profile'       => ['question' => 'How has SMART Circle helped you grow?', 'example' => 'My confidence in Maths has soared.'],
];
$default = ['question' => 'How is your learning journey?', 'example' => 'SMART Circle has been a game‑changer for my studies.'];
$ctx = $context[$currentPage] ?? $default;

// Progress checks
$has_testimonial = $conn->query("SELECT id FROM testimonials WHERE user_id=$uid AND status IN ('approved','pending')")->num_rows > 0;
$should_prompt = false;

if ($has_testimonial) {
    $last_testimonial = $conn->query("SELECT created_at FROM testimonials WHERE user_id=$uid AND status='approved' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
    if ($last_testimonial) {
        $since = time() - strtotime($last_testimonial['created_at']);
        $new_milestone = $conn->query("SELECT COUNT(*) FROM exercise_attempts WHERE user_id=$uid AND status='marked' AND updated_at > '{$last_testimonial['created_at']}'")->fetch_row()[0];
        if ($since > 2592000 && $new_milestone > 5) $should_prompt = true;
    }
} else {
    $completed_exercises = $conn->query("SELECT COUNT(*) FROM exercise_attempts WHERE user_id=$uid AND status='marked'")->fetch_row()[0];
    $completed_quizzes = $conn->query("SELECT COUNT(*) FROM quiz_attempts WHERE user_id=$uid AND status='submitted'")->fetch_row()[0];
    if (($completed_exercises + $completed_quizzes) >= 5) $should_prompt = true;
}

$prompt_shown = $conn->query("SELECT testimonial_prompt_shown FROM users WHERE id=$uid")->fetch_assoc()['testimonial_prompt_shown'] ?? 0;
if (!$should_prompt || $prompt_shown) return;

$conn->query("UPDATE users SET testimonial_prompt_shown=1 WHERE id=$uid");
?>

<div id="testimonialPrompt" class="testimonial-prompt hidden-prompt">
    <h4><i class="fas fa-star"></i> <?= htmlspecialchars($ctx['question']) ?></h4>
    <div class="pill-options">
        <button class="pill-option" data-response="It helped me a lot!">👍 It helped me a lot!</button>
        <button class="pill-option" data-response="I'm improving steadily">📈 I'm improving steadily</button>
        <button class="pill-option" data-response="Still struggling, but trying">💪 Still struggling, but trying</button>
        <button class="pill-option" data-response="Needs more explanation">❓ Needs more explanation</button>
    </div>
    <div id="testimonialExample" class="testimonial-example">
        💡 Example: “<?= htmlspecialchars($ctx['example']) ?>”
    </div>
    <div class="card-buttons">
        <a href="submit_testimonial.php" id="writeTestimonialBtn" class="btn">✍️ Write Testimonial</a>
        <button id="dismissTestimonialPrompt" class="btn-secondary">Remind Me Later</button>
    </div>
</div>

<script>
    const promptDiv = document.getElementById('testimonialPrompt');
    if (promptDiv && <?= json_encode($currentPage === 'view_note') ?>) {
        promptDiv.classList.remove('hidden-prompt');
        promptDiv.style.display = 'none';
        let revealed = false;
        window.addEventListener('scroll', function() {
            if (revealed) return;
            const scrollPercent = (window.scrollY + window.innerHeight) / document.body.scrollHeight;
            if (scrollPercent > 0.8) {
                promptDiv.style.display = 'block';
                revealed = true;
            }
        });
    } else if (promptDiv) {
        promptDiv.classList.remove('hidden-prompt');
        promptDiv.style.display = 'block';
    }

    const pills = document.querySelectorAll('.pill-option');
    const exampleDiv = document.getElementById('testimonialExample');
    let selectedResponse = '';
    pills.forEach(btn => {
        btn.addEventListener('click', function() {
            pills.forEach(p => p.classList.remove('active-pill'));
            this.classList.add('active-pill');
            selectedResponse = this.getAttribute('data-response');
            exampleDiv.innerHTML = `💡 You selected: “${selectedResponse}”<br>✏️ Turn this into a testimonial: “${selectedResponse} <?= addslashes($ctx['example']) ?>”`;
        });
    });
    const writeBtn = document.getElementById('writeTestimonialBtn');
    writeBtn.addEventListener('click', function(e) {
        if (selectedResponse) {
            const testimonialText = selectedResponse + ' ' + "<?= addslashes($ctx['example']) ?>";
            sessionStorage.setItem('prefill_testimonial', testimonialText);
        }
    });
    document.getElementById('dismissTestimonialPrompt')?.addEventListener('click', function() {
        fetch('dismiss_testimonial_prompt.php', { method: 'POST' })
            .then(() => promptDiv.style.display = 'none');
    });
</script>