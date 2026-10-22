<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($iterator as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if ($file->getExtension() !== 'php' || preg_match('~^(vendor|runtime)[/\\\\]~', $relative)) {
        continue;
    }
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
    if ($status !== 0) {
        exit($status);
    }
    $count++;
}
echo "PHP syntax check passed: $count files.\n";
