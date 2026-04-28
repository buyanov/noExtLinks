<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $argv[1] ?? 'joomla5';

$targets = [
    'joomla5' => [
        'image' => 'joomla:5-php8.3-apache',
        'port' => 8055,
        'project' => 'noextlinks-manual-j5',
    ],
    'joomla6' => [
        'image' => 'joomla:6-php8.3-apache',
        'port' => 8056,
        'project' => 'noextlinks-manual-j6',
    ],
];

if (!isset($targets[$target])) {
    fwrite(STDERR, "Usage: php tools/e2e-manual.php [joomla5|joomla6]\n");
    exit(1);
}

function runManualCommand(array $command, array $env = [], bool $allowFailure = false): string
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, null, array_merge($_ENV, $env));

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

    if ($exitCode !== 0 && !$allowFailure) {
        throw new RuntimeException(
            "Command failed with exit code {$exitCode}: " . implode(' ', $command)
            . ($output !== '' ? "\n{$output}" : '')
        );
    }

    return $output;
}

function composeManualCommand(string $root, array $targetConfig, array $args): array
{
    return array_merge(
        ['docker', 'compose', '-f', $root . '/Tests/e2e/compose.yaml', '-p', $targetConfig['project']],
        $args
    );
}

function composeManualEnv(array $targetConfig): array
{
    return [
        'JOOMLA_IMAGE' => $targetConfig['image'],
        'JOOMLA_PORT' => (string) $targetConfig['port'],
    ];
}

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, "\nManual Joomla start failed: {$exception->getMessage()}\n");
    exit(1);
});

$targetConfig = $targets[$target];
$env = composeManualEnv($targetConfig);

runManualCommand([PHP_BINARY, $root . '/tools/build.php', 'zip']);
runManualCommand(composeManualCommand($root, $targetConfig, ['down', '-v', '--remove-orphans']), $env, true);
runManualCommand(composeManualCommand($root, $targetConfig, ['up', '-d']), $env);

$baseUrl = 'http://127.0.0.1:' . $targetConfig['port'];

echo "Joomla manual test stand is starting.\n\n";
echo "Frontend: {$baseUrl}/\n";
echo "Admin:    {$baseUrl}/administrator/\n\n";
echo "Admin username: admin\n";
echo "Admin password: admin-password-123\n\n";
echo "Upload this package in System -> Install -> Extensions:\n";
echo $root . "/dist/noextlinks.zip\n\n";
echo "Stop and remove the stand with:\n";
echo 'JOOMLA_IMAGE=' . $targetConfig['image'] . ' JOOMLA_PORT=' . $targetConfig['port']
    . ' docker compose -f Tests/e2e/compose.yaml -p ' . $targetConfig['project']
    . " down -v --remove-orphans\n";
