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
$menu = '`' . $prefix . 'menu`';

$result = $mysqli->query(
    "SELECT id, language FROM {$menu}"
    . " WHERE client_id = 0 AND home = 1 AND published = 1"
    . " ORDER BY id ASC LIMIT 1"
);

if ($result === false) {
    fwrite(STDERR, 'SQL failed: ' . $mysqli->error . "\n");
    exit(1);
}

$row = $result->fetch_assoc();

if (!$row) {
    fwrite(STDERR, "No published site home menu item found\n");
    exit(1);
}

echo json_encode(
    [
        'id' => (int) $row['id'],
        'language' => $row['language'],
    ],
    JSON_THROW_ON_ERROR
) . "\n";
