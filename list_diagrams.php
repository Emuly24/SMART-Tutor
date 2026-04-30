<?php
$files = glob('uploads/diagrams/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
$result = [];
foreach ($files as $f) {
    $result[] = ['url' => $f, 'name' => basename($f)];
}
header('Content-Type: application/json');
echo json_encode($result);