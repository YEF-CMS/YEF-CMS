<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use Override;
use Yef\Domain\Module\ModuleContext;
use Yef\Domain\Module\ModuleInterface;

/**
 * Entry point of the module: tells the core which content type it adds.
 *
 * @psalm-api Instantiated by the module loader through the `entry` of the manifest.
 */
final class TextPagesModule implements ModuleInterface
{
    #[Override]
    public function register(ModuleContext $context): void
    {
        $context->addContentType(TextContentType::definition());
    }
}
