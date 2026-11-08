<?php

declare(strict_types=1);

namespace Yef\Storage;

use Override;
use Symfony\Component\Uid\Ulid;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageIdGenerator;

/**
 * Issues sortable identifiers that need no shared counter, so pages can be created in parallel.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class UlidPageIdGenerator implements PageIdGenerator
{
    #[Override]
    public function next(): PageId
    {
        return new PageId(Ulid::generate());
    }
}
