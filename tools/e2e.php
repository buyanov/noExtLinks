<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $argv[1] ?? 'all';

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, "\nE2E failed: {$exception->getMessage()}\n");
    exit(1);
});

$targets = [
    'joomla5' => [
        'image' => 'joomla:5-php8.3-apache',
        'port' => 8055,
        'project' => 'noextlinks-e2e-j5',
    ],
    'joomla6' => [
        'image' => 'joomla:6-php8.3-apache',
        'port' => 8056,
        'project' => 'noextlinks-e2e-j6',
    ],
];

if ($target === 'all') {
    $selectedTargets = array_keys($targets);
} elseif (isset($targets[$target])) {
    $selectedTargets = [$target];
} else {
    fwrite(STDERR, "Usage: php tools/e2e.php [all|joomla5|joomla6]\n");
    exit(1);
}

function runCommand(array $command, array $env = [], bool $allowFailure = false): string
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

function httpGet(string $url): array
{
    $headers = [];
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => 15,
            'header' => "User-Agent: noextlinks-e2e\r\n",
        ],
    ]);

    set_error_handler(static function () {
        return true;
    });

    $body = file_get_contents($url, false, $context);
    restore_error_handler();

    foreach ($http_response_header ?? [] as $header) {
        $headers[] = $header;
    }

    $status = 0;

    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
        $status = (int) $matches[1];
    }

    return [$status, $body === false ? '' : $body, $headers];
}

function waitForJoomla(string $baseUrl): void
{
    $deadline = time() + 240;
    $lastStatus = 0;

    while (time() < $deadline) {
        [$status, $body] = httpGet($baseUrl . '/');
        $lastStatus = $status;

        if ($status === 200 && stripos($body, 'Joomla') !== false) {
            return;
        }

        sleep(3);
    }

    throw new RuntimeException("Joomla did not become ready, last status: {$lastStatus}");
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . "\nMissing: {$needle}\nBody excerpt:\n" . substr($haystack, 0, 1200));
    }
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message . "\nUnexpected: {$needle}\nBody excerpt:\n" . substr($haystack, 0, 1200));
    }
}

function assertStatus(int $expected, int $actual, string $url, string $body): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            "Unexpected HTTP status for {$url}: expected {$expected}, got {$actual}\n"
            . substr($body, 0, 1200)
        );
    }
}

function composeCommand(string $root, array $targetConfig, array $args): array
{
    return array_merge(
        ['docker', 'compose', '-f', $root . '/Tests/e2e/compose.yaml', '-p', $targetConfig['project']],
        $args
    );
}

function composeEnv(array $targetConfig): array
{
    return [
        'JOOMLA_IMAGE' => $targetConfig['image'],
        'JOOMLA_PORT' => (string) $targetConfig['port'],
    ];
}

