<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

/**
 * What came of applying the change of one page.
 */
enum StepOutcome: string
{
    case Built = 'built';
    case Removed = 'removed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /** The page is already in line with the public site, for example because the changes were applied elsewhere. */
    case NothingToDo = 'nothing_to_do';
}
