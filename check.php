<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Step 1: PHP is running.<br>";

require_once 'config.php';
echo "Step 2: config.php loaded.<br>";

$conn = getDB();
echo "Step 3: Database connection successful.<br>";

include_once 'includes/header.php';
echo "Step 4: Header included.<br>";

include_once 'includes/vision_mission.php';
echo "Step 5: Vision/mission included.<br>";
?>