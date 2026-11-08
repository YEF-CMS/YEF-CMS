<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;

/**
 * Takes down the public HTML at a page address, for example after the page was deleted or moved.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class UnpublishPage
{
    public function __construct(private PublicArtifactPublisher $publisher) {}

    /**
     * @throws PublishingFailed
     */
    public function handle(PagePath $path): void
    {
        $this->publisher->remove(PublicPath::forPage($path));
    }
}
