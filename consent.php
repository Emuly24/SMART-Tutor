<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$conn = getDB();
$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT fullname, class_level, school FROM users WHERE id=$uid")->fetch_assoc();
if (!$user) die("User not found.");

$u = $conn->query("SELECT consent_signed FROM users WHERE id=$uid")->fetch_assoc();
// === CHANGE START: Show agreement card instead of die() ===
if ($u['consent_signed']) {
    // Already signed – show the agreement card with download options
    $signed_date = $conn->query("SELECT consent_signed_at FROM users WHERE id=$uid")->fetch_assoc()['consent_signed_at'];
    $success = false; // not a fresh signature
} else {
    $success = false;
}
// === CHANGE END ===

function generateSignature($fullname) {
    $parts = explode(' ', $fullname);
    $surname = end($parts);
    $firstName = $parts[0];
    return substr($surname, 0, 1) . '. ' . $firstName;
}
$generated_signature = generateSignature($user['fullname']);

$success = false;
$signed_by = '';
$signed_date = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree'])) {
    $signed_by = trim($_POST['signed_by']);
    $signed_date = $_POST['signed_date'];
    $conn->query("UPDATE users SET consent_signed=1, consent_signed_at=NOW() WHERE id=$uid");
    $success = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Consent Form</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<?php include_once 'includes/progress_tracker.php'; ?>
<div class="consent-container">
    <?php if ($u['consent_signed'] && !$success): ?>
        <!-- Already signed – show agreement and download options -->
        <div class="success-card" id="successCard">
            <h2><i class="fas fa-check-circle"></i> Your Signed Consent Agreement</h2>
            <div class="student-details">
                <p><strong>Student:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
                <p><strong>Class:</strong> <?= htmlspecialchars($user['class_level']) ?></p>
                <p><strong>School:</strong> <?= htmlspecialchars($user['school']) ?></p>
                <p><strong>Signed on:</strong> <?= date('Y-m-d', strtotime($signed_date)) ?></p>
                <p><strong>Signature:</strong> <?= htmlspecialchars($generated_signature) ?></p>
            </div>
            <p>You have already agreed to the SMART Circle group rules. You can download or print a copy below.</p>
            <div class="success-actions">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <button onclick="printConsent()" class="btn-secondary">🖨️ Print Copy</button>
                <button onclick="downloadPDF()" class="btn-secondary">📄 Download PDF</button>
            </div>
        </div>
    <?php elseif ($success): ?>
        <!-- Just signed -->
        <div class="success-card" id="successCard">
            <h2><i class="fas fa-check-circle"></i> Agreement Confirmed</h2>
            <div class="student-details">
                <p><strong>Student:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
                <p><strong>Class:</strong> <?= htmlspecialchars($user['class_level']) ?></p>
                <p><strong>School:</strong> <?= htmlspecialchars($user['school']) ?></p>
                <p><strong>Signed on:</strong> <?= htmlspecialchars($signed_date) ?></p>
                <p><strong>Signature:</strong> <?= htmlspecialchars($signed_by) ?></p>
            </div>
            <p>You have successfully agreed to the SMART Circle group rules. This confirmation serves as your official commitment.</p>
            <div class="success-actions">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <button onclick="printConsent()" class="btn-secondary">🖨️ Print Copy</button>
                <button onclick="downloadPDF()" class="btn-secondary">📄 Download PDF</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Not signed yet – show form -->
        <h1><i class="fas fa-file-signature"></i> SMART Circle Consent Agreement</h1>
        <p>Dear <strong><?= htmlspecialchars($user['fullname']) ?></strong>, please read the following terms carefully. By signing this document, you commit to the rules below.</p>

        <h2>Commitments</h2>
        <ul class="agreement-list">
            <li>I will work hard and read extensively to improve my knowledge.</li>
            <li>I will be punctual for all sessions and respect the agreed schedule.</li>
            <li>I will respect my class teacher and peers at all times.</li>
            <li>I will not rely only on past papers but will engage fully with all learning materials.</li>
            <li>I will not engage in any financial or inappropriate exchanges. I understand this leads to dismissal.</li>
        </ul>

        <h2>Consequences of Breach</h2>
        <ul class="agreement-list">
            <li>A warning may be issued for minor violations.</li>
            <li>Extra assignments may be given as corrective measures.</li>
            <li>Suspension may occur, during which my content access will be locked.</li>
            <li>Permanent dismissal will result from serious or repeated violations.</li>
        </ul>

        <form method="post" class="consent-form">
            <div class="form-group">
                <label class="distinct-checkbox">
                    <input type="checkbox" name="agree" required>
                    <span>I hereby agree to abide by all rules and commitments stated above.</span>
                </label>
            </div>

            <div class="signature-section">
                <h3>Electronic Signature</h3>
                <div class="signature-line">
                    <label for="signed_by">Signed by (Full Name):</label>
                    <input type="text" id="signed_by" name="signed_by" value="<?= htmlspecialchars($generated_signature) ?>" required>
                </div>
                <div class="signature-line">
                    <label for="signed_date">Date of Signing:</label>
                    <input type="date" id="signed_date" name="signed_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <button type="submit" class="btn">Accept & Continue</button>
        </form>
    <?php endif; ?>
