<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * @psalm-api Used by content types and read by the rendering layer.
 */
enum AssetKind: string
{
    case Script = 'script';
    case Style = 'style';
}
