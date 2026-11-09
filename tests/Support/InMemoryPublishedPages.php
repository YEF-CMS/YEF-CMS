<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Page\PageId;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;

final class InMemoryPublishedPages implements PublishedPages
{
    /** @var array<string, PublishedPage> */
    private array $pages = [];

    #[Override]
    public function all(): array
    {
        return array_values($this->pages);
    }

    #[Override]
    public function find(PageId $id): ?PublishedPage
    {
        return $this->pages[$id->value] ?? null;
    }

    #[Override]
    public function record(PublishedPage $page): void
    {
        $this->pages[$page->id->value] = $page;
    }

    #[Override]
    public function forget(PageId $id): void
    {
        unset($this->pages[$id->value]);
    }
}
