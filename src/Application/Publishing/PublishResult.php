<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

/**
 * What publishing a page led to.
 *
 * @psalm-api Read by the admin and console adapters.
 */
enum PublishResult: string
{
    /** The HTML of the page is now public. */
    case Published = 'published';

    /** The page is a draft, so nothing of it is public. */
    case Withdrawn = 'withdrawn';

    /** The content type of the page is not available, so the page was left as it is. */
    case Skipped = 'skipped';
}
