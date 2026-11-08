<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;

/**
 * Turns a payload into the HTML that goes into the main region of the page layout.
 */
interface ContentRenderer
{
    /**
     * @throws InvalidArgumentException When the payload was not created by this content type.
     */
    public function render(Payload $payload): RenderedContent;
}
