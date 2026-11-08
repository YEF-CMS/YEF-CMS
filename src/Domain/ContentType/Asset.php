<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * A script or a style sheet a page needs.
 *
 * @psalm-api Used by content types and read by the rendering layer.
 */
final readonly class Asset
{
    public function __construct(
        public AssetKind $kind,
        public string $path,
    ) {}
}
