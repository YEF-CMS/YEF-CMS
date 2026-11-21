<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

use CurlHandle;
use Override;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;

use function count;
use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_init;
use function curl_setopt_array;
use function explode;
use function min;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

/**
 * HTTP GET with the curl extension.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class CurlTransport implements HttpTransport
{
    public function __construct(private int $timeoutSeconds) {}

    #[Override]
    public function get(string $url, array $headers, int $maxBytes, callable $sink): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw SourceUnavailable::because(SourceUnavailable::NO_CLIENT, 'curl could not start');
        }

        $transfer = new TransferState();

        curl_setopt_array($handle, [
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_TIMEOUT => $this->timeoutSeconds,
            \CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use ($transfer): int {
                $parts = explode(':', $line, 2);
                if (str_starts_with($line, 'HTTP/')) {
                    $fields = explode(' ', $line);
                    $transfer->status = (int) ($fields[1] ?? 0);
                    $transfer->headers = [];
                } elseif (count($parts) === 2) {
                    $transfer->headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
            \CURLOPT_WRITEFUNCTION => static function (CurlHandle $curl, string $data) use ($transfer, $maxBytes, $sink): int {
                if ($transfer->status >= 300 && $transfer->status < 400) {
                    return strlen($data);
                }

                $transfer->bytes += strlen($data);
                if ($transfer->bytes > $maxBytes) {
                    $transfer->tooLarge = true;

                    return 0;
                }

                $sink($data);

                return strlen($data);
            },
        ]);

        $ok = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($transfer->tooLarge) {
            throw SourceUnavailable::because(SourceUnavailable::TOO_LARGE, 'more than ' . $maxBytes . ' bytes');
        }

        if ($ok === false) {
            throw SourceUnavailable::because(SourceUnavailable::UNREACHABLE, $error);
        }

        return new HttpResponse($transfer->status, $transfer->headers);
    }
}
