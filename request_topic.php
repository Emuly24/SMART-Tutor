<?php
require_once 'config.php'; require_once 'check_access.php'; $conn=getDB(); $uid=$_SESSION['user_id']; $class=$_SESSION['class_level']; $subjects=['Mathematics','English','Chemistry','Physics','Biology','History','Agriculture']; $current=[]; $res=$conn->query("SELECT subject,topic FROM topic_requests WHERE user_id=$uid"); while($r=$res->fetch_assoc()) $current[$r['subject']]=$r['topic']; $warning=''; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $subject=$_POST['subject']; $topic=trim($_POST['topic']);
    if(isset($_POST['check_topic'])){
        $check=$conn->query("SELECT covered_date FROM topics_covered WHERE class_level='$class' AND subject='$subject' AND topic='$topic'");
        if($check->num_rows) $warning="⚠️ This topic was covered on ".$check->fetch_assoc()['covered_date'].". You can still request it but priority may be lower.";
        else $warning="✅ This topic is new.";
    }elseif(isset($_POST['submit_request'])){
        if(empty($topic)) $msg="Please enter a topic.";
        else{
            $stmt=$conn->prepare("INSERT INTO topic_requests (user_id,subject,topic,class_level) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE topic=VALUES(topic), updated_at=NOW()");
            $stmt->bind_param("isss",$uid,$subject,$topic,$class); $stmt->execute(); $msg="Request saved."; $current[$subject]=$topic;
        }
    }
}?>
<!DOCTYPE html><html><head><title>Request Topic</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><div class="form-container"><p><a href="covered_topics.php">View already covered topics</a></p><?php if($msg) echo "<p class='text-center'>$msg</p>"; if($warning) echo "<p class='text-center'>$warning</p>";?><form method="post"><div class="form-group"><label>Subject</label><select name="subject"><?php foreach($subjects as $s) echo "<option value='$s' ".((isset($current[$s])&&$_POST['subject']==$s)?'selected':'').">$s</option>";?></select></div><div class="form-group"><label>Specific Topic</label><textarea name="topic" rows="3"><?=htmlspecialchars($current[$_POST['subject']??'']??'')?></textarea></div><button type="submit" name="check_topic">Check if covered</button><button type="submit" name="submit_request">Submit Request</button></form><div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div></div></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>