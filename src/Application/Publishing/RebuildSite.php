<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Publishes every page in one pass, without limits. A page that fails does not stop the others.
 *
 * @psalm-api Used by the console adapter.
 */
final readonly class RebuildSite
{
    public function __construct(
        private PageRepository $pages,
        private PublishPage $publishPage,
    ) {}

    /**
     * @throws PageStorageFailed When the pages cannot be listed at all.
     */
    public function handle(): RebuildReport
    {
        $counts = [];
        $failed = [];

        foreach ($this->pages->allMetadata() as $metadata) {
            try {
                $result = $this->publishPage->handle($metadata->id->value);
            } catch (PageDataCorrupted|PageNotFound|PageStorageFailed|PublishingFailed|RenderingFailed) {
                $failed[] = $metadata->id;
                continue;
            }

            $counts[$result->value] = ($counts[$result->value] ?? 0) + 1;
        }

        return new RebuildReport($counts, $failed);
    }
}
