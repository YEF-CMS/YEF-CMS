<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use DateTimeImmutable;
use Yef\Domain\ContentType\Payload;

final readonly class Page
{
    public function __construct(
        public PageMetadata $metadata,
        public Payload $payload,
    ) {}

    public function edit(PageDetails $details, Payload $payload, DateTimeImmutable $now): self
    {
        return new self($this->metadata->revised($details, $now), $payload);
    }

    public function publish(DateTimeImmutable $now): self
    {
        return new self($this->metadata->withStatus(PageStatus::Published, $now), $this->payload);
    }

    public function unpublish(DateTimeImmutable $now): self
    {
        return new self($this->metadata->withStatus(PageStatus::Draft, $now), $this->payload);
    }
}
