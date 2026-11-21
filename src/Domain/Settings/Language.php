<?php

declare(strict_types=1);

namespace Yef\Domain\Settings;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function sprintf;

/**
 * A language by its code: two lowercase letters, like "en" or "ru".
 */
final readonly class Language implements Stringable
{
    private const string PATTERN = '/^[a-z]{2}$/';

    public string $code;

    /**
     * @throws InvalidArgumentException When the code is not two lowercase letters.
     */
    public function __construct(string $code)
    {
        if (preg_match(self::PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a language code; expected two lowercase letters.', $code));
        }

        $this->code = $code;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->code;
    }
}
