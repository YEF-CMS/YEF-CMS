<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Clock;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

/**
 * Changes a page. The content type of a page never changes after creation.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class UpdatePage
{
    public function __construct(
        private PageRepository $pages,
        private ContentTypeRegistry $types,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidArgumentException When the id, the path or the title is invalid.
     * @throws PageNotFound
     * @throws UnknownContentType
     * @throws InvalidPayload
     * @throws PathAlreadyTaken
     */
    public function handle(UpdatePageCommand $command): Page
    {
        $page = $this->pages->get(new PageId($command->id));
        $definition = $this->types->get($page->metadata->type);
        $details = new PageDetails(
            new PagePath($command->path),
            $command->title,
            $command->metaTitle,
            $command->metaDescription,
        );
        $this->assertPathIsFree($details->path, $page);

        $now = $this->clock->now();
        $page = $page->edit($details, $definition->factory->fromInput($command->input), $now);
        $page = $command->published ? $page->publish($now) : $page->unpublish($now);

        $this->pages->save($page);

        return $page;
    }

    private function assertPathIsFree(PagePath $path, Page $page): void
    {
        $owner = $this->pages->findMetadataByPath($path);
        if ($owner instanceof \Yef\Domain\Page\PageMetadata && !$owner->id->equals($page->metadata->id)) {
            throw PathAlreadyTaken::byPath($path);
        }
    }
}
