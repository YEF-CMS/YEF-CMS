<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Yef\Storage\Files;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * A scratch directory on the real file system for tests of the file storage.
 */
final readonly class TemporaryDirectory
{
    public string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/yef-test-' . bin2hex(random_bytes(6));
        mkdir($this->path, 0o775, true);
    }

    public function delete(): void
    {
        new Files()->remove($this->path);
    }
}
