<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;

use function array_key_exists;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Storage-independent form of a payload: named parts with string values ("body" => "<p>…</p>").
 *
 * A part name is a logical identifier, not a file name or a column: how and where the parts are kept
 * is decided only by the storage.
 */
final readonly class PayloadData
{
    private const int MAX_NAME_LENGTH = 50;

    private const string NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @var array<string, string>
     */
    private array $parts;

    /**
     * @param array<array-key, mixed> $parts Part name => contents.
     */
    public function __construct(array $parts)
    {
        $checked = [];
        foreach ($parts as $name => $contents) {
            if (!is_string($name) || strlen($name) > self::MAX_NAME_LENGTH || preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid payload part name.', $name));
            }

            if (!is_string($contents)) {
                throw new InvalidArgumentException(sprintf('Payload part "%s" must be a string.', $name));
            }

            $checked[$name] = $contents;
        }

        $this->parts = $checked;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->parts);
    }

    /**
     * @return string|null Null when there is no such part.
     */
    public function part(string $name): ?string
    {
        return $this->parts[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function parts(): array
    {
        return $this->parts;
    }
}
