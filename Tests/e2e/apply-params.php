<?php

declare(strict_types=1);

$params = json_decode(base64_decode($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($params)) {
    fwrite(STDERR, "Usage: php apply-params.php <base64-json-params>\n");
    exit(1);
}

require_once '/var/www/html/configuration.php';

$config = new JConfig();
$mysqli = new mysqli($config->host, $config->user, $config->password, $config->db);

if ($mysqli->connect_errno) {
    fwrite(STDERR, 'Database connection failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}

$mysqli->set_charset('utf8mb4');
$prefix = str_replace('`', '``', $config->dbprefix);
$extensions = '`' . $prefix . 'extensions`';
$encodedParams = $mysqli->real_escape_string(json_encode($params, JSON_UNESCAPED_SLASHES));
$result = $mysqli->query(
    "UPDATE {$extensions} SET enabled = 1, params = '{$encodedParams}'"
    . " WHERE type = 'plugin' AND folder = 'system' AND element = 'noextlinks'"
);

if ($result === false) {
    fwrite(STDERR, 'SQL failed: ' . $mysqli->error . "\n");
    exit(1);
}

if ($mysqli->affected_rows < 1) {
    fwrite(STDERR, "NoExternalLinks plugin is not installed\n");
    exit(1);
}

echo json_encode(['updated' => true], JSON_THROW_ON_ERROR) . "\n";
