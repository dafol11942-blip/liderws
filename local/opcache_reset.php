<?php
header('Content-Type: text/plain; charset=utf-8');
$ok = function_exists('opcache_reset') ? opcache_reset() : false;
echo $ok ? "OPCACHE_RESET_OK\n" : "OPCACHE_RESET_FAIL_OR_DISABLED\n";
if (function_exists('opcache_get_status')) {
    $st = opcache_get_status(false);
    echo 'enabled=' . (!empty($st['opcache_enabled']) ? '1' : '0') . "\n";
}
// touch stage2
$f = $_SERVER['DOCUMENT_ROOT'] . '/parts-search/stage2_search.php';
if (is_file($f)) touch($f);
echo "touched stage2\n";
echo "time=" . date('c') . "\n";
