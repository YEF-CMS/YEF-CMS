<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function sprintf;
use function str_replace;

/**
 * Technical identifier of a module, "vendor/name" in lowercase ("yef-cms/gallery").
 * It is not what a user reads: that is the title from the manifest.
 */
final readonly class ModuleName implements Stringable
{
    private const string PATTERN = '#^[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*$#';

    public string $value;

    /**
     * @throws InvalidArgumentException When the value is not "vendor/name".
     */
    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid module name; expected "vendor/name".', $value));
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Name of the directory in `modules/` ("yef-cms__gallery").
     */
    public function directoryName(): string
    {
        return str_replace('/', '__', $this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
