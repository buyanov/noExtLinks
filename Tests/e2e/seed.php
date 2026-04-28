<?php

declare(strict_types=1);

$scenario = $argv[1] ?? '';
$params = json_decode(base64_decode($argv[2] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$introtext = base64_decode($argv[3] ?? '', true);

if ($scenario === '' || !is_array($params) || $introtext === false) {
    fwrite(STDERR, "Usage: php seed.php <scenario> <base64-json-params> <base64-introtext>\n");
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
$prefix = $config->dbprefix;

function tableName(string $prefix, string $name): string
{
    return '`' . str_replace('`', '``', $prefix . $name) . '`';
}

function query(mysqli $mysqli, string $sql): mysqli_result|bool
{
    $result = $mysqli->query($sql);

    if ($result === false) {
        fwrite(STDERR, "SQL failed: {$mysqli->error}\n{$sql}\n");
        exit(1);
    }

    return $result;
}

function scalar(mysqli $mysqli, string $sql): ?string
{
    $result = query($mysqli, $sql);
    $row = $result instanceof mysqli_result ? $result->fetch_row() : null;

    return $row[0] ?? null;
}

$extensions = tableName($prefix, 'extensions');
$content = tableName($prefix, 'content');
$categories = tableName($prefix, 'categories');

$extensionId = scalar(
    $mysqli,
    "SELECT extension_id FROM {$extensions} WHERE type = 'plugin' AND folder = 'system' AND element = 'noextlinks'"
);

if ($extensionId === null) {
    fwrite(STDERR, "NoExternalLinks plugin is not installed\n");
    exit(1);
}

$encodedParams = $mysqli->real_escape_string(json_encode($params, JSON_UNESCAPED_SLASHES));
query($mysqli, "UPDATE {$extensions} SET enabled = 1, params = '{$encodedParams}' WHERE extension_id = " . (int) $extensionId);

$catid = scalar($mysqli, "SELECT id FROM {$categories} WHERE extension = 'com_content' ORDER BY id ASC LIMIT 1");
$catid = (int) ($catid ?: 2);
$alias = 'noextlinks-e2e-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $scenario));
$title = 'NoExternalLinks E2E ' . $scenario;
$now = gmdate('Y-m-d H:i:s');

query($mysqli, "DELETE FROM {$content} WHERE alias = '" . $mysqli->real_escape_string($alias) . "'");

$columns = [
    'asset_id',
    'title',
    'alias',
    'introtext',
    'fulltext',
    'state',
    'catid',
    'created',
    'created_by',
    'modified',
    'modified_by',
    'checked_out',
    'checked_out_time',
    'publish_up',
    'publish_down',
    'images',
    'urls',
    'attribs',
    'version',
    'ordering',
    'metakey',
    'metadesc',
    'access',
    'hits',
    'metadata',
    'featured',
    'language',
    'note',
];

$values = [
    '0',
    "'" . $mysqli->real_escape_string($title) . "'",
    "'" . $mysqli->real_escape_string($alias) . "'",
    "'" . $mysqli->real_escape_string($introtext) . "'",
    "''",
    '1',
    (string) $catid,
    "'" . $now . "'",
    '0',
    "'" . $now . "'",
    '0',
    '0',
    "'1970-01-01 00:00:00'",
    "'" . $now . "'",
    'NULL',
    "'{}'",
    "'{}'",
    "'{}'",
    '1',
    '0',
    "''",
    "''",
    '1',
    '0',
    "'{}'",
    '0',
    "'*'",
    "''",
];

query(
    $mysqli,
    'INSERT INTO ' . $content
    . ' (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $values) . ')'
);

echo json_encode(
    [
        'articleId' => $mysqli->insert_id,
        'categoryId' => $catid,
        'extensionId' => (int) $extensionId,
    ],
    JSON_THROW_ON_ERROR
) . "\n";