</div>
<div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
<?php include_once 'includes/testimonial_prompt.php'; ?>
</body>
<script>
    function printConsent() {
        const content = document.getElementById('successCard').innerHTML;
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Consent Agreement – SMART Circle</title><style>body{font-family:Arial,sans-serif;padding:20px;} .student-details{background:#f5f5f5;padding:10px;margin:15px 0;}</style></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('<div class="footer"><hr><p>SMART Circle – A digital learning community built for your future</p></div>');
        printWindow.document.close();
        printWindow.print();
    }
    async function downloadPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const pageWidth = doc.internal.pageSize.getWidth();
        const leftMargin = 20;
        const rightMargin = pageWidth - 20;
        let y = 20;

        // Header with SMART Circle colors
        doc.setFillColor(30, 42, 58);
        doc.rect(0, 0, pageWidth, 40, 'F');
        doc.setTextColor(212, 175, 55);
        doc.setFontSize(18);
        doc.text("SMART Circle Consent Agreement", leftMargin, 25);
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(10);
        doc.text("Discipline & Integrity", leftMargin, 35);
        doc.setTextColor(0, 0, 0);
        
        y = 50;
        doc.setLineWidth(0.5);
        doc.line(leftMargin, y, rightMargin, y);
        y += 10;
        doc.setFontSize(12);
        const text = "This document certifies that the student named below has read, understood, and agreed to the rules and commitments of the SMART Circle program.";
        const lines = doc.splitTextToSize(text, pageWidth - 40);
        doc.text(lines, leftMargin, y);
        y += lines.length * 6 + 10;
        
        doc.setFontSize(12);
        doc.setTextColor(30, 42, 58);
        doc.text("Student Information:", leftMargin, y);
        y += 8;
        doc.setFontSize(11);
        doc.text("Full Name: <?= addslashes($user['fullname']) ?>", leftMargin + 10, y);
        y += 7;
        doc.text("Class Level: <?= addslashes($user['class_level']) ?>", leftMargin + 10, y);
        y += 7;
        doc.text("School: <?= addslashes($user['school']) ?>", leftMargin + 10, y);
        y += 7;
        doc.text("Agreement Date: <?= addslashes($signed_date) ?>", leftMargin + 10, y);
        y += 12;
        
        doc.setFontSize(12);
        doc.text("The student agrees to:", leftMargin, y);
        y += 8;
        const rules = [
            "Work hard and read extensively to improve knowledge.",
            "Be punctual and respect the agreed schedule.",
            "Respect the teacher and peers at all times.",
            "Not rely solely on past papers but engage fully with materials.",
            "Never engage in financial or inappropriate exchanges (dismissal)."
        ];
        rules.forEach(line => {
            const bullet = "• " + line;
            const wrapped = doc.splitTextToSize(bullet, pageWidth - 40);
            doc.text(wrapped, leftMargin + 5, y);
            y += wrapped.length * 5 + 2;
        });
        y += 8;
        doc.text("Consequences of Breach:", leftMargin, y);
        y += 8;
        const cons = [
            "Warning for minor violations.",
            "Extra assignments as corrective measures.",
            "Suspension (content locked).",
            "Permanent dismissal for serious/repeated violations."
        ];
        cons.forEach(line => {
            const bullet = "• " + line;
            const wrapped = doc.splitTextToSize(bullet, pageWidth - 40);
            doc.text(wrapped, leftMargin + 5, y);
            y += wrapped.length * 5 + 2;
        });
        y += 10;
        doc.text("Electronic Signature: <?= addslashes($signed_by) ?>", leftMargin, y);
        y += 8;
        doc.text("Date: <?= addslashes($signed_date) ?>", leftMargin, y);
        y += 20;
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text("SMART Circle – A digital learning community built for your future", leftMargin, y);
        doc.save("Consent_Agreement_<?= preg_replace('/[^a-zA-Z0-9]/','_', $user['fullname']) ?>.pdf");
    }
</script>
</html>