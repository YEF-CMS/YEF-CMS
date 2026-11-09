<?php

declare(strict_types=1);

namespace Yef\Domain\User;

interface UserIdGenerator
{
    public function next(): UserId;
}
