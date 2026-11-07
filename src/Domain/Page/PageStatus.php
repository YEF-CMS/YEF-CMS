<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
