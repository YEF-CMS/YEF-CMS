<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\User\UserRepository;
use Yef\Tests\Support\InMemoryUserRepository;

#[CoversClass(InMemoryUserRepository::class)]
final class InMemoryUserRepositoryTest extends UserRepositoryContract
{
    #[Override]
    protected function createUsers(): UserRepository
    {
        return new InMemoryUserRepository();
    }
}
