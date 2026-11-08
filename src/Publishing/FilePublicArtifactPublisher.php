<?php

declare(strict_types=1);

namespace Yef\Publishing;

use Override;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rename;
use function rmdir;
use function unlink;

/**
 * Publishes artifacts as files under the document root.
 */
final readonly class FilePublicArtifactPublisher implements PublicArtifactPublisher
{
    public function __construct(private string $documentRoot) {}

    #[Override]
    public function publish(PublicPath $path, string $contents): void
    {
        $target = $this->absolute($path);
        $directory = dirname($target);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw PublishingFailed::whilePublishing($path);
        }

        $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));

        if (@file_put_contents($temporary, $contents) === false) {
            throw PublishingFailed::whilePublishing($path);
        }

        if (!@rename($temporary, $target)) {
            @unlink($temporary);

            throw PublishingFailed::whilePublishing($path);
        }
    }

    #[Override]
    public function remove(PublicPath $path): void
    {
        $target = $this->absolute($path);

        if (is_file($target) && !@unlink($target)) {
            throw PublishingFailed::whileRemoving($path);
        }

        $this->removeEmptyParents(dirname($target));
    }

    private function absolute(PublicPath $path): string
    {
        return $this->documentRoot . '/' . $path->value;
    }

    private function removeEmptyParents(string $directory): void
    {
        while ($directory !== $this->documentRoot && is_dir($directory) && glob($directory . '/*') === []) {
            if (!@rmdir($directory)) {
                return;
            }

            $directory = dirname($directory);
        }
    }
}
