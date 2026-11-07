<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use DateTimeImmutable;

final readonly class Page
{
    public function __construct(
        public PageId $id,
        public PagePath $path,
        public PageContent $content,
        public PageStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * A new page always starts as a draft.
     */
    public static function create(PageId $id, PagePath $path, PageContent $content, DateTimeImmutable $now): self
    {
        return new self($id, $path, $content, PageStatus::Draft, $now, $now);
    }

    public function moveTo(PagePath $path, DateTimeImmutable $now): self
    {
        return new self($this->id, $path, $this->content, $this->status, $this->createdAt, $now);
    }

    public function rewrite(PageContent $content, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->path, $content, $this->status, $this->createdAt, $now);
    }

    public function publish(DateTimeImmutable $now): self
    {
        return $this->withStatus(PageStatus::Published, $now);
    }

    public function unpublish(DateTimeImmutable $now): self
    {
        return $this->withStatus(PageStatus::Draft, $now);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    private function withStatus(PageStatus $status, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->path, $this->content, $status, $this->createdAt, $now);
    }
}
