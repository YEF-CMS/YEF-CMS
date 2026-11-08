<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;

final class InMemoryArtifactPublisher implements PublicArtifactPublisher
{
    /** @var array<string, string> */
    public array $artifacts = [];

    #[Override]
    public function publish(PublicPath $path, string $contents): void
    {
        $this->artifacts[$path->value] = $contents;
    }

    #[Override]
    public function remove(PublicPath $path): void
    {
        unset($this->artifacts[$path->value]);
    }
}
