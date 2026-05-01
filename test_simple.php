<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "Step 1: PHP works.<br>";
require_once 'config.php';
echo "Step 2: config.php loaded.<br>";
$conn = getDB();
echo "Step 3: DB connected.<br>";
$result = $conn->query("SELECT * FROM users LIMIT 1");
if ($result) echo "Step 4: Users table accessible.<br>";
else echo "Step 4: Error: " . $conn->error . "<br>";
echo "Step 5: Trying to include admin_dashboard.php...<br>";
require_once 'admin_dashboard.php';
?>