function seedArticle(string $root, array $targetConfig, string $scenario, array $params, string $introtext): int
{
    $output = runCommand(
        composeCommand(
            $root,
            $targetConfig,
            [
                'exec',
                '-T',
                'joomla',
                'php',
                '/tests/Tests/e2e/seed.php',
                $scenario,
                base64_encode(json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                base64_encode($introtext),
            ]
        ),
        composeEnv($targetConfig)
    );

    $data = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    return (int) $data['articleId'];
}

function installExtension(string $root, array $targetConfig): void
{
    $command = composeCommand(
        $root,
        $targetConfig,
        [
            'exec',
            '-T',
            'joomla',
            'php',
            'cli/joomla.php',
            'extension:install',
            '--path',
            '/tests/dist/noextlinks',
            '-vvv',
        ]
    );

    try {
        $output = runCommand($command, composeEnv($targetConfig));
    } catch (RuntimeException $exception) {
        $diagnostics = runCommand(
            composeCommand(
                $root,
                $targetConfig,
                [
                    'exec',
                    '-T',
                    'joomla',
                    'sh',
                    '-lc',
                    'ls -l /tests/dist/noextlinks.zip; '
                    . 'find /tests/dist/noextlinks -maxdepth 3 -type f -print; '
                    . 'php /tests/Tests/e2e/inspect-zip.php /tests/dist/noextlinks.zip; '
                    . 'php /tests/Tests/e2e/inspect-manifest.php /tests/dist/noextlinks/noextlinks.xml; '
                    . 'php cli/joomla.php extension:list | grep -i noextlinks || true; '
                    . 'find plugins/system/noextlinks -maxdepth 3 -type f -print 2>/dev/null || true; '
                    . 'find tmp -maxdepth 3 -type f -name "*noextlinks*" -o -name "*.xml" 2>/dev/null | head -n 80 || true; '
                    . 'find administrator/logs logs -type f -maxdepth 2 -print -exec tail -n 120 {} \; 2>/dev/null || true',
                ]
            ),
            composeEnv($targetConfig),
            true
        );

        throw new RuntimeException($exception->getMessage() . "\n\nInstaller diagnostics:\n" . $diagnostics);
    }

    if ($output !== '') {
        echo $output . "\n";
    }
}

function fetchArticle(string $baseUrl, int $articleId): string
{
    $url = $baseUrl . '/index.php?option=com_content&view=article&id=' . $articleId;
    [$status, $body] = httpGet($url);
    assertStatus(200, $status, $url, $body);

    return $body;
}

function defaultParams(array $overrides = []): array
{
    return array_merge(
        [
            'noindex' => '1',
            'nofollow' => 'nofollow',
            'settitle' => '1',
            'blank' => '_blank',
            'replace_anchor' => '0',
            'replace_anchor_host' => '0',
            'absolutize' => '0',
            'usejs' => '0',
            'excluded_domains' => '',
            'removed_domains' => '',
            'use_redirect_page' => '0',
            'redirect_timeout' => '5',
            'excluded_menu_items' => '',
            'excluded_menu' => [],
            'excluded_categories' => '',
            'excluded_category_list' => [],
            'excluded_articles' => '',
        ],
        $overrides
    );
}

function runScenario(string $root, array $targetConfig, string $baseUrl, string $name, array $params, string $introtext): string
{
    $articleId = seedArticle($root, $targetConfig, $name, $params, $introtext);

    return fetchArticle($baseUrl, $articleId);
}

runCommand([PHP_BINARY, $root . '/tools/build.php', 'zip']);

foreach ($selectedTargets as $targetName) {
    $targetConfig = $targets[$targetName];
    $env = composeEnv($targetConfig);
    $baseUrl = 'http://127.0.0.1:' . $targetConfig['port'];

    echo "Starting {$targetName} ({$targetConfig['image']})\n";

    try {
        runCommand(composeCommand($root, $targetConfig, ['down', '-v', '--remove-orphans']), $env, true);
        runCommand(composeCommand($root, $targetConfig, ['up', '-d']), $env);
        waitForJoomla($baseUrl);
        installExtension($root, $targetConfig);

        $body = runScenario(
            $root,
            $targetConfig,
            $baseUrl,
            'default',
            defaultParams(),
            '<p><a href="https://google.com">google</a> <a href="/local">local</a> <a href="#top">top</a> <a href="tel:123">tel</a></p>'
        );
        assertContains('<!--noindex--><a href="https://google.com"', $body, 'Default external link is not wrapped');
        assertContains('target="_blank"', $body, 'Default external link does not have target');
        assertContains('rel="nofollow"', $body, 'Default external link does not have rel');
        assertContains('class="external-link --set-title"', $body, 'Default external link does not have expected class');
        assertContains('<a href="/local">local</a>', $body, 'Relative link was modified');
        assertContains('<a href="#top">top</a>', $body, 'Anchor link was modified');
        assertContains('<a href="tel:123">tel</a>', $body, 'Special scheme link was modified');

        $body = runScenario(
            $root,
            $targetConfig,
            $baseUrl,
            'nofollow-off',
            defaultParams(['noindex' => '0', 'nofollow' => '0', 'blank' => '0']),
            '<p><a href="https://google.com">google</a></p>'
        );
        assertContains('<a href="https://google.com" title="google" class="external-link --set-title">google</a>', $body, 'nofollow/blank disabled output mismatch');
        assertNotContains('rel="nofollow"', $body, 'nofollow=0 still adds rel');
        assertNotContains('target="_blank"', $body, 'blank=0 still adds target');
        assertNotContains('<!--noindex-->', $body, 'noindex=0 still wraps output');

        $body = runScenario(
            $root,
            $targetConfig,
            $baseUrl,
            'usejs',
            defaultParams(['usejs' => '1']),
            '<p><a href="https://google.com">google</a></p>'
        );
        assertContains('<span data-href="https://google.com"', $body, 'usejs did not render span');
        assertContains('class="external-link --set-title js-modify"', $body, 'usejs did not add js class');

        $body = runScenario(
            $root,
            $targetConfig,
            $baseUrl,
            'excluded-domain',
            defaultParams(['excluded_domains' => '{"scheme":["https"],"host":["google.com"],"path":["/*"]}']),
            '<p><a href="https://google.com/docs/page">google docs</a></p>'
        );
        assertContains('<a href="https://google.com/docs/page">google docs</a>', $body, 'excluded domain link was modified');
        assertNotContains('external-link', $body, 'excluded domain still has plugin class');

        $body = runScenario(
            $root,
            $targetConfig,
            $baseUrl,
            'removed-domain',
            defaultParams(['removed_domains' => '{"host":["google.com"]}']),
            '<p>before <a href="https://google.com">google</a> after</p>'
        );
        assertContains('before  after', $body, 'removed domain link was not removed');
        assertNotContains('https://google.com', $body, 'removed domain href still present');

        [$adminStatus, $adminBody] = httpGet($baseUrl . '/administrator/index.php');
        assertStatus(200, $adminStatus, $baseUrl . '/administrator/index.php', $adminBody);
        assertNotContains('external-link', $adminBody, 'admin output was modified');
        assertNotContains('<!--noindex-->', $adminBody, 'admin output was wrapped');

        echo "{$targetName}: OK\n";
    } finally {
        runCommand(composeCommand($root, $targetConfig, ['down', '-v', '--remove-orphans']), $env, true);
    }
}
