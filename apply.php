<?php
require_once 'config.php';
require_once 'check_access.php'; // ensures logged in
$conn = getDB();
$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT approved FROM users WHERE id=$uid")->fetch_assoc();
if ($user['approved']) { header("Location: dashboard.php"); exit; }

$error = $success = '';
$universities = ["University of Malawi (UNIMA)","Mzuzu University (MZUNI)","Lilongwe University of Agriculture and Natural Resources (LUANAR)","Malawi University of Business and Applied Sciences (MUBAS)","Kamuzu University of Health Sciences (KUHeS)","Malawi University of Science and Technology (MUST)","DMI St. John the Baptist University","Catholic University of Malawi"];
$all_subjects = ['Mathematics','English','Chemistry','Physics','Biology','History','Agriculture'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ambition = trim($_POST['ambition']);
    $career_reason = trim($_POST['career_reason']);
    $university = $_POST['university'];
    $why_join = trim($_POST['why_join']);
    $subject_assist = $_POST['subject_assist'];
    $target_points = (int)$_POST['target_points'];
    $seriousness = json_encode([
        'hardworking' => isset($_POST['serious_hardworking']),
        'read_extensively' => isset($_POST['serious_read']),
        'attend_regularly' => isset($_POST['serious_attend']),
        'respect_rules' => isset($_POST['serious_respect'])
    ]);
    if (empty($ambition) || empty($career_reason) || empty($university) || empty($why_join) || empty($subject_assist)) {
        $error = "Please fill all required fields.";
    } elseif ($target_points > 20) {
        $error = "🌟 Your target points ($target_points) are above 20. We believe you can aim for ≤20. Please adjust and resubmit.";
    } else {
        $existing = $conn->query("SELECT id FROM applications WHERE user_id=$uid");
        if ($existing->num_rows) {
            $conn->query("UPDATE applications SET ambition='$ambition', career_reason='$career_reason', university='$university', why_join='$why_join', subject_assist='$subject_assist', target_points=$target_points, seriousness_answers='$seriousness', status='pending' WHERE user_id=$uid");
        } else {
            $conn->query("INSERT INTO applications (user_id, ambition, career_reason, university, why_join, subject_assist, target_points, seriousness_answers) VALUES ($uid, '$ambition', '$career_reason', '$university', '$why_join', '$subject_assist', $target_points, '$seriousness')");
        }
        $success = "Application submitted! Wait for admin approval.";
        @mail(ADMIN_EMAIL, "New Application", "User ID $uid submitted application.", "From:noreply@yoursite.com");
    }
}
?>
<!DOCTYPE html><html><head><title>Application Form</title><link rel="stylesheet" href="style.css"></head><body class="apply-page"><div class="apply-container"><h1>Complete Your Application</h1><?php if($error) echo "<div class='error'>$error</div>"; if($success) echo "<div class='success'>$success <a href='dashboard.php'>Back to Dashboard</a></div>"; if(!$success):?><form method="post"><?php foreach([['ambition','What career would you like to pursue?','text'],['career_reason','Why do you want that career?','textarea'],['university','Which public university?','select'],['why_join','Why join this group?','textarea'],['subject_assist','Which subject need most help?','select']] as $f){ echo "<div class='form-group'><label>".$f[1]." *</label>".($f[2]=='textarea'?"<textarea name='{$f[0]}' required></textarea>":($f[2]=='select'?"<select name='{$f[0]}' required>".($f[0]=='university'?implode('',array_map(function($u){return "<option>$u</option>";},$universities)):implode('',array_map(function($s){return "<option>$s</option>";},$all_subjects)))."</select>":"<input type='text' name='{$f[0]}' required>"))."</div>"); }?>
<div class="form-group"><label>Target MSCE points (≤20) *</label><input type="number" name="target_points" min="0" max="20" required></div>
<div class="checkbox-group"><label><input type="checkbox" name="serious_hardworking"> I am hardworking</label><label><input type="checkbox" name="serious_read"> I will read extensively</label><label><input type="checkbox" name="serious_attend"> I will attend on time</label><label><input type="checkbox" name="serious_respect"> I will respect others</label></div>
<button type="submit">Submit Application</button></form><?php endif;?></div></body></html>