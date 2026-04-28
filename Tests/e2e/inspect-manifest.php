<?php

declare(strict_types=1);

$path = $argv[1] ?? '';

if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Manifest file does not exist: {$path}\n");
    exit(1);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($path);

echo "\nManifest inspection:\n";

if ($xml === false) {
    echo "SimpleXML parse failed\n";

    foreach (libxml_get_errors() as $error) {
        echo trim($error->message) . "\n";
    }

    exit(1);
}

$attributes = $xml->attributes();

echo 'root=' . $xml->getName() . "\n";
echo 'type=' . (string) ($attributes['type'] ?? '') . "\n";
echo 'group=' . (string) ($attributes['group'] ?? '') . "\n";
echo 'method=' . (string) ($attributes['method'] ?? '') . "\n";
echo 'name=' . (string) $xml->name . "\n";
echo 'version=' . (string) $xml->version . "\n";
echo 'entry=' . (string) ($xml->files->filename[0] ?? '') . "\n";
