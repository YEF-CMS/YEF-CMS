<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageRepository;

/**
 * Deletes a page. Its public HTML stays until the changes are applied, like every other edit.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class DeletePage
{
    public function __construct(private PageRepository $pages) {}

    /**
     * @throws InvalidArgumentException When the id is invalid.
     * @throws PageNotFound
     */
    public function handle(string $id): void
    {
        $this->pages->delete(new PageId($id));
    }
}
