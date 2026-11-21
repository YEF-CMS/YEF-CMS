<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;

use function sprintf;

/**
 * The address is not a GitHub repository of the form https://github.com/<owner>/<repository>.
 */
final class InvalidRepositoryAddress extends DomainException
{
    public static function forAddress(string $address): self
    {
        return new self(sprintf('"%s" is not an address of the form https://github.com/<owner>/<repository>.', $address));
    }
}
