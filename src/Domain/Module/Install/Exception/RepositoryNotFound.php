<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;
use Yef\Domain\Module\Install\GitHubRepository;

use function sprintf;

/**
 * There is no such repository, or it is not public.
 */
final class RepositoryNotFound extends DomainException
{
    public static function named(GitHubRepository $repository): self
    {
        return new self(sprintf('The repository %s was not found.', $repository));
    }
}
