<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<string, Page> */
    private array $pages = [];

    #[Override]
    public function save(Page $page): void
    {
        $this->pages[$page->id->value] = $page;
    }

    #[Override]
    public function get(PageId $id): Page
    {
        return $this->pages[$id->value] ?? throw PageNotFound::withId($id);
    }

    #[Override]
    public function findByPath(PagePath $path): ?Page
    {
        foreach ($this->pages as $page) {
            if ($page->path->equals($path)) {
                return $page;
            }
        }

        return null;
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->pages);
    }

    #[Override]
    public function delete(PageId $id): void
    {
        $this->get($id);
        unset($this->pages[$id->value]);
    }
}
