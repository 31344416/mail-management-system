<?php
$configPath = $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';
$content = file_get_contents($configPath);
$tokens = token_get_all($content);
$errors = 0;
foreach ($tokens as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    if (is_array($token) && $token[0] === T_CLOSE_TAG) continue;
    if (is_array($token) && $token[0] === T_WHITESPACE) continue;
    if (is_array($token) && $token[0] === T_INLINE_HTML) {
        if (trim($token[1]) !== '') {
            echo "Inline HTML before <?php? Found: " . htmlspecialchars(substr($token[1], 0, 50)) . "<br>";
            $errors++;
        }
    }
}
if ($errors === 0) {
    echo "No stray HTML before <?php. Syntax might be OK.\n";
} else {
    echo "There are characters before the opening <?php tag. Remove them.\n";
}
?>