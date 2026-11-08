<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Clock;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageIdGenerator;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;

/**
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class CreatePage
{
    public function __construct(
        private PageRepository $pages,
        private ContentTypeRegistry $types,
        private PageIdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidArgumentException When the type id, the path or the title is invalid.
     * @throws UnknownContentType
     * @throws InvalidPayload
     * @throws PathAlreadyTaken
     */
    public function handle(CreatePageCommand $command): Page
    {
        $type = new ContentTypeId($command->type);
        $definition = $this->types->get($type);
        $details = new PageDetails(
            new PagePath($command->path),
            $command->title,
            $command->metaTitle,
            $command->metaDescription,
        );
        if ($this->pages->findMetadataByPath($details->path) instanceof \Yef\Domain\Page\PageMetadata) {
            throw PathAlreadyTaken::byPath($details->path);
        }

        $page = new Page(
            PageMetadata::draft($this->ids->next(), $type, $details, $this->clock->now()),
            $definition->factory->fromInput($command->input),
        );
        $this->pages->save($page);

        return $page;
    }
}
