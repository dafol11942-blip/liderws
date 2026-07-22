<?php
spl_autoload_register(function ($className) {
    $prefix = 'Lider\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return;
    }
    $relativeClass = substr($className, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
