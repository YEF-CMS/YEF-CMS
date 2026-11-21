<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

use Yef\Domain\Module\Install\Exception\SourceUnavailable;

use function in_array;
use function parse_url;
use function str_starts_with;

/**
 * Fetches from a short list of hosts only, over https unless the configuration says otherwise (a local test server),
 * and follows redirects by itself so that none of them leads anywhere else.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class HttpFetcher
{
    private const int MAX_REDIRECTS = 5;

    private const string USER_AGENT = 'YEF-CMS-module-installer';

    /**
     * @param list<string> $allowedHosts "host" or "host:port".
     */
    public function __construct(
        private HttpTransport $transport,
        private array $allowedHosts,
        private bool $allowHttp,
    ) {}

    /**
     * @param callable(string): void $sink
     *
     * @throws SourceUnavailable
     */
    public function get(string $url, int $maxBytes, callable $sink): HttpResponse
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; ++$redirects) {
            $this->assertAllowed($url);
            $response = $this->transport->get(
                $url,
                ['Accept: application/vnd.github+json', 'User-Agent: ' . self::USER_AGENT, 'X-GitHub-Api-Version: 2022-11-28'],
                $maxBytes,
                $sink,
            );

            if (!$response->isRedirect()) {
                return $response;
            }

            $url = $this->resolve($url, (string) $response->header('location'));
        }

        throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'too many redirects');
    }

    /**
     * @throws SourceUnavailable
     */
    private function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $hostAndPort = isset($parts['port']) ? $host . ':' . $parts['port'] : $host;
        $schemeAllowed = $scheme === 'https' || ($this->allowHttp && $scheme === 'http');

        if (!$schemeAllowed || isset($parts['user']) || !in_array($hostAndPort, $this->allowedHosts, true)) {
            throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'the address is not allowed: ' . $hostAndPort);
        }
    }

    private function resolve(string $from, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($from);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $base . (str_starts_with($location, '/') ? '' : '/') . $location;
    }
}
