<?php

declare(strict_types=1);

namespace Yef\Setup;

final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
    ) {}
}
