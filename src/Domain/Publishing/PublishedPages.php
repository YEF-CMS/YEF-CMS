<?php

declare(strict_types=1);

namespace Yef\Domain\Publishing;

use Yef\Domain\Page\PageId;
use Yef\Domain\Publishing\Exception\PublishingFailed;

/**
 * A record of the pages whose HTML is public. A lost record reads as empty: every page then counts as unpublished.
 */
interface PublishedPages
{
    /**
     * @return list<PublishedPage>
     *
     * @throws PublishingFailed
     */
    public function all(): array;

    /**
     * @throws PublishingFailed
     */
    public function find(PageId $id): ?PublishedPage;

    /**
     * Adds the page or replaces its earlier entry.
     *
     * @throws PublishingFailed
     */
    public function record(PublishedPage $page): void;

    /**
     * Does nothing when the page has no entry.
     *
     * @throws PublishingFailed
     */
    public function forget(PageId $id): void;
}
