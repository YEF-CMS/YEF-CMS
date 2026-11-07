<?php

declare(strict_types=1);

namespace Yef\Domain;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
