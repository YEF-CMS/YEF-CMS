<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

interface PageIdGenerator
{
    public function next(): PageId;
}
