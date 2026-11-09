<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;

/**
 * One step that brings the public site in line with the pages.
 */
final readonly class Change
{
    public function __construct(
        public ChangeAction $action,
        public PageId $page,
    ) {}
}
