<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageIdGenerator;

use function sprintf;

final class SequentialPageIdGenerator implements PageIdGenerator
{
    private int $counter = 0;

    #[Override]
    public function next(): PageId
    {
        return new PageId(sprintf('01ARZ3NDEKTSV4RRFFQ69G5%03d', ++$this->counter));
    }
}
