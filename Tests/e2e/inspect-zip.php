<?php

declare(strict_types=1);

$path = $argv[1] ?? '';

if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Zip file does not exist: {$path}\n");
    exit(1);
}

$zip = new ZipArchive();

if ($zip->open($path) !== true) {
    fwrite(STDERR, "Unable to open zip file: {$path}\n");
    exit(1);
}

echo "Zip entries:\n";

for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i) . "\n";
}

$manifest = $zip->getFromName('noextlinks.xml');

if ($manifest === false) {
    fwrite(STDERR, "Zip does not contain noextlinks.xml\n");
    exit(1);
}

echo "\nManifest head:\n";
echo implode("\n", array_slice(explode("\n", $manifest), 0, 20)) . "\n";

$zip->close();
