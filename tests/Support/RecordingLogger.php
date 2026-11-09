<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
