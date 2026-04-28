<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$command = $argv[1] ?? 'build';
$dist = $root . '/dist';
$package = $dist . '/noextlinks';

function removePath(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function copyPath(string $source, string $target): void
{
    if (is_dir($source)) {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $target . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }

        return;
    }

    $targetDir = dirname($target);

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    copy($source, $target);
}

function buildPackage(string $root, string $package): void
{
    removePath($package);
    mkdir($package, 0777, true);

    copyPath($root . '/src/PlgSystemNoExtLinks.php', $package . '/noextlinks.php');
    copyPath($root . '/src/noextlinks.xml', $package . '/noextlinks.xml');
    copyPath($root . '/src/noextlinks.js', $package . '/noextlinks.js');
    copyPath($root . '/language', $package . '/language');
    copyPath($root . '/src/Support', $package . '/Support');
    copyPath($root . '/src/services', $package . '/services');
}

function zipPackage(string $package, string $zipPath): void
{
    removePath($zipPath);

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Unable to create ' . $zipPath);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $relativePath = substr($item->getPathname(), strlen($package) + 1);
        $zip->addFile($item->getPathname(), $relativePath);
    }

    $zip->close();
}

if (!in_array($command, ['build', 'zip'], true)) {
    fwrite(STDERR, "Usage: php tools/build.php [build|zip]\n");
    exit(1);
}

if (!is_dir($dist)) {
    mkdir($dist, 0777, true);
}

buildPackage($root, $package);

if ($command === 'zip') {
    zipPackage($package, $dist . '/noextlinks.zip');
}
