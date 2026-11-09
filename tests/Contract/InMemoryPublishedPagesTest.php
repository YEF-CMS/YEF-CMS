<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Publishing\PublishedPages;
use Yef\Tests\Support\InMemoryPublishedPages;

#[CoversClass(InMemoryPublishedPages::class)]
final class InMemoryPublishedPagesTest extends PublishedPagesContract
{
    #[Override]
    protected function createPublishedPages(): PublishedPages
    {
        return new InMemoryPublishedPages();
    }
}
