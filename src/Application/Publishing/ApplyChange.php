<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Carries out one planned change: builds the page or takes it down.
 *
 * @psalm-api Used by ApplyChanges and ApplyPageChange.
 */
final readonly class ApplyChange
{
    public function __construct(
        private PublishPage $publishPage,
        private UnpublishPage $unpublishPage,
    ) {}

    /**
     * @throws PageDataCorrupted
     * @throws PageNotFound
     * @throws PageStorageFailed
     * @throws PublishingFailed
     * @throws RenderingFailed
     */
    public function handle(Change $change): PublishResult
    {
        if ($change->action === ChangeAction::Build) {
            return $this->publishPage->handle($change->page->value);
        }

        $this->unpublishPage->handle($change->page);

        return PublishResult::Withdrawn;
    }
}
