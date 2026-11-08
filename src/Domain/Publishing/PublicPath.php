<?php

declare(strict_types=1);

namespace Yef\Domain\Publishing;

use InvalidArgumentException;
use Override;
use Stringable;
use Yef\Domain\Page\PagePath;

use function explode;
use function preg_match;
use function sprintf;

/**
 * Location of a public artifact relative to the document root ("blog/hello/index.html").
 */
final readonly class PublicPath implements Stringable
{
    private const string SEGMENT_PATTERN = '/^[a-zA-Z0-9_][a-zA-Z0-9_.-]*$/';

    private const string PAGE_FILE = 'index.html';

    public string $value;

    /**
     * @throws InvalidArgumentException When the path is empty, absolute or could leave the document root.
     */
    public function __construct(string $value)
    {
        foreach (explode('/', $value) as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                throw new InvalidArgumentException(sprintf('"%s" is not a valid public path.', $value));
            }
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public static function forPage(PagePath $path): self
    {
        return new self($path->isHome() ? self::PAGE_FILE : $path->value . '/' . self::PAGE_FILE);
    }
}
