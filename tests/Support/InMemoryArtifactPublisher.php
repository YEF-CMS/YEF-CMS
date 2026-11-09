<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;

final class InMemoryArtifactPublisher implements PublicArtifactPublisher
{
    /** @var array<string, string> */
    public array $artifacts = [];

    /** Removing this path fails, to test how callers cope with a failing publisher. */
    public ?string $failToRemove = null;

    #[Override]
    public function publish(PublicPath $path, string $contents): void
    {
        $this->artifacts[$path->value] = $contents;
    }

    #[Override]
    public function remove(PublicPath $path): void
    {
        if ($path->value === $this->failToRemove) {
            throw PublishingFailed::whileRemoving($path);
        }

        unset($this->artifacts[$path->value]);
    }
}
