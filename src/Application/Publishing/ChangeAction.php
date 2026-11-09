<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

enum ChangeAction: string
{
    /** Build the HTML of the page again. */
    case Build = 'build';

    /** Take the HTML of the page down. */
    case Remove = 'remove';
}
