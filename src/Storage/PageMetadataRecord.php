<?php

declare(strict_types=1);

namespace Yef\Storage;

use DateTimeImmutable;
use InvalidArgumentException;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageStatus;

use function is_array;
use function is_string;
use function sprintf;

/**
 * How page metadata looks inside the file storage: a flat array of strings, ready for JSON.
 */
final readonly class PageMetadataRecord
{
    /** Keeps microseconds and the offset, so a stored date comes back unchanged. */
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return array<string, string>
     */
    public function toRecord(PageMetadata $metadata): array
    {
        return [
            'id' => $metadata->id->value,
            'type' => $metadata->type->value,
            'path' => $metadata->details->path->value,
            'title' => $metadata->details->title,
            'meta_title' => $metadata->details->metaTitle,
            'meta_description' => $metadata->details->metaDescription,
            'status' => $metadata->status->value,
            'created_at' => $metadata->createdAt->format(self::DATE_FORMAT),
            'updated_at' => $metadata->updatedAt->format(self::DATE_FORMAT),
        ];
    }

    /**
     * @throws InvalidArgumentException When the record is not valid page metadata.
     */
    public function fromRecord(mixed $record): PageMetadata
    {
        if (!is_array($record)) {
            throw new InvalidArgumentException('A metadata record must be an array.');
        }

        return new PageMetadata(
            new PageId($this->text($record, 'id')),
            new ContentTypeId($this->text($record, 'type')),
            new PageDetails(
                new PagePath($this->text($record, 'path')),
                $this->text($record, 'title'),
                $this->text($record, 'meta_title'),
                $this->text($record, 'meta_description'),
            ),
            PageStatus::tryFrom($this->text($record, 'status'))
                ?? throw new InvalidArgumentException('Unknown page status.'),
            $this->date($record, 'created_at'),
            $this->date($record, 'updated_at'),
        );
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function text(array $record, string $field): string
    {
        if (!isset($record[$field]) || !is_string($record[$field])) {
            throw new InvalidArgumentException(sprintf('Metadata field "%s" is missing or not a string.', $field));
        }

        return $record[$field];
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function date(array $record, string $field): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->text($record, $field))
            ?: throw new InvalidArgumentException(sprintf('Metadata field "%s" is not a valid date.', $field));
    }
}
