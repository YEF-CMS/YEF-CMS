<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\User\SetupToken;

final class InMemorySetupToken implements SetupToken
{
    private ?string $token = null;

    private int $created = 0;

    #[Override]
    public function current(): string
    {
        if ($this->token === null) {
            ++$this->created;
            $this->token = 'code-' . $this->created;
        }

        return $this->token;
    }

    #[Override]
    public function consume(): void
    {
        $this->token = null;
    }

    public function exists(): bool
    {
        return $this->token !== null;
    }
}
