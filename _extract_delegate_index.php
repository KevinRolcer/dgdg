<?php
$base = 'c:/laragon/www/segob';
$blade = "$base/resources/views/temporary_modules/delegate/index.blade.php";
$lines = file($blade, FILE_IGNORE_NEW_LINES);
$n = count($lines);
echo "total lines $n\n";
// 0-based: line 1038 = index 1037, line 5923 = index 5922
$js = array_slice($lines, 1037, 5922 - 1037 + 1);
$jsText = implode("\n", $js);
$jsText = preg_replace(
    '/^\\s*const DELEGATE_BOOT = @json\\([^;]+\\);/m',
    'const DELEGATE_BOOT = typeof window.TM_DELEGATE_BOOT !== \'undefined\' && window.TM_DELEGATE_BOOT !== null ? window.TM_DELEGATE_BOOT : {};',
    $jsText,
    1
);
file_put_contents("$base/public/assets/js/modules/temporary-modules-delegate-index.js", $jsText . "\n");
echo "js bytes " . strlen($jsText) . "\n";
