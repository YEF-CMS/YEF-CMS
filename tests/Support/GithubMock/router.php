<?php

declare(strict_types=1);

/**
 * A stand-in for GitHub, for tests of the module installer. Run with `php -S 127.0.0.1:<port> router.php`.
 * What a repository does depends on its name; the owner is always "acme".
 */

// The version of the release the stand-in publishes can be changed by writing it to this file (manual tests).
$versionFile = sys_get_temp_dir() . '/yef-mock-version';
$version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.2.0';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');

$json = static function (int $status, array|string $body, array $headers = []): never {
    http_response_code($status);
    header('Content-Type: application/json');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    echo is_string($body) ? $body : json_encode($body);
    exit;
};

$zip = static function (int $extraBytes = 0) use ($version): never {
    $file = tempnam(sys_get_temp_dir(), 'mock');
    $archive = new ZipArchive();
    $archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    // A small module that really works, named like the repository "news".
    $archive->addFromString('acme-news-1a2b3c/module.json', json_encode([
        'schema' => 1,
        'name' => 'acme/news',
        'title' => 'News (stand-in)',
        'description' => 'A module from the stand-in for GitHub.',
        'version' => $version,
        'namespace' => 'AcmeNews\\',
        'entry' => 'AcmeNews\\Module',
        'requires' => ['yef' => '^0.1'],
    ]));
    $archive->addFromString(
        'acme-news-1a2b3c/src/Module.php',
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace AcmeNews;\n\nfinal class Module implements \\Yef\\Domain\\Module\\ModuleInterface\n{\n    public function register(\\Yef\\Domain\\Module\\ModuleContext \$context): void {}\n}\n",
    );
    $archive->addFromString('acme-news-1a2b3c/messages/en/acme__news.php', "<?php return ['module.title' => 'News (stand-in) ' . '" . $version . "'];\n");
    $archive->addFromString('acme-news-1a2b3c/messages/ru/acme__news.php', "<?php return ['module.title' => 'Новости (макет) " . $version . "'];\n");
    if ($extraBytes > 0) {
        $archive->addFromString('acme-news-1a2b3c/big.bin', random_bytes($extraBytes));
    }

    $archive->close();
    header('Content-Type: application/zip');
    readfile($file);
    unlink($file);
    exit;
};

if ($path === '/headers') {
    $json(200, ['headers' => array_change_key_case(getallheaders(), CASE_LOWER)]);
}

if ($path === '/loop') {
    header('Location: /loop', true, 302);
    exit;
}

if ($path === '/codeload/archive.zip') {
    $zip();
}

if ($path === '/codeload/big.zip') {
    $zip(300_000);
}

if (preg_match('#^/repos/acme/([a-z0-9-]+)/zipball/([^/]+)$#', $path, $parts) === 1) {
    $target = match ($parts[1]) {
        'evilredirect' => 'http://evil.example/archive.zip',
        'loop' => '/loop',
        'huge' => '/codeload/big.zip',
        default => '/codeload/archive.zip',
    };
    header('Location: ' . $target, true, 302);
    exit;
}

if (preg_match('#^/repos/acme/([a-z0-9-]+)/releases/latest$#', $path, $parts) === 1) {
    $zipball = $base . '/repos/acme/' . $parts[1] . '/zipball/v' . $version;
    match ($parts[1]) {
        'news', 'evilredirect', 'loop', 'huge' => $json(200, ['tag_name' => 'v' . $version, 'zipball_url' => $zipball, 'draft' => false, 'prerelease' => false]),
        'norelease', 'missing' => $json(404, ['message' => 'Not Found']),
        'limited' => $json(403, ['message' => 'rate limit'], ['x-ratelimit-remaining' => '0']),
        'tooMany', 'toomany' => $json(429, ['message' => 'slow down']),
        'forbidden' => $json(403, ['message' => 'no']),
        'badjson' => $json(200, 'this is not json'),
        'notag' => $json(200, ['zipball_url' => $zipball]),
        'notsemver' => $json(200, ['tag_name' => 'latest', 'zipball_url' => $zipball, 'draft' => false, 'prerelease' => false]),
        'prerelease' => $json(200, ['tag_name' => 'v2.0.0-beta.1', 'zipball_url' => $zipball, 'draft' => false, 'prerelease' => true]),
        'evilhost' => $json(200, ['tag_name' => 'v1.2.0', 'zipball_url' => 'http://evil.example/archive.zip', 'draft' => false, 'prerelease' => false]),
        'broken' => $json(500, ['message' => 'oops']),
        default => $json(404, ['message' => 'Not Found']),
    };
}

if (preg_match('#^/repos/acme/([a-z0-9-]+)$#', $path, $parts) === 1) {
    if ($parts[1] === 'missing') {
        $json(404, ['message' => 'Not Found']);
    }

    $json(200, ['full_name' => 'acme/' . $parts[1]]);
}

$json(404, ['message' => 'Not Found']);
