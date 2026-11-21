<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

/**
 * The head of an HTTP answer.
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers Header values by lower-case name.
     */
    public function __construct(
        public int $status,
        public array $headers = [],
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isRedirect(): bool
    {
        return in_array($this->status, [301, 302, 303, 307, 308], true) && $this->header('location') !== null;
    }
}
