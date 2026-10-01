<?php

declare(strict_types=1);

// Zero-dependency test runner
spl_autoload_register(function ($class) {
    $prefixes = [
        'EidCloud\\PhpModernizer\\Tests\\' => __DIR__ . '/',
        'EidCloud\\PhpModernizer\\' => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

use EidCloud\PhpModernizer\Tests\ModernizerTest;

echo "\033[1;35m==============================================================\n";
echo "  🐘 Running EidCloud PHP Modernizer Test Suite (Zero-Dep)\n";
echo "==============================================================\033[0m\n\n";

$suite = new ModernizerTest();
$success = $suite->runAll();

exit($success ? 0 : 1);
