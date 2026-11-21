<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;
use Yef\Domain\Module\Install\GitHubRepository;

use function sprintf;

/**
 * The repository exists but has published no release. Only releases are installed, not branches or tags.
 */
final class NoRelease extends DomainException
{
    public static function in(GitHubRepository $repository): self
    {
        return new self(sprintf('The repository %s has no release.', $repository));
    }
}
