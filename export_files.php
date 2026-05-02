<?php
// export_files.php - collects all .php, .css, .js files into one text file
$output = "=== SMART TUTOR FILE EXPORT ===\n\n";
$exclude = ['export_files.php', 'style.css']; // we'll analyse style.css separately

function scanDirRecursive($dir, $basePath = '') {
    global $output, $exclude;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $full = $dir . '/' . $file;
        $rel = $basePath ? $basePath . '/' . $file : $file;
        if (is_dir($full)) {
            scanDirRecursive($full, $rel);
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, ['php', 'css', 'js']) && !in_array($file, $exclude)) {
                $output .= "\n\n========== FILE: $rel ==========\n\n";
                $output .= file_get_contents($full);
            }
        }
    }
}

scanDirRecursive(__DIR__);
file_put_contents('smarttutor_export.txt', $output);
echo "Export completed! Download smarttutor_export.txt from your server.";
?>