<?php
/**
 * Auto Version Bumper for XEPMARKET-ALFA
 */
$style_file = __DIR__ . '/style.css';
$functions_file = __DIR__ . '/functions.php';

// 1. Read style.css and bump version
if (file_exists($style_file)) {
    $content = file_get_contents($style_file);
    if (preg_match('/Version:\s*([\d\.]+)/', $content, $matches)) {
        $old_v = $matches[1];
        $parts = explode('.', $old_v);
        $parts[count($parts) - 1]++;
        $new_v = implode('.', $parts);

        $content = preg_replace('/Version:\s*[\d\.]+/', "Version: $new_v", $content);
        file_put_contents($style_file, $content);
        echo "Style.css version bumped: $old_v -> $new_v\n";

        // 2. Read functions.php and update constant
        if (file_exists($functions_file)) {
            $f_content = file_get_contents($functions_file);
            $f_content = preg_replace("/define\('XEPMARKET_ALFA_VERSION',\s*'[\d\.]+'\);/", "define('XEPMARKET_ALFA_VERSION', '$new_v');", $f_content);
            file_put_contents($functions_file, $f_content);
            echo "Functions.php version bumped to $new_v\n";
        }
    }
}
