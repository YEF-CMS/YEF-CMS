<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Clock;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageContent;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

/**
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class UpdatePage
{
    public function __construct(
        private PageRepository $pages,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidArgumentException When the id, the path or the content is invalid.
     * @throws PageNotFound
     * @throws PathAlreadyTaken
     */
    public function __invoke(UpdatePageCommand $command): Page
    {
        $page = $this->pages->get(new PageId($command->id));
        $path = new PagePath($command->path);
        $this->assertPathIsFree($path, $page);

        $now = $this->clock->now();
        $page = $page
            ->moveTo($path, $now)
            ->rewrite(
                new PageContent($command->title, $command->body, $command->metaTitle, $command->metaDescription),
                $now,
            );
        $page = $command->published ? $page->publish($now) : $page->unpublish($now);

        $this->pages->save($page);

        return $page;
    }

    private function assertPathIsFree(PagePath $path, Page $page): void
    {
        $owner = $this->pages->findByPath($path);
        if ($owner instanceof \Yef\Domain\Page\Page && !$owner->id->equals($page->id)) {
            throw PathAlreadyTaken::byPath($path);
        }
    }
}
