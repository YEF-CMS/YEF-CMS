<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Override;
use Stringable;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;

use function ltrim;
use function preg_match;
use function sprintf;
use function version_compare;

/**
 * The version of a release: what its name says, like "v1.2.0" or "1.2.0-rc.1", without the leading "v".
 */
final readonly class ReleaseVersion implements Stringable
{
    private const string PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    public string $value;

    /**
     * @throws InvalidReleaseVersion When the name is not a semantic version.
     */
    public function __construct(string $name)
    {
        $value = ltrim($name, 'vV');
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidReleaseVersion(sprintf('"%s" is not a semantic version.', $name));
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function isNewerThan(self $other): bool
    {
        return version_compare($this->value, $other->value, '>');
    }
}
