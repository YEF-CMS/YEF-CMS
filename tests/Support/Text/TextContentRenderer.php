<?php

declare(strict_types=1);

namespace Yef\Tests\Support\Text;

use InvalidArgumentException;
use Override;
use Yef\Domain\ContentType\ContentRenderer;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\RenderedContent;

final readonly class TextContentRenderer implements ContentRenderer
{
    #[Override]
    public function render(Payload $payload): RenderedContent
    {
        if (!$payload instanceof TextPayload) {
            throw new InvalidArgumentException('Expected a text payload.');
        }

        return new RenderedContent($payload->html);
    }
}
