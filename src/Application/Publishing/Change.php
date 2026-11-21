<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;

/**
 * One step that brings the public site in line with the pages.
 */
final readonly class Change
{
    /**
     * @param PagePath $path Where the public HTML of the page lies, or will lie.
     * @param ?string $title The title of the page; unknown for a page that is already deleted.
     */
    public function __construct(
        public ChangeAction $action,
        public PageId $page,
        public PagePath $path,
        public ?string $title = null,
    ) {}
}
