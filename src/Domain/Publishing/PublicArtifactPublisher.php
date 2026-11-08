<?php

declare(strict_types=1);

namespace Yef\Domain\Publishing;

use Yef\Domain\Publishing\Exception\PublishingFailed;

/**
 * Puts finished artifacts (page HTML, later JSON, scripts and styles) where visitors get them.
 *
 * Where and how that happens is the implementation's business.
 */
interface PublicArtifactPublisher
{
    /**
     * Replaces the artifact as a whole: visitors see either the old or the new version, never a mix.
     *
     * @throws PublishingFailed
     */
    public function publish(PublicPath $path, string $contents): void;

    /**
     * Does nothing when there is no such artifact.
     *
     * @throws PublishingFailed
     */
    public function remove(PublicPath $path): void;
}
