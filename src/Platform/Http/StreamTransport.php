<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

use Override;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;

use function explode;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function str_starts_with;
use function stream_context_create;
use function strlen;
use function strtolower;
use function trim;

/**
 * HTTP GET with PHP streams (allow_url_fopen), for hosts without the curl extension.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class StreamTransport implements HttpTransport
{
    public function __construct(private int $timeoutSeconds) {}

    #[Override]
    public function get(string $url, array $headers, int $maxBytes, callable $sink): HttpResponse
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'follow_location' => 0,
            'ignore_errors' => true,
            'timeout' => $this->timeoutSeconds,
        ]]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw SourceUnavailable::because(SourceUnavailable::UNREACHABLE, 'the connection failed');
        }

        $status = 0;
        $received = [];
        /** @var list<string> $http_response_header */
        foreach ($http_response_header ?? [] as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $fields = explode(' ', $line);
                $status = (int) ($fields[1] ?? 0);
                $received = [];

                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $received[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        $response = new HttpResponse($status, $received);
        if ($response->isRedirect()) {
            fclose($stream);

            return $response;
        }

        $total = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false) {
                fclose($stream);

                throw SourceUnavailable::because(SourceUnavailable::UNREACHABLE, 'the connection broke');
            }

            $total += strlen($chunk);
            if ($total > $maxBytes) {
                fclose($stream);

                throw SourceUnavailable::because(SourceUnavailable::TOO_LARGE, 'more than ' . $maxBytes . ' bytes');
            }

            $sink($chunk);
        }

        fclose($stream);

        return $response;
    }
}
