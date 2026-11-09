<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Flash messages kept in memory and shown once, as the real ones are within a session.
 */
final class InMemoryFlash implements FlashInterface
{
    /** @var array<string, mixed> */
    private array $messages = [];

    #[Override]
    public function get(string $key)
    {
        /** @psalm-suppress MixedAssignment Flash messages can be anything. */
        $message = $this->messages[$key] ?? null;
        unset($this->messages[$key]);

        return $message;
    }

    #[Override]
    public function getAll(): array
    {
        $all = $this->messages;
        $this->messages = [];

        return $all;
    }

    #[Override]
    public function set(string $key, $value = true, bool $removeAfterAccess = true): void
    {
        $this->messages[$key] = $value;
    }

    #[Override]
    public function add(string $key, $value = true, bool $removeAfterAccess = true): void
    {
        $this->messages[$key] = [...(array) ($this->messages[$key] ?? []), $value];
    }

    #[Override]
    public function remove(string $key): void
    {
        unset($this->messages[$key]);
    }

    #[Override]
    public function removeAll(): void
    {
        $this->messages = [];
    }

    #[Override]
    public function has(string $key): bool
    {
        return isset($this->messages[$key]);
    }
}
