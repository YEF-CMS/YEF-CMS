<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Clock;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageContent;
use Yef\Domain\Page\PageIdGenerator;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

/**
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class CreatePage
{
    public function __construct(
        private PageRepository $pages,
        private PageIdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidArgumentException When the path or the content is invalid.
     * @throws PathAlreadyTaken
     */
    public function __invoke(CreatePageCommand $command): Page
    {
        $path = new PagePath($command->path);
        if ($this->pages->findByPath($path) instanceof \Yef\Domain\Page\Page) {
            throw PathAlreadyTaken::byPath($path);
        }

        $page = Page::create(
            $this->ids->next(),
            $path,
            new PageContent($command->title, $command->body, $command->metaTitle, $command->metaDescription),
            $this->clock->now(),
        );
        $this->pages->save($page);

        return $page;
    }
}
