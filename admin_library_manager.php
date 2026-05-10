<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();

// ─────────────────────────────────────────────────────────────
// ONE‑TIME INITIALIZATION: INSERT EXAMPLE EQUATIONS IF EMPTY
// ─────────────────────────────────────────────────────────────
$countEq = $conn->query("SELECT COUNT(*) FROM equations_library")->fetch_row()[0];
if ($countEq == 0) {
    $exampleEquations = [
        // Algebra
        ['Quadratic Formula', 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}', 'algebra'],
        ['Binomial Expansion', '(a+b)^2 = a^2 + 2ab + b^2', 'algebra'],
        ['Difference of Squares', 'a^2 - b^2 = (a-b)(a+b)', 'algebra'],
        // Calculus
        ['Derivative of x^n', '\\frac{d}{dx} x^n = n x^{n-1}', 'calculus'],
        ['Integral of x^n', '\\int x^n dx = \\frac{x^{n+1}}{n+1} + C', 'calculus'],
        ['Product Rule', '(fg)\' = f\'g + fg\'', 'calculus'],
        ['Chain Rule', '\\frac{dy}{dx} = \\frac{dy}{du} \\cdot \\frac{du}{dx}', 'calculus'],
        ['Fundamental Theorem of Calculus', '\\int_a^b f(x) dx = F(b)-F(a)', 'calculus'],
        // Trigonometry
        ['Pythagorean Identity', '\\sin^2 \\theta + \\cos^2 \\theta = 1', 'trigonometry'],
        ['Sine Double Angle', '\\sin 2\\theta = 2\\sin\\theta\\cos\\theta', 'trigonometry'],
        ['Cosine Double Angle', '\\cos 2\\theta = \\cos^2\\theta - \\sin^2\\theta', 'trigonometry'],
        ['Law of Sines', '\\frac{a}{\\sin A} = \\frac{b}{\\sin B} = \\frac{c}{\\sin C}', 'trigonometry'],
        // Logarithm
        ['Log Product', '\\log_b(xy) = \\log_b x + \\log_b y', 'logarithm'],
        ['Log Quotient', '\\log_b(\\frac{x}{y}) = \\log_b x - \\log_b y', 'logarithm'],
        ['Log Power', '\\log_b(x^r) = r \\log_b x', 'logarithm'],
        ['Change of Base', '\\log_b a = \\frac{\\log_c a}{\\log_c b}', 'logarithm'],
        // Chemistry
        ['Ideal Gas Law', 'PV = nRT', 'chemistry'],
        ['Arrhenius Equation', 'k = A e^{-E_a/(RT)}', 'chemistry'],
        ['Henderson–Hasselbalch', 'pH = pK_a + \\log\\left(\\frac{[A^-]}{[HA]}\\right)', 'chemistry'],
        // Physics
        ['Newton\'s Second Law', 'F = ma', 'physics'],
        ['Einstein\'s Mass‑Energy', 'E = mc^2', 'physics'],
        ['Ohm\'s Law', 'V = IR', 'physics'],
        ['Kinetic Energy', 'K = \\frac{1}{2}mv^2', 'physics'],
        ['Gravitational Force', 'F = G\\frac{m_1 m_2}{r^2}', 'physics']
    ];
    $stmt = $conn->prepare("INSERT INTO equations_library (title, latex, category) VALUES (?, ?, ?)");
    foreach ($exampleEquations as $eq) {
        $stmt->bind_param("sss", $eq[0], $eq[1], $eq[2]);
        $stmt->execute();
    }
}

