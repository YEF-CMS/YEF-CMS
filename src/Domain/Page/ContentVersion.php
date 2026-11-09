<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use HashContext;
use InvalidArgumentException;
use Override;
use Stringable;
use Yef\Domain\ContentType\PayloadData;

use function hash_final;
use function hash_init;
use function hash_update;
use function ksort;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Fingerprint of everything that shows on a published page: the metadata an editor controls and the payload.
 *
 * Creation and modification dates are not part of it, so saving a page without changes keeps the version.
 */
final readonly class ContentVersion implements Stringable
{
    private const string PATTERN = '/^[0-9a-f]{64}$/';

    public string $value;

    /**
     * @throws InvalidArgumentException When the value is not a SHA-256 hex digest.
     */
    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid content version.', $value));
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public static function of(PageMetadata $metadata, PayloadData $data): self
    {
        $context = hash_init('sha256');
        $fields = [
            $metadata->type->value,
            $metadata->details->path->value,
            $metadata->details->title,
            $metadata->details->metaTitle,
            $metadata->details->metaDescription,
            $metadata->status->value,
        ];

        foreach ($fields as $field) {
            self::feed($context, $field);
        }

        $parts = $data->parts();
        ksort($parts);
        foreach ($parts as $name => $contents) {
            self::feed($context, $name);
            self::feed($context, $contents);
        }

        return new self(hash_final($context));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Length-prefixed, so that no two different field lists can produce the same byte stream.
     */
    private static function feed(HashContext $context, string $field): void
    {
        hash_update($context, strlen($field) . ':' . $field . ';');
    }
}
