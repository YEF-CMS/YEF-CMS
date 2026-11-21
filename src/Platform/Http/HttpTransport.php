<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

use Yef\Domain\Module\Install\Exception\SourceUnavailable;

/**
 * One HTTP GET, without following redirects. How it is done (curl or PHP streams) is up to the implementation.
 */
interface HttpTransport
{
    /**
     * @param list<string> $headers Request headers, "Name: value".
     * @param callable(string): void $sink Receives the body piece by piece; not called for a redirect.
     *
     * @throws SourceUnavailable When the server cannot be reached, or the body is longer than `$maxBytes`.
     */
    public function get(string $url, array $headers, int $maxBytes, callable $sink): HttpResponse;
}
