<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<string, Page> */
    private array $pages = [];

    #[Override]
    public function save(Page $page): void
    {
        $this->pages[$page->metadata->id->value] = $page;
    }

    #[Override]
    public function get(PageId $id): Page
    {
        return $this->pages[$id->value] ?? throw PageNotFound::withId($id);
    }

    #[Override]
    public function findMetadataByPath(PagePath $path): ?PageMetadata
    {
        foreach ($this->pages as $page) {
            if ($page->metadata->details->path->equals($path)) {
                return $page->metadata;
            }
        }

        return null;
    }

    #[Override]
    public function allMetadata(): array
    {
        return array_values(array_map(static fn(Page $page): PageMetadata => $page->metadata, $this->pages));
    }

    #[Override]
    public function delete(PageId $id): void
    {
        $this->get($id);
        unset($this->pages[$id->value]);
    }
}
