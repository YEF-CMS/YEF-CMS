<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function sprintf;
use function strlen;

/**
 * Identifier of a content type ("text", "gallery").
 */
final readonly class ContentTypeId implements Stringable
{
    private const int MAX_LENGTH = 50;

    private const string PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public string $value;

    public function __construct(string $value)
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a valid content type id.', $value),
            );
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
