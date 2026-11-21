<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;

/**
 * The downloaded module the admin refers to is not there any more. Start again from the address.
 */
final class StagingNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('The downloaded module is not there any more.');
    }
}
