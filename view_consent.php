<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$user = $conn->query("SELECT fullname, class_level, school, consent_signed, consent_signed_at FROM users WHERE id=$uid")->fetch_assoc();
if (!$user) die("User not found.");

if (!$user['consent_signed']) {
    // Not signed yet – redirect to consent form
    header("Location: consent.php");
    exit;
}

$signed_date = date('Y-m-d', strtotime($user['consent_signed_at']));
// Generate signature (same as in consent.php)
function generateSignature($fullname) {
    $parts = explode(' ', $fullname);
    $surname = end($parts);
    $firstName = $parts[0];
    return substr($surname, 0, 1) . '. ' . $firstName;
}
$signed_by = generateSignature($user['fullname']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Consent Agreement – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<?php include_once 'includes/progress_tracker.php'; ?>
<div class="container">
    <div class="consent-container">
        <div class="success-card" id="consentCard">
            <h2><i class="fas fa-check-circle"></i> Your Signed Consent Agreement</h2>
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
    </div>
</div>
<div class="footer"><a href="dashboard.php" class="btn-back">← Back</a></div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
<script>
    function printConsent() {
        const content = document.getElementById('consentCard').innerHTML;
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Consent Agreement – SMART Circle</title><style>body{font-family:Arial,sans-serif;padding:20px;} .student-details{background:#f5f5f5;padding:10px;margin:15px 0;}</style></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('<div class="footer"><hr><p>SMART Circle – Discipline & Integrity</p></div>');
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
        doc.setFillColor(30, 42, 58); // dark blue
        doc.rect(0, 0, pageWidth, 40, 'F');
        doc.setTextColor(212, 175, 55); // gold
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
        doc.text("SMART Circle – Discipline & Integrity", leftMargin, y);
        doc.save("Consent_Agreement_<?= preg_replace('/[^a-zA-Z0-9]/','_', $user['fullname']) ?>.pdf");
    }
</script>
</body>
</html>