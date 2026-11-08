<?php

declare(strict_types=1);

namespace Yef\Storage;

use RuntimeException;

/**
 * Internal to the file storage: a file operation did not succeed. Never leaves the Storage layer.
 */
final class StorageIoFailure extends RuntimeException
{
    public static function during(string $operation, string $target): self
    {
        return new self(sprintf('Could not %s "%s".', $operation, $target));
    }
}
