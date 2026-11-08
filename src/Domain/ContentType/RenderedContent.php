<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * HTML of the page content together with the scripts and styles it needs.
 *
 * @psalm-api Read by the rendering layer.
 */
final readonly class RenderedContent
{
    /**
     * @param list<Asset> $assets
     */
    public function __construct(
        public string $html,
        public array $assets = [],
    ) {}
}
