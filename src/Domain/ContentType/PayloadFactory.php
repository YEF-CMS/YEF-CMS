<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use Yef\Domain\ContentType\Exception\InvalidPayload;

/**
 * Builds a validated payload from the raw data an editor submitted.
 */
interface PayloadFactory
{
    /**
     * @param array<string, mixed> $input
     *
     * @throws InvalidPayload
     */
    public function fromInput(array $input): Payload;
}
