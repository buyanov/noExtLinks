<?php

declare(strict_types=1);

namespace Buyanov\NoExtLinks\Support;

function base(): string
{
    $host = $_SERVER['HTTP_HOST'];
    $scheme = $_SERVER['REQUEST_SCHEME'];

    if (strpos(php_sapi_name(), 'cgi') !== false
        && !ini_get('cgi.fix_pathinfo')
        && !empty($_SERVER['REQUEST_URI'])) {
        $script_name = $_SERVER['PHP_SELF'];
    } else {
        $script_name = $_SERVER['SCRIPT_NAME'];
    }

    return $scheme . '://' . $host . '/' . rtrim(dirname($script_name), '/\\');
}