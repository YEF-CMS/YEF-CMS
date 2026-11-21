<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Application\Publishing\RenderPage;
use Yef\Domain\Clock;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageIdGenerator;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Builds the HTML a page would have, from the data of the form, without saving anything or touching the public site.
 * The address plays no part in it, so it is neither checked nor needs to be free.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class PreviewPage
{
    private const string UNTITLED = 'Untitled';

    public function __construct(
        private ContentTypeRegistry $contentTypes,
        private PageIdGenerator $ids,
        private Clock $clock,
        private RenderPage $renderPage,
    ) {}

    /**
     * @throws InvalidArgumentException When the type id or the title or the SEO texts are invalid.
     * @throws UnknownContentType
     * @throws InvalidPayload
     * @throws RenderingFailed
     */
    public function handle(PreviewPageCommand $command): string
    {
        $type = new ContentTypeId($command->type);
        $payload = $this->contentTypes->get($type)->factory->fromInput($command->input);
        $title = trim($command->title) === '' ? self::UNTITLED : $command->title;
        $details = new PageDetails(new PagePath(''), $title, $command->metaTitle, $command->metaDescription);

        return $this->renderPage->handle(PageMetadata::draft($this->ids->next(), $type, $details, $this->clock->now()), $payload);
    }
}
