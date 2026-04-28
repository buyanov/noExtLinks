<?php

declare(strict_types=1);

use Buyanov\NoExtLinks\Support\Parser;
use Buyanov\NoExtLinks\Support\UriList;
use Joomla\Registry\Registry;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

$options = new Registry([
    'noindex' => '1',
    'nofollow' => 'nofollow',
    'settitle' => '1',
    'blank' => '_blank',
    'replace_anchor' => '0',
    'replace_anchor_host' => '0',
    'absolutize' => '0',
    'usejs' => '0',
    'use_redirect_page' => '0',
]);

$scenarios = [
    'no-links' => str_repeat('<p>Plain article text without anchors.</p>' . PHP_EOL, 5000),
    'internal-links' => str_repeat('<p><a href="/local/path">local</a> text</p>' . PHP_EOL, 5000),
    'external-links' => str_repeat('<p><a href="https://example.com/path" class="x">example</a> text</p>' . PHP_EOL, 5000),
    'mixed-links' => str_repeat(
        '<p><a href="https://example.com/path" class="x">example</a> <a href="/local">local</a> '
        . '<a href="tel:123">tel</a></p>' . PHP_EOL,
        5000
    ),
    'malformed-html' => str_repeat(
        '<p><a href="https://example.com/path" title="a > b">example</a> '
        . '<a href="https://broken.example">broken</p>' . PHP_EOL,
        5000
    ),
];

printf("%-16s %10s %12s %12s %12s\n", 'scenario', 'bytes', 'time_ms', 'peak_mib', 'sha1');

foreach ($scenarios as $name => $content) {
    $whiteList = new UriList();
    $removeList = new UriList();

    gc_collect_cycles();

    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $memoryStart = memory_get_usage(true);
    $timeStart = hrtime(true);

    Parser::create($content, $options)
        ->prepare($whiteList, $removeList)
        ->parse()
        ->finish();

    $timeMs = (hrtime(true) - $timeStart) / 1e6;
    $peakMiB = (memory_get_peak_usage(true) - $memoryStart) / 1024 / 1024;

    printf(
        "%-16s %10d %12.2f %12.2f %12s\n",
        $name,
        strlen($content),
        $timeMs,
        $peakMiB,
        substr(sha1($content), 0, 12)
    );
}
