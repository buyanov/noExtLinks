<?php

declare(strict_types=1);

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
$result = $mysqli->query(
    "SELECT extension_id, enabled, params FROM {$extensions}"
    . " WHERE type = 'plugin' AND folder = 'system' AND element = 'noextlinks'"
);

if ($result === false) {
    fwrite(STDERR, 'SQL failed: ' . $mysqli->error . "\n");
    exit(1);
}

$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode(['installed' => false], JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

echo json_encode(
    [
        'installed' => true,
        'extensionId' => (int) $row['extension_id'],
        'enabled' => (int) $row['enabled'],
        'params' => json_decode($row['params'] ?: '{}', true),
    ],
    JSON_THROW_ON_ERROR
) . "\n";
