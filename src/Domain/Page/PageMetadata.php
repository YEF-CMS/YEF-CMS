<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use DateTimeImmutable;
use Yef\Domain\ContentType\ContentTypeId;

/**
 * Data every page has, whatever its content type. Lists and queries work on metadata alone.
 */
final readonly class PageMetadata
{
    public function __construct(
        public PageId $id,
        public ContentTypeId $type,
        public PageDetails $details,
        public PageStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * A new page always starts as a draft.
     */
    public static function draft(PageId $id, ContentTypeId $type, PageDetails $details, DateTimeImmutable $now): self
    {
        return new self($id, $type, $details, PageStatus::Draft, $now, $now);
    }

    public function revised(PageDetails $details, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->type, $details, $this->status, $this->createdAt, $now);
    }

    public function withStatus(PageStatus $status, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->type, $this->details, $status, $this->createdAt, $now);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}
