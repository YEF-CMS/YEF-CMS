<?php

declare(strict_types=1);

namespace Yef\Domain\Rendering;

use Yef\Domain\ContentType\RenderedContent;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Wraps the content of a page into the site layout and returns the complete document.
 */
interface PageRenderer
{
    /**
     * @throws RenderingFailed
     */
    public function render(PageMetadata $page, RenderedContent $content): string;
}
