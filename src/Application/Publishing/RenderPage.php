<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use InvalidArgumentException;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Rendering\Exception\RenderingFailed;
use Yef\Domain\Rendering\PageRenderer;

/**
 * Builds the complete HTML document of a page: the content type renders the payload, the site layout wraps it.
 * Publishing and previewing both use it, so a preview shows what publishing would build.
 *
 * @psalm-api Used by PublishPage and PreviewPage.
 */
final readonly class RenderPage
{
    public function __construct(
        private ContentTypeRegistry $contentTypes,
        private PageRenderer $renderer,
    ) {}

    /**
     * @throws UnknownContentType
     * @throws InvalidArgumentException When the payload does not belong to the content type of the page.
     * @throws RenderingFailed
     */
    public function handle(PageMetadata $metadata, Payload $payload): string
    {
        $content = $this->contentTypes->get($metadata->type)->renderer->render($payload);

        return $this->renderer->render($metadata, $content);
    }
}
