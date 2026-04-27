<?php
require_once 'config.php'; require_once 'check_access.php'; $conn=getDB(); $user_id=$_SESSION['user_id']; $message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $type=$_POST['report_type']; $description=trim($_POST['description']); $incident_date=$_POST['incident_date']??date('Y-m-d');
    if(empty($description)) $message="Please explain the situation.";
    else{
        $stmt=$conn->prepare("INSERT INTO student_reports (user_id, report_type, description, incident_date) VALUES (?,?,?,?)");
        $stmt->bind_param("isss",$user_id,$type,$description,$incident_date);
        if($stmt->execute()) $message="Thank you for your honesty. Your report has been submitted.";
        else $message="Database error.";
    }
}?>
<!DOCTYPE html><html><head><title>Submit a Report</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="header"><h1>📝 Submit a Report</h1><a href="dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div><div class="form-container"><?php if($message) echo "<p>$message</p>";?><form method="post"><div class="form-group"><label>Report Type</label><select name="report_type" required><option value="lateness">I was late</option><option value="poor_performance">I am not doing well in class/tests</option><option value="disrespect">I disrespected a fellow student or teacher</option><option value="other">Other issue</option></select></div><div class="form-group"><label>Date of incident</label><input type="date" name="incident_date" value="<?=date('Y-m-d')?>"></div><div class="form-group"><label>Explanation / Description</label><textarea name="description" rows="6" required placeholder="Please explain what happened, why, and what you will do to improve..."></textarea></div><button type="submit">Submit Report</button></form><div class="footer"><a href="dashboard.php">← Back</a></div></div></div></body></html>
