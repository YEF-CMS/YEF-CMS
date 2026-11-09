<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Tests\Support\InMemoryPageRepository;

#[CoversClass(InMemoryPageRepository::class)]
final class InMemoryPageRepositoryTest extends PageRepositoryContract
{
    #[Override]
    protected function createRepository(): InMemoryPageRepository
    {
        return new InMemoryPageRepository();
    }
}
