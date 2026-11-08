<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;
use Yef\Domain\ContentType\Exception\InvalidPayload;

/**
 * Converts a payload to storage-independent data and back. The type chooses the parts and their format,
 * the storage decides how to keep them.
 */
interface PayloadSerializer
{
    /**
     * @throws InvalidArgumentException When the payload was not created by this content type.
     */
    public function toData(Payload $payload): PayloadData;

    /**
     * @throws InvalidPayload When the data does not form a valid payload.
     */
    public function fromData(PayloadData $data): Payload;
}
