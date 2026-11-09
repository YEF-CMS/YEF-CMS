<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageId;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Brings the public site in line with the pages in one pass: builds what changed, takes down what is gone.
 * A change that fails does not stop the others; the next run plans it again.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class ApplyChanges
{
    public function __construct(
        private PlanChanges $planChanges,
        private PublishPage $publishPage,
        private UnpublishPage $unpublishPage,
    ) {}

    /**
     * @throws PageStorageFailed When the pages cannot be listed at all.
     * @throws PublishingFailed When the record of published pages cannot be read.
     */
    public function handle(): ApplyReport
    {
        $built = 0;
        $removed = 0;
        $skipped = 0;
        $failed = [];

        foreach ($this->planChanges->handle()->changes as $change) {
            try {
                $result = $this->apply($change);
            } catch (PageDataCorrupted|PageNotFound|PageStorageFailed|PublishingFailed|RenderingFailed) {
                $failed[] = $change->page;
                continue;
            }

            match ($result) {
                PublishResult::Published => ++$built,
                PublishResult::Withdrawn => ++$removed,
                PublishResult::Skipped => ++$skipped,
            };
        }

        return new ApplyReport($built, $removed, $skipped, $failed);
    }

    /**
     * @throws PageDataCorrupted
     * @throws PageNotFound
     * @throws PageStorageFailed
     * @throws PublishingFailed
     * @throws RenderingFailed
     */
    private function apply(Change $change): PublishResult
    {
        return $change->action === ChangeAction::Build
            ? $this->publishPage->handle($change->page->value)
            : $this->remove($change->page);
    }

    /**
     * @throws PublishingFailed
     */
    private function remove(PageId $id): PublishResult
    {
        $this->unpublishPage->handle($id);

        return PublishResult::Withdrawn;
    }
}
