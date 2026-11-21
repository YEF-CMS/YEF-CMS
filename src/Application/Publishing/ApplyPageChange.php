<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use InvalidArgumentException;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageId;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Applies the change of one page, so a screen can bring the site up to date step by step.
 * What to do is taken from the current plan, never from the caller: a page that has no change in the plan is not touched.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class ApplyPageChange
{
    public function __construct(
        private PlanChanges $planChanges,
        private ApplyChange $applyChange,
    ) {}

    /**
     * @throws InvalidArgumentException When the id is not a page id.
     * @throws PageStorageFailed When the pages cannot be listed at all.
     * @throws PublishingFailed When the record of published pages cannot be read.
     */
    public function handle(string $pageId): StepOutcome
    {
        $id = new PageId($pageId);

        foreach ($this->planChanges->handle()->changes as $change) {
            if ($change->page->equals($id)) {
                return $this->apply($change);
            }
        }

        return StepOutcome::NothingToDo;
    }

    private function apply(Change $change): StepOutcome
    {
        try {
            $result = $this->applyChange->handle($change);
        } catch (PageDataCorrupted|PageNotFound|PageStorageFailed|PublishingFailed|RenderingFailed) {
            return StepOutcome::Failed;
        }

        return match ($result) {
            PublishResult::Published => StepOutcome::Built,
            PublishResult::Withdrawn => StepOutcome::Removed,
            PublishResult::Skipped => StepOutcome::Skipped,
        };
    }
}
