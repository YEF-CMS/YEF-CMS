<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\ContentType\RenderedContent;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Rendering\PageRenderer;

final readonly class FakePageRenderer implements PageRenderer
{
    #[Override]
    public function render(PageMetadata $page, RenderedContent $content): string
    {
        return '<doc title="' . $page->details->title . '">' . $content->html . '</doc>';
    }
}
