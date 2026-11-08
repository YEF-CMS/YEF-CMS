<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;
use Yef\Domain\ContentType\Exception\InvalidPayload;

/**
 * Converts a payload to files and back. The type chooses names and formats, the core writes the files.
 */
interface PayloadSerializer
{
    /**
     * @return array<string, string> File name => contents.
     *
     * @throws InvalidArgumentException When the payload was not created by this content type.
     */
    public function toFiles(Payload $payload): array;

    /**
     * @param array<string, string> $files File name => contents.
     *
     * @throws InvalidPayload When the files do not form a valid payload.
     */
    public function fromFiles(array $files): Payload;
}
