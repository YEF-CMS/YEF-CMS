<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yiisoft\Csrf\CsrfTokenInterface;

final readonly class FakeCsrfToken implements CsrfTokenInterface
{
    public const string VALUE = 'csrf-token-for-tests';

    #[Override]
    public function getValue(): string
    {
        return self::VALUE;
    }

    #[Override]
    public function validate(string $token): bool
    {
        return $token === self::VALUE;
    }
}
