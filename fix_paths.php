<?php
$files = array_merge(glob("*.php"), glob("admin_*.php"));
$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $new = preg_replace('/(require_once|include_once)\s+__DIR__\s*\.\s*[\'"]\.\.\/config\.php[\'"]/', '\1 \'config.php\'', $content);
    $new = preg_replace('/(require_once|include_once)\s+[\'"]\.\.\/config\.php[\'"]/', '\1 \'config.php\'', $new);
    if ($new !== $content) {
        file_put_contents($file, $new);
        echo "Fixed: $file\n";
        $count++;
    }
}
echo "Fixed $count files. Delete this script now.\n";
?>