// Process POST actions (add/edit/delete equations, upload diagram)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_equation'])) {
        $title = $_POST['title'];
        $latex = $_POST['latex'];
        $category = $_POST['category'];
        $conn->query("INSERT INTO equations_library (title, latex, category) VALUES ('$title', '$latex', '$category')");
        $msg = "Equation added.";
    } elseif (isset($_POST['edit_equation'])) {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $latex = $_POST['latex'];
        $category = $_POST['category'];
        $conn->query("UPDATE equations_library SET title='$title', latex='$latex', category='$category' WHERE id=$id");
        $msg = "Equation updated.";
    } elseif (isset($_POST['delete_equation'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM equations_library WHERE id=$id");
        $msg = "Equation deleted.";
    } elseif (isset($_FILES['diagram_image'])) {
        $title = $_POST['diagram_title'];
        $category = $_POST['diagram_category'];
        $dir = 'uploads/diagrams/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['diagram_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','svg'];
        if (!in_array($ext, $allowed)) {
            $msg = "Invalid image type.";
        } else {
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['diagram_image']['name']);
            $dest = $dir . $filename;
            if (move_uploaded_file($_FILES['diagram_image']['tmp_name'], $dest)) {
                $conn->query("INSERT INTO diagrams_library (title, file_path, category) VALUES ('$title', '$dest', '$category')");
                $msg = "Diagram uploaded.";
            } else {
                $msg = "Upload failed.";
            }
        }
    }
    header("Location: admin_library_manager.php?msg=" . urlencode($msg));
    exit;
}
if (isset($_GET['delete_diagram'])) {
    $id = (int)$_GET['delete_diagram'];
    $diag = $conn->query("SELECT file_path FROM diagrams_library WHERE id=$id")->fetch_assoc();
    if ($diag && file_exists($diag['file_path'])) unlink($diag['file_path']);
    $conn->query("DELETE FROM diagrams_library WHERE id=$id");
    header("Location: admin_library_manager.php?msg=Diagram deleted");
    exit;
}
$equations = $conn->query("SELECT * FROM equations_library ORDER BY category, title");
$diagrams = $conn->query("SELECT * FROM diagrams_library ORDER BY category, title");
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html><head><title>Library Manager</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>📚 Equation & Diagram Library</h1>
    <?php if ($msg) echo "<div class='success'>$msg</div>"; ?>
    <?php if ($countEq == 0) echo "<div class='success'>✅ Initial example equations have been added automatically.</div>"; ?>
    <div class="content-grid">
        <div class="card">
            <h3>➕ Add Equation / Formula</h3>
            <form method="post">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>LaTeX</label><textarea name="latex" rows="2" required placeholder="e.g., \log(x)"></textarea></div>
                <div class="form-group"><label>Category</label>
                    <select name="category">
                        <option value="algebra">Algebra</option><option value="calculus">Calculus</option>
                        <option value="trigonometry">Trigonometry</option><option value="logarithm">Logarithm</option>
                        <option value="chemistry">Chemistry</option><option value="physics">Physics</option>
                    </select>
                </div>
                <button type="submit" name="add_equation">Add Equation</button>
            </form>
            <hr>
            <h3>📐 Existing Equations</h3>
            <?php while($eq = $equations->fetch_assoc()): ?>
                <div style="border-bottom:1px solid #ccc; margin:10px 0; padding:5px;">
                    <strong><?= htmlspecialchars($eq['title']) ?></strong> (<?= $eq['category'] ?>)<br>
                    <code><?= htmlspecialchars($eq['latex']) ?></code>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                        <input type="text" name="title" value="<?= htmlspecialchars($eq['title']) ?>" size="15">
                        <textarea name="latex" rows="1"><?= htmlspecialchars($eq['latex']) ?></textarea>
                        <select name="category">
                            <?php foreach(['algebra','calculus','trigonometry','logarithm','chemistry','physics'] as $c): ?>
                                <option value="<?= $c ?>" <?= $c==$eq['category']?'selected':'' ?>><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="edit_equation">Edit</button>
                        <button type="submit" name="delete_equation" onclick="return confirm('Delete?')">Delete</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="card">
            <h3>🖼️ Upload Diagram</h3>
            <p><strong>Note:</strong> Start by uploading your own biological, physical, chemical or agricultural diagrams. After upload, they will appear in the editor’s "Diagram Library".</p>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Title</label><input type="text" name="diagram_title" required></div>
                <div class="form-group"><label>Image file</label><input type="file" name="diagram_image" accept="image/*" required></div>
                <div class="form-group"><label>Category</label>
                    <select name="diagram_category">
                        <option value="biology">Biology</option><option value="physics">Physics</option>
                        <option value="chemistry">Chemistry</option><option value="agriculture">Agriculture</option>
                    </select>
                </div>
                <button type="submit">Upload Diagram</button>
            </form>
            <hr>
            <h3>🖼️ Existing Diagrams</h3>
            <?php if ($diagrams->num_rows == 0): ?>
                <p>No diagrams yet. Use the form above to add your first diagram.</p>
            <?php else: ?>
                <?php while($d = $diagrams->fetch_assoc()): ?>
                    <div style="border-bottom:1px solid #ccc; margin:10px 0; padding:5px;">
                        <strong><?= htmlspecialchars($d['title']) ?></strong> (<?= $d['category'] ?>)<br>
                        <img src="<?= $d['file_path'] ?>" style="max-width:100px; max-height:100px;"><br>
                        <a href="?delete_diagram=<?= $d['id'] ?>" onclick="return confirm('Delete diagram?')">Delete</a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer"><a href="admin_dashboard.php">← Back</a></div>
</div>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>