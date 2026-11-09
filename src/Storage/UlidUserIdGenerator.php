<?php

declare(strict_types=1);

namespace Yef\Storage;

use Override;
use Symfony\Component\Uid\Ulid;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserIdGenerator;

/**
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class UlidUserIdGenerator implements UserIdGenerator
{
    #[Override]
    public function next(): UserId
    {
        return new UserId(new Ulid()->toBase32());
    }
}
