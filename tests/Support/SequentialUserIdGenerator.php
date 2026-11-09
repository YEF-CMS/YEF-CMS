<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserIdGenerator;

use function sprintf;

final class SequentialUserIdGenerator implements UserIdGenerator
{
    private int $counter = 0;

    #[Override]
    public function next(): UserId
    {
        ++$this->counter;

        return new UserId(sprintf('01JT%022d', $this->counter));
    }
